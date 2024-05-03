<?php

namespace LakM\Comments\Livewire;

use GrahamCampbell\Security\Facades\Security;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use LakM\Comments\Actions\CreateCommentReplyAction;
use LakM\Comments\Exceptions\ReplyLimitExceeded;
use LakM\Comments\Models\Comment;
use LakM\Comments\Repository;
use LakM\Comments\ValidationRules;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Spatie\Honeypot\Http\Livewire\Concerns\HoneypotData;
use Spatie\Honeypot\Http\Livewire\Concerns\UsesSpamProtection;

class CreateCommentReplyForm extends Component
{
    use UsesSpamProtection;

    public bool $show = false;

    #[Locked]
    public Comment $comment;

    #[Locked]
    public ?Model $relatedModel;

    #[Locked]
    public bool $loginRequired;

    #[Locked]
    public bool $limitExceeded;

    #[Locked]
    public bool $approvalRequired;

    public HoneypotData $honeyPostData;

    public string $guest_name = '';

    public string $guest_email = '';

    public string $text = "";

    public string $editorId;
    public string $toolbarId;

    #[Locked]
    public bool $authenticated;

    #[Locked]
    public bool $guestMode;

    /**
     * @param  Comment  $comment
     * @param  Model  $relatedModel
     * @param  bool  $guestMode
     * @return void
     */
    public function mount(Comment $comment, Model $relatedModel, bool $guestMode): void
    {
        $this->comment = $comment;
        $this->relatedModel = $relatedModel;

        $this->guestMode = $guestMode;

        $this->authenticated = $this->relatedModel->authCheck();

        $this->setLoginRequired();

        $this->setApprovalRequired();

        $this->honeyPostData = new HoneypotData();

        $this->editorId = 'editor'.Str::random();
        $this->toolbarId = 'toolbar'.Str::random();
    }

    public function rules(): array
    {
        return ValidationRules::get($this->relatedModel, 'create');
    }

    /**
     * @throws \Exception
     */
    public function create(CreateCommentReplyAction $replyAction): void
    {
        $this->protectAgainstSpam();

        $this->validate();

        if (! $this->guestMode) {
            Gate::authorize('create-reply');
        }

        throw_if($this->limitExceeded, new ReplyLimitExceeded($this->replyLimit()));

        CreateCommentReplyAction::execute($this->comment, $this->getFormData(), $this->guestMode);

        $this->clear();
        $this->dispatch('reply-created', commentId: $this->comment->getKey(), approvalRequired:$this->approvalRequired);

        $this->setLimitExceeded();
    }

    private function getFormData(): array
    {
        $data = $this->only('guest_name', 'guest_email', 'text');
        return $this->clearFormData($data);

    }

    private function clearFormData(array $data): array
    {
        return array_map(function (string $value) {
            return Security::clean($value);
        }, $data);

    }

    public function draft(): void
    {
        $this->dispatch('reply-drafted', commentId: $this->comment->getKey());
    }

    public function discard(): void
    {
        $this->dispatch('reply-discarded', commentId: $this->comment->getKey());
    }

    public function setLoginRequired(): void
    {
        $this->loginRequired = !$this->authenticated && !$this->guestMode;
    }

    public function setLimitExceeded(): void
    {
        $limit = $this->replyLimit();

        if (is_null($limit)) {
            $this->limitExceeded = false;
            return;
        }

        $this->limitExceeded = Repository::userReplyCountForComment(
                $this->comment, $this->guestMode, $this->relatedModel->getAuthUser()
            ) >= $limit;

    }

    public function setApprovalRequired(): void
    {
        $this->approvalRequired = config('comments.reply.approval_required');
    }

    public function replyLimit(): ?int
    {
        return config('comments.reply.limit');
    }

    public function clear(): void
    {
        $this->resetValidation();
        $this->reset('guest_name', 'guest_email', 'text');
    }

    public function redirectToLogin(string $redirectUrl): void
    {
        session(['url.intended' => $redirectUrl]);
        $this->redirect(config('comments.login_route'));
    }


    #[On('show-create-reply-form.{comment.id}')]
    public function setShowStatus(): void
    {
        $this->show = !$this->show;

        if ($this->show && !isset($this->limitExceeded)) {
            $this->setLimitExceeded();
        }
    }

    public function render(): View|Factory|Application
    {
        return view('comments::livewire.create-comment-reply-form');
    }
}

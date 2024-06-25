<?php

namespace LakM\Comments\Livewire;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use LakM\Comments\Models\Comment;
use LakM\Comments\Repository;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class CommentReplyList extends Component
{
    use WithPagination;

    public bool $show = false;

    #[Locked]
    public Comment $comment;

    #[Locked]
    public Model $relatedModel;

    public int $total;

    public int $limit = 15;

    public int $perPage;

    public bool $paginationRequired;

    #[Locked]
    public bool $guestMode;

    #[Locked]
    public bool $approvalRequired;

    public string $sortBy = '';

    public string $filter = '';

    public function mount(Comment $comment, Model $relatedModel, int $total): void
    {
        if (!$this->show) {
            $this->skipRender();
        }

        $this->comment = $comment;
        $this->relatedModel = $relatedModel;

        $this->total = $total;

        $this->perPage = config('comments.reply.pagination.per_page');
        $this->limit = config('comments.reply.pagination.per_page');

        $this->guestMode = $this->relatedModel->guestModeEnabled();

        $this->setPaginationRequired();

        $this->setApprovalRequired();
    }

    public function paginate(): void
    {
        $this->limit += $this->perPage;

        $this->dispatch('more-replies-loaded');
    }

    public function setSortBy(string $sortBy): void
    {
        $this->sortBy = $sortBy;

        $this->dispatchFilterAppliedEvent();
    }

    public function setFilter(string $filter): void
    {
        if ($this->filter) {
            $this->filter = '';
        } else {
            $this->filter = $filter;
        }

        $this->setTotalRepliesCount();
        $this->setPaginationRequired();
        $this->dispatchFilterAppliedEvent();
    }

    public function setTotalRepliesCount(): int
    {
        return $this->total = Repository::getCommentReplyCount($this->comment, $this->relatedModel, $this->approvalRequired,  $this->filter);
    }

    #[On('show-replies.{comment.id}')]
    public function setShowStatus(): void
    {
        $this->show = !$this->show;

        $this->dispatch('show-reply');
    }

    #[On('reply-created-{comment.id}')]
    public function onReplyCreated($commentId): void
    {
        if ($this->approvalRequired) {
            return;
        }

        if ($commentId === $this->comment->getKey()) {
            $this->total += 1;
        }
    }

    #[On('reply-deleted')]
    public function onReplyDeleted($commentId): void
    {
        if ($commentId === $this->comment->getKey()) {
            $this->total -= 1;
        }
    }

    private function setPaginationRequired(): void
    {
        $this->paginationRequired = $this->limit < $this->total;
    }

    public function setApprovalRequired(): void
    {
        $this->approvalRequired = config('comments.reply.approval_required');
    }

    public function dispatchFilterAppliedEvent(): void
    {
        $this->dispatch('filter-applied');
    }

    public function render(): View|Factory|Application
    {
        return view(
            'comments::livewire.comment-replies-list',
            ['replies' => Repository::commentReplies($this->comment, $this->relatedModel, $this->approvalRequired, $this->limit, $this->sortBy, $this->filter)]
        );
    }
}

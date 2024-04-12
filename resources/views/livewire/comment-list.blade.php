<div @comment-created.window="$wire.$refresh" class="space-y-8">
    <div class="text-lg font-bold">
        {{__('Comments')}} ({{$total}})
    </div>
    @if($comments->isNotEmpty())
        @foreach($comments as $comment)
            <div
                    x-ref="comment{{$comment->getKey()}}" wire:key="{{$comment->getKey()}}"
                    class="flex gap-x-2 sm:gap-x-4"
            >
                <div class="basis-12">
                    @if(!$guestMode && $profilePhotoUrl)
                        <a href="{{$comment->commenter->$profilePhotoUrl}}" target="_blank">
                            <img
                                    class="border border-gray-200 rounded-full"
                                    src="{{$comment->commenter->$profilePhotoUrl}}"
                                    alt="{{$comment->commenter->name}}"
                            >
                        </a>
                    @else
                        <img
                                class="border border-gray-200 rounded-full"
                                src="vendor/lakm/laravel-comments/img/user.png"
                                alt="{{$guestMode ? $comment->guest_name : $comment->commenter->name}}"
                        >
                    @endif
                </div>
                <div
                        wire:ignore
                        x-data="{showUpdateForm: false}"
                        @comment-update-discarded.window="(e) => {
                             if(e.detail.commentId === @js($comment->getKey())) {
                                   showUpdateForm = false;
                             }
                        }"
                        class="basis-full"
                >
                    <div x-show="!showUpdateForm" x-transition class="border rounded">
                        <div class="flex justify-between bg-gray-100 border-b p-1 mb-2 items-center space-x-4">
                            <div class="space-x-4">
                                <span class="font-bold">{{$guestMode ? $comment->guest_name : $comment->commenter->name}}</span>
                                <span x-text="moment(@js($comment->created_at)).format('YYYY/M/D H:mm')"
                                      class="text-xs"></span>
                            </div>
                            @if($model->canCreateComment($comment))
                                <div @click="showUpdateForm = !showUpdateForm">
                                    <x-comments::action class="text-sm">Edit</x-comments::action>
                                </div>
                            @endif
                        </div>
                        <div
                                x-ref="text" @comment-updated.window="(e) => {
                                let key = @js($comment->getKey());
                                if(e.detail.commentId === key) {
                                    if(@js($model->approvalRequired())) {
                                        let elm = 'comment'+ key;
                                         setTimeout(() => {
                                           $refs[elm].remove();
                                         }, 2000);
                                        return;
                                    }
                                    $refs.text.innerHTML = e.detail.text;
                                    showUpdateForm = false;
                                }
                            }"
                                class="p-1"
                        >
                            {!! $comment->text !!}
                        </div>
                    </div>
                    <div x-show="showUpdateForm" x-transition class="basis-full">
                        @if($model->canCreateComment($comment))
                            <livewire-comments-update-form
                                    :comment="$comment"
                                    :model="$model"
                                    :key="$comment->getKey()"
                            />
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    @else
        <div class="text-lg">{{__('Be the first one to make the comment !')}}</div>
    @endif

    @if($comments->isNotEmpty())
        <div class="flex justify-center items-center">
            @if($limit < $total)
                <x-comments::button wire:click="paginate" type="button" loadingTarget="paginate">
                    {{__('Load More')}}
                </x-comments::button>
            @else
                <div class="font-bold">{{__('End of comments')}}</div>
            @endif
        </div>
    @endif
</div>

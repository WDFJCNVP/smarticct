<?php

namespace App\Livewire\Pages;

use Livewire\Component;
use Livewire\Attributes\Computed;
use App\Models\Post;
use App\Models\RentalOffer;
use App\Models\TripRequest;
use Illuminate\Support\Facades\Storage;
use Flux\Flux;

new class extends Component
{
    public $selectedPostId = null;
    public bool $show_delete_post_modal = false;
    public ?int $deletePostId = null;

    public function restorePost($postId)
    {
        $post = Post::findOrFail($postId);

        if ($post->user_id !== auth()->id()) {
            return;
        }

        // A post archived because a transaction on it was completed shouldn't be
        // restorable back into the feed — there's nothing to "un-complete".
        if (! empty($post->metadata['transaction_completed'])) {
            Flux::toast(
                duration: 0,
                variant: 'danger',
                heading: 'Cannot restore this post',
                text: 'This post was archived because its transaction was completed and cannot be restored.',
            );
            return;
        }

        $post->update(['status' => 'published']);

        Flux::toast(
            duration: 0,
            variant: 'success',
            heading: 'Post restored',
            text: 'Your post is back in the feed.',
        );
    }

    public function confirmDeletePost($postId)
    {
        $post = Post::findOrFail($postId);

        if ($post->user_id !== auth()->id() || ! in_array(auth()->user()->role, ['operator', 'commuter'])) {
            return;
        }

        $hasActiveInterest = $post->tripRequest()->whereIn('status', ['pending', 'accept'])->exists()
            || $post->rentalOffer()->whereIn('status', ['pending', 'accept'])->exists();

        if ($hasActiveInterest) {
            Flux::toast(
                duration: 0,
                variant: 'danger',
                heading: 'Cannot delete this post',
                text: 'This post has pending or active requests. Resolve or cancel those first.',
            );
            return;
        }

        $this->deletePostId = $postId;
        $this->show_delete_post_modal = true;
    }

    public function cancelDeletePost()
    {
        $this->deletePostId = null;
        $this->show_delete_post_modal = false;
    }

    public function deletePost()
    {
        if (! $this->deletePostId) {
            $this->show_delete_post_modal = false;
            return;
        }

        $post = Post::find($this->deletePostId);

        if (! $post || $post->user_id !== auth()->id() || ! in_array(auth()->user()->role, ['operator', 'commuter'])) {
            $this->deletePostId = null;
            $this->show_delete_post_modal = false;
            return;
        }

        $hasActiveInterest = $post->tripRequest()->whereIn('status', ['pending', 'accept'])->exists()
            || $post->rentalOffer()->whereIn('status', ['pending', 'accept'])->exists();

        if ($hasActiveInterest) {
            $this->deletePostId = null;
            $this->show_delete_post_modal = false;
            return;
        }

        // Attachments aren't removed here — the post is only soft-deleted, so it
        // can still be restored from Trash. They're cleaned up by the daily purge
        // job in routes/console.php once the post is 30 days old.
        $post->delete();

        $this->deletePostId = null;
        $this->show_delete_post_modal = false;

        unset($this->getArchivedPost);

        Flux::toast(
            duration: 0,
            variant: 'success',
            heading: 'Post moved to Trash',
            text: 'You can restore it within 30 days, from the Trash page.',
        );
    }

    public function canExpressInterest(Post $post): bool
    {
        $user = auth()->user();

        if ($user->id === $post->user_id) {
            return false;
        }

        if ($post->status !== 'published') {
            return false;
        }

        if ($post->type === 'announcement') {
            return in_array($user->role, ['admin', 'cashier']);
        }

        if ($post->type === 'rental') {
            if ($user->role === 'operator') {
                return $post->user->role === 'commuter';
            }

            if ($user->role === 'commuter') {
                return $post->user->role === 'operator';
            }
        }

        return false;
    }

    public function uninterested($postId)
    {
        $this->selected_post = null;
        $this->show_interested_modal = false;

        if(auth()->user()->role === 'operator') {
            $this->selected_post = RentalOffer::where('user_id', auth()->id())
                ->where('post_id', $postId)
                ->first();
        } elseif(auth()->user()->role === 'commuter') {
            $this->selected_post = TripRequest::where('user_id', auth()->id())
                    ->where('post_id', $postId)
                    ->first();
        }

        $this->show_delete_interested_modal = true;
    }

    #[Computed]
    public function myInterestedPostIds()
    {
        if(auth()->user()->role === 'operator') {
            return RentalOffer::where('user_id', auth()->id())
                ->pluck('post_id')
                ->all();
        } elseif(auth()->user()->role === 'commuter') {
            return TripRequest::where('user_id', auth()->id())
                ->pluck('post_id')
                ->all();
        }

        return [];
    }

    #[Computed]
    public function getArchivedPost()
    {
        return Post::with('user')
            ->where('status', 'archived')
            ->where(function ($query) {
                $query->where('user_id', auth()->id())
                    ->orWhere('metadata->completed_with_user_id', auth()->id());
            })
            ->withCount(['tripRequest' => function ($query) {
                $query->whereIn('status',['pending', 'cancel']);
            }])
            ->withCount(['rentalOffer' => function ($query) {
                $query->whereIn('status',['pending', 'cancel']);
            }])
            ->latest()
            ->get();
    }

    public function render()
    {
        $role = auth()->user()->role;
        return $this->view()->layout('layouts.' . $role . '-layout');
    }
};
?>

<div>
    {{-- Header — matches Feed exactly --}}
    <div class="flex flex-wrap items-start justify-between gap-2 sm:gap-3 mb-4 sm:mb-6">
        <div>
            <x-heading
                size="xl"
                class="!font-primary !font-bold !text-light-txt-primary dark:!text-dark-txt-primary"
                style="font-size: var(--text-page-title)"
            >
                Archived Posts
            </x-heading>
            <x-text variant="subtle" class="!font-secondary mt-1 block" style="font-size: var(--text-helper)">
                Your archived rental posts and announcements.
            </x-text>
        </div>

        {{-- Navigation buttons — exactly like Feed --}}
        <div class="flex items-center gap-1 sm:gap-2 flex-wrap mt-1 sm:mt-0">
            <x-button
                href="{{ route('feed') }}"
                wire:navigate
                variant="ghost"
                icon="home"
                class="!font-secondary text-sm sm:text-base !px-2 sm:!px-3 !py-1 sm:!py-2"
            >
                <span class="hidden sm:inline">Feed</span>
                <span class="sm:hidden">Feed</span>
            </x-button>
            <x-button
                href="{{ route('post.my-posts') }}"
                wire:navigate
                variant="ghost"
                icon="document-text"
                class="!font-secondary text-sm sm:text-base !px-2 sm:!px-3 !py-1 sm:!py-2"
            >
                <span class="hidden sm:inline">My Posts</span>
                <span class="sm:hidden">My Posts</span>
            </x-button>
            <x-button
                href="{{ route('post.trash') }}"
                wire:navigate
                variant="ghost"
                icon="trash"
                class="!font-secondary text-sm sm:text-base !px-2 sm:!px-3 !py-1 sm:!py-2"
            >
                <span class="hidden sm:inline">Trash</span>
                <span class="sm:hidden">Trash</span>
            </x-button>
        </div>
    </div>

    {{-- Post list — same as Feed --}}
    <div class="flex-1 min-h-0 overflow-y-auto space-y-4 [&::-webkit-scrollbar]:hidden [-ms-overflow-style:none] [scrollbar-width:none]">
        @forelse ($this->getArchivedPost as $post)
            <x-post-card :post="$post" wire:key="archived-post-{{ $post->id }}">
                <x-slot name="footer">
                    @php
                        $isOwner = $post->user_id === auth()->id();
                        $alreadyInterested = in_array($post->id, $this->myInterestedPostIds);
                        $role = auth()->user()->role;
                    @endphp
                    
                    @include("partials.feed.actions.{$role}", [
                        'post' => $post,
                        'isOwner' => $isOwner,
                    ])
                </x-slot>
            </x-post-card>
        @empty
            {{-- Empty state — matches Feed --}}
            <x-card class="!rounded-xl !border !border-dashed !border-light-bd-strong dark:!border-dark-bd-strong !bg-light-secondary dark:!bg-dark-secondary !text-center !p-8">
                <flux:icon name="archive-box" class="w-8 h-8 mx-auto text-light-txt-muted dark:text-dark-txt-muted mb-2" />
                <x-text variant="subtle" class="!font-secondary block" style="font-size: var(--text-table-row)">
                    No archived posts yet.
                </x-text>
                <x-text variant="subtle" class="!font-secondary block mt-1" style="font-size: var(--text-timestamp)">
                    Posts you archive will appear here.
                </x-text>
            </x-card>
        @endforelse
    </div>

    <flux:modal
        wire:model="show_delete_post_modal"
        :closable="false"
        class="w-full max-w-[95vw] sm:max-w-lg md:max-w-2xl lg:max-w-3xl mx-auto max-h-[80vh] sm:max-h-[90vh] overflow-hidden rounded-xl"
    >
        <div class="flex flex-col max-h-[calc(80vh-2rem)] sm:max-h-[calc(90vh-2rem)] overflow-y-auto overscroll-contain p-4 sm:p-6 !pr-4 sm:!pr-6 space-y-5">
            <div class="flex items-start justify-between">
                <div>
                    <flux:heading size="xl" class="!font-primary !font-bold text-light-txt-primary dark:text-dark-txt-primary">
                        Delete this post?
                    </flux:heading>
                    <flux:text class="mt-1 font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">
                        This will move the post to Trash. You can restore it within 30 days — after that, it's deleted for good.
                    </flux:text>
                </div>
                <flux:modal.close>
                    <button type="button" class="p-1 rounded-full hover:bg-light-subtle dark:hover:bg-dark-subtle text-light-txt-muted dark:text-dark-txt-muted -mt-1">
                        <flux:icon name="x-mark" class="w-5 h-5" />
                    </button>
                </flux:modal.close>
            </div>

            <div class="flex flex-col-reverse sm:flex-row justify-end items-stretch sm:items-center gap-2 pt-2 border-t border-light-bd-default dark:border-dark-bd-default">
                <flux:modal.close>
                    <x-button type="button" variant="ghost" wire:click="cancelDeletePost" class="w-full sm:w-auto justify-center !font-secondary">
                        Cancel
                    </x-button>
                </flux:modal.close>
                <x-button
                    wire:click="deletePost"
                    wire:loading.attr="disabled"
                    type="button"
                    variant="primary"
                    color="red"
                    class="w-full sm:w-auto justify-center !font-secondary"
                >
                    Move to Trash
                </x-button>
            </div>
        </div>
    </flux:modal>
</div>
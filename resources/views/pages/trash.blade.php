<?php

namespace App\Livewire\Pages;

use Livewire\Component;
use Livewire\Attributes\Computed;
use App\Models\Post;
use Flux\Flux;
use Illuminate\Support\Facades\Storage;

new class extends Component
{
    public $selectedPostId = null;
    public $selected_post = null;
    public bool $show_delete_post_modal = false;
    public bool $show_restore_post_modal = false;
    public ?int $deletePostId = null;
    public ?int $restorePostId = null;

    public function confirmDeletePost($postId)
    {
        $post = Post::onlyTrashed()->findOrFail($postId);

        if ($post->user_id !== auth()->id() || ! in_array(auth()->user()->role, ['operator', 'commuter'])) {
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

    public function deletePostPermanently()
    {
        if (! $this->deletePostId) {
            $this->show_delete_post_modal = false;
            return;
        }

        $post = Post::onlyTrashed()->find($this->deletePostId);

        if (! $post || $post->user_id !== auth()->id() || ! in_array(auth()->user()->role, ['operator', 'commuter'])) {
            $this->deletePostId = null;
            $this->show_delete_post_modal = false;
            return;
        }

        // Clean up attachments before permanent deletion
        foreach (($post->metadata['attachments'] ?? []) as $attachmentPath) {
            Storage::disk('public')->delete($attachmentPath);
        }

        $post->forceDelete();

        $this->deletePostId = null;
        $this->show_delete_post_modal = false;

        unset($this->getTrashedPosts);

        Flux::toast(
            duration: 0,
            variant: 'success',
            heading: 'Post permanently deleted',
            text: 'This cannot be undone.',
        );
    }

    public function confirmRestorePost($postId)
    {
        $post = Post::onlyTrashed()->findOrFail($postId);

        if ($post->user_id !== auth()->id() || ! in_array(auth()->user()->role, ['operator', 'commuter'])) {
            return;
        }

        $this->restorePostId = $postId;
        $this->show_restore_post_modal = true;
    }

    public function cancelRestorePost()
    {
        $this->restorePostId = null;
        $this->show_restore_post_modal = false;
    }

    public function restorePost()
    {
        if (! $this->restorePostId) {
            $this->show_restore_post_modal = false;
            return;
        }

        $post = Post::onlyTrashed()->find($this->restorePostId);

        if (! $post || $post->user_id !== auth()->id() || ! in_array(auth()->user()->role, ['operator', 'commuter'])) {
            $this->restorePostId = null;
            $this->show_restore_post_modal = false;
            return;
        }

        $post->restore();

        $this->restorePostId = null;
        $this->show_restore_post_modal = false;

        unset($this->getTrashedPosts);

        Flux::toast(
            duration: 0,
            variant: 'success',
            heading: 'Post restored',
            text: 'Your post is back in your feed.',
        );
    }

    #[Computed]
    public function getTrashedPosts()
    {
        return Post::onlyTrashed()
            ->with('user')
            ->where('user_id', auth()->user()->id)
            ->latest('deleted_at')
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
    {{-- Header --}}
    <div class="flex flex-wrap items-start justify-between gap-2 sm:gap-3 mb-4 sm:mb-6">
        <div>
            <x-heading
                size="xl"
                class="!font-primary !font-bold !text-light-txt-primary dark:!text-dark-txt-primary"
                style="font-size: var(--text-page-title)"
            >
                Trash
            </x-heading>
            <x-text variant="subtle" class="!font-secondary mt-1 block" style="font-size: var(--text-helper)">
                Posts are kept here for 30 days. After that, they're automatically deleted forever.
            </x-text>
        </div>

        {{-- Navigation buttons --}}
        <div class="flex items-center gap-1 sm:gap-2 flex-wrap mt-1 sm:mt-0">
            <x-button
                href="{{ route('feed') }}"
                wire:navigate
                variant="ghost"
                icon="home"
                class="!font-secondary text-sm sm:text-base !px-2 sm:!px-3 !py-1 sm:!py-2"
            >
                <span class="hidden sm:inline">Feed</span>
            </x-button>
            <x-button
                href="{{ route('post.my-posts') }}"
                wire:navigate
                variant="ghost"
                icon="document-text"
                class="!font-secondary text-sm sm:text-base !px-2 sm:!px-3 !py-1 sm:!py-2"
            >
                <span class="hidden sm:inline">My Posts</span>
            </x-button>
            <x-button
                href="{{ route('post.archived') }}"
                wire:navigate
                variant="ghost"
                icon="archive-box"
                class="!font-secondary text-sm sm:text-base !px-2 sm:!px-3 !py-1 sm:!py-2"
            >
                <span class="hidden sm:inline">Archived</span>
            </x-button>
        </div>
    </div>

    {{-- Post list --}}
    <div class="flex-1 min-h-0 overflow-y-auto space-y-4 [&::-webkit-scrollbar]:hidden [-ms-overflow-style:none] [scrollbar-width:none]">
        @forelse ($this->getTrashedPosts as $post)
            <x-post-card :post="$post" wire:key="trash-post-{{ $post->id }}">
                <x-slot name="footer">
                    <div class="flex items-center justify-between gap-2">
                        <x-text variant="subtle" class="!font-secondary text-xs block">
                            Deleted {{ $post->deleted_at?->diffForHumans() ?? 'unknown time ago' }}
                        </x-text>
                        <div class="flex items-center gap-2">
                            <flux:button
                                wire:click="confirmRestorePost({{ $post->id }})"
                                variant="ghost"
                                size="sm"
                                icon="arrow-path"
                                class="!font-secondary"
                            >
                                Restore
                            </flux:button>
                            <flux:button
                                wire:click="confirmDeletePost({{ $post->id }})"
                                variant="danger"
                                size="sm"
                                icon="trash"
                                class="!font-secondary"
                            >
                                Delete now
                            </flux:button>
                        </div>
                    </div>
                </x-slot>
            </x-post-card>
        @empty
            {{-- Empty state --}}
            <x-card class="!rounded-xl !border !border-dashed !border-light-bd-strong dark:!border-dark-bd-strong !bg-light-secondary dark:!bg-dark-secondary !text-center !p-8">
                <flux:icon name="trash" class="w-8 h-8 mx-auto text-light-txt-muted dark:text-dark-txt-muted mb-2" />
                <x-text variant="subtle" class="!font-secondary block" style="font-size: var(--text-table-row)">
                    Trash is empty.
                </x-text>
                <x-text variant="subtle" class="!font-secondary block mt-1" style="font-size: var(--text-timestamp)">
                    Posts you delete will appear here for 30 days.
                </x-text>
            </x-card>
        @endforelse
    </div>

    {{-- Restore confirmation modal --}}
    <flux:modal
        wire:model="show_restore_post_modal"
        :closable="false"
        class="w-full max-w-[95vw] sm:max-w-lg md:max-w-2xl lg:max-w-3xl mx-auto max-h-[80vh] sm:max-h-[90vh] overflow-hidden rounded-xl"
    >
        <div class="flex flex-col max-h-[calc(80vh-2rem)] sm:max-h-[calc(90vh-2rem)] overflow-y-auto overscroll-contain p-4 sm:p-6 !pr-4 sm:!pr-6 space-y-5">
            <div class="flex items-start justify-between">
                <div>
                    <flux:heading size="xl" class="!font-primary !font-bold text-light-txt-primary dark:text-dark-txt-primary">
                        Restore this post?
                    </flux:heading>
                    <flux:text class="mt-1 font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">
                        It will be returned to your feed and visible to others again.
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
                    <x-button type="button" variant="ghost" wire:click="cancelRestorePost" class="w-full sm:w-auto justify-center !font-secondary">
                        Cancel
                    </x-button>
                </flux:modal.close>
                <x-button
                    wire:click="restorePost"
                    wire:loading.attr="disabled"
                    type="button"
                    variant="primary"
                    class="w-full sm:w-auto justify-center !font-secondary"
                >
                    Restore
                </x-button>
            </div>
        </div>
    </flux:modal>

    {{-- Permanent delete confirmation modal --}}
    <flux:modal
        wire:model="show_delete_post_modal"
        :closable="false"
        class="w-full max-w-[95vw] sm:max-w-lg md:max-w-2xl lg:max-w-3xl mx-auto max-h-[80vh] sm:max-h-[90vh] overflow-hidden rounded-xl"
    >
        <div class="flex flex-col max-h-[calc(80vh-2rem)] sm:max-h-[calc(90vh-2rem)] overflow-y-auto overscroll-contain p-4 sm:p-6 !pr-4 sm:!pr-6 space-y-5">
            <div class="flex items-start justify-between">
                <div>
                    <flux:heading size="xl" class="!font-primary !font-bold text-light-txt-primary dark:text-dark-txt-primary">
                        Delete permanently?
                    </flux:heading>
                    <flux:text class="mt-1 font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">
                        This will permanently delete the post and all its photos. This action cannot be undone.
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
                    wire:click="deletePostPermanently"
                    wire:loading.attr="disabled"
                    type="button"
                    variant="primary"
                    color="red"
                    class="w-full sm:w-auto justify-center !font-secondary"
                >
                    Delete permanently
                </x-button>
            </div>
        </div>
    </flux:modal>
</div>
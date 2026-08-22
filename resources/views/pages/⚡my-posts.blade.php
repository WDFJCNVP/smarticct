<?php

namespace App\Livewire\Pages;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use App\Models\Post;
use App\Models\RentalOffer;
use App\Models\TripRequest;

new class extends Component
{
    public $selectedPostId = null;
    public $selected_post = null;
    public bool $show_delete_interested_modal = false;

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
        $this->show_delete_interested_modal = false;

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

    #[On('interest-deleted')]
    #[On('interested-list-updated')]
    public function closeModals()
    {
        $this->show_delete_interested_modal = false;
        $this->selected_post = null;

        unset($this->myActivePosts);
        unset($this->myInterestedPostIds);
    }

    #[Computed]
    public function myInterestedPostIds()
    {
        if(auth()->user()->role === 'operator') {
            return RentalOffer::where('user_id', auth()->id())
                ->where('status', '!=', 'decline')
                ->pluck('post_id')
                ->all();
        } elseif(auth()->user()->role === 'commuter') {
            return TripRequest::where('user_id', auth()->id())
                ->where('status', '!=', 'decline')
                ->pluck('post_id')
                ->all();
        }

        return [];
    }

    public function archivePost($postId)
    {
        $post = Post::findOrFail($postId);

        if ($post->user_id === auth()->id()) {
            $post->update(['status' => 'archived']);
        }
    }

    #[Computed]
    public function myActivePosts()
    {
        return Post::with('user')
            ->whereIn('status', ['published', 'rented'])
            ->where('user_id', auth()->user()->id)
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
                My Posts
            </x-heading>
            <x-text variant="subtle" class="!font-secondary mt-1 block" style="font-size: var(--text-helper)">
                Your published and rented posts.
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
                href="{{ route('post.archived') }}"
                wire:navigate
                variant="ghost"
                icon="archive-box"
                class="!font-secondary text-sm sm:text-base !px-2 sm:!px-3 !py-1 sm:!py-2"
            >
                <span class="hidden sm:inline">Archived</span>
                <span class="sm:hidden">Archived</span>
            </x-button>
        </div>
    </div>

    {{-- Post list — same as Feed --}}
    <div class="flex-1 min-h-0 overflow-y-auto space-y-4 [&::-webkit-scrollbar]:hidden [-ms-overflow-style:none] [scrollbar-width:none]">
        @forelse ($this->myActivePosts as $post)
            <x-post-card :post="$post" wire:key="my-post-{{ $post->id }}">
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
                <flux:icon name="document-text" class="w-8 h-8 mx-auto text-light-txt-muted dark:text-dark-txt-muted mb-2" />
                <x-text variant="subtle" class="!font-secondary block" style="font-size: var(--text-table-row)">
                    No posts yet.
                </x-text>
                <x-text variant="subtle" class="!font-secondary block mt-1" style="font-size: var(--text-timestamp)">
                    Create your first post from the Feed.
                </x-text>
            </x-card>
        @endforelse
    </div>

    {{-- Uninterest confirmation modal — was missing before, referenced by uninterested() but never rendered --}}
    <flux:modal wire:model="show_delete_interested_modal" :closable="false" class="w-full max-w-xs sm:max-w-sm md:max-w-md lg:min-w-96">
        @if ($this->selected_post)
            <livewire:pages::post-delete-interest-modal
                :selected_post="$selected_post"
                :key="'delete-my-posts-' . $selected_post->id"
            />
        @endif
    </flux:modal>
</div>
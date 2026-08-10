<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use App\Models\Post;
use App\Models\RentalOffer;
use App\Models\TripRequest; 

new class extends Component
{  

    public $selectedPostId = null;

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
    public function archivePost($postId)
    {
        $post = Post::findOrFail($postId);

        if ($post->user_id === auth()->id()) {
            $post->update(['status' => 'archived']);
        }
    }
    #[Computed]
    public function getArchivedPost() {
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
    <div class="flex items-center justify-between mb-4">
        <flux:heading size="xl" > My Posts </flux:heading>
        <flux:breadcrumbs>
            <flux:breadcrumbs.item href="{{ route('feed') }}" wire:navigate>Feed</flux:breadcrumbs.item>
            <flux:breadcrumbs.item href="{{ route('post.archived') }}" wire:navigate>Archived Posts</flux:breadcrumbs.item>
        </flux:breadcrumbs>
    </div>

    <div>
        <div class="flex-1 min-h-0 overflow-y-auto space-y-4 [&::-webkit-scrollbar]:hidden [-ms-overflow-style:none] [scrollbar-width:none]">
            @forelse ($this->getArchivedPost as $post)
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
                <flux:card>
                    <x-text size="sm" class="text-zinc-500 text-center block py-6">
                        You have no posts yet.
                    </x-text>
                </flux:card>
            @endforelse
        </div>
    </div>
</div>
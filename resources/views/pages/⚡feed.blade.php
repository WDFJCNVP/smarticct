<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Validate;
use Livewire\Attributes\On;
use App\Models\Post;
use App\Models\RentalOffer;
use App\Models\TripRequest;

use App\Events\CreateNewPostEvent;

use App\Services\PostService;

new class extends Component
{
    public ?int $selectedPostId = null;
    public $selected_post;
    public bool $show_trip_request_modal = false;
    public bool $show_delete_interested_modal = false;

    public $replies;
    public bool $showRepliesModal = false;

    public bool $rental_offer_modal = false;


    #[On('interest-deleted')]
    public function closeModals()
    {
        // 1. Close ALL possible modals first
        $this->show_delete_interested_modal = false;
        $this->show_trip_request_modal = false;
        $this->rental_offer_modal = false;
        
        // 2. Clear the selected post data so the form resets
        $this->selected_post = null;
        
        // 3. Refresh the feed
        unset($this->posts); 
    }

    // #[On('interest-deleted')]
    // public function closeDeleteModal()
    // {
    //     $this->show_delete_interested_modal = false;
        
    //     $this->selected_post = null;
        
    //     unset($this->posts); 
    // }

    #[On('echo:create-new-post-event,.CreateNewPostEvent')]
    #[On('new-post-created')]
    public function refreshFeed() {
        unset($this->posts); 
    }

    public function showRepliesToYouModal($interest_id) {

        $this->showRepliesModal = true;
        $this->replies = PostInterest::where('id', $interest_id)->first();
        
    }

    public function mount() {
        $this->name = auth()->user()->name;
        $this->phone_number = auth()->user()->phone_number;
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

    public function getPostInterested($postId)
    {
         $this->selectedPostId = null;
        $this->selectedPostId = $postId;
    }

    #[On('echo:notification-event,.NotificationEvent')]
    public function reloadNotification() {
        $this->selected_post = null;
        $this->interested_operator_modal = false;
    }

    public function rentalOfferModal($postId) {
        $this->selected_post = null;
        $this->rental_offer_modal = false;

        $this->selected_post = Post::with('user')->find($postId);
        $this->rental_offer_modal = true;
    }

    public function tripRequest($postId)
    {   
        $this->selected_post = null;
        $this->show_delete_interested_modal = false;

        $this->selected_post = Post::with('user')->find($postId);
        $this->show_trip_request_modal = true;

    }

    // public function commuterUninterested($postId)
    // {
    //     $this->selected_post = null;
    //     $this->show_interested_modal = false;

    //     $this->selected_post = TripRequest::where('user_id', auth()->id())
    //             ->where('post_id', $postId)
    //             ->first();

    //     $this->show_delete_interested_modal = true;
    // }

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

    public function markAsRented($postId)
    {
        $post = Post::findOrFail($postId);

        if ($post->user_id === auth()->id() && $post->type === 'rental') {
            $post->update(['status' => 'rented']);
        }
    }

    public function archivePost($postId)
    {
        $post = Post::findOrFail($postId);

        if ($post->user_id === auth()->id()) {
            $post->update(['status' => 'archived']);
        }
    }

    public function restorePost($postId)
    {
        $post = Post::findOrFail($postId);

        if ($post->user_id === auth()->id()) {
            $post->update(['status' => 'published']);
        }
    }

    #[Computed]
    public function posts()
    {
        return Post::with('user', 'tripRequest', 'rentalOffer')
            ->whereIn('status', ['published', 'rented'])
            ->withCount(['tripRequest' => function ($query) {
                $query->whereIn('status',['pending', 'cancel']);
            }])
            ->withCount(['rentalOffer' => function ($query) {
                $query->whereIn('status',['pending', 'cancel']);
            }])
            ->latest()
            ->get();
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
    public function myInterests()
    {
        return PostInterest::with('post.user')
            ->where('user_id', auth()->id())
            ->latest()
            ->get();
    }

    #[Computed]
    public function activeInterests()
    {
        if (!$this->selectedPostId) {
            return collect();
        }

        $post = Post::find($this->selectedPostId);
        if (!$post || $post->user_id !== auth()->id()) {
            return collect();
        }

        return PostInterest::with('user', 'post')
            ->where('post_id', $this->selectedPostId)
            ->where(function ($query) {
                $query->where('status', '!=', 'decline')
                    ->orWhereNull('status');
            })
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
    <div>
        <div class="flex items-center justify-between mb-4">
            <flux:heading size="xl" > Feed </flux:heading>
            <flux:breadcrumbs>
                <flux:breadcrumbs.item href="{{ route('feed') }}" wire:navigate>Feed</flux:breadcrumbs.item>
                <flux:breadcrumbs.item href="{{ route('post.archived') }}" wire:navigate>Archived Post</flux:breadcrumbs.item>
                <flux:breadcrumbs.item href="{{ route('post.my-posts') }}" wire:navigate>My Posts</flux:breadcrumbs.item>
            </flux:breadcrumbs>
        </div>
        

        <div class="lg:col-span-7 flex flex-col gap-4 lg:h-[90vh]">
            <div class="shrink-0">

                <livewire:pages::create-post />

            </div>

            <div class="flex-1 min-h-0 overflow-y-auto space-y-4 [&::-webkit-scrollbar]:hidden [-ms-overflow-style:none] [scrollbar-width:none]">
                @forelse ($this->posts as $post)
                    <x-post-card :post="$post" wire:key="post-{{ $post->id }}">
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
                            No posts yet. Check back soon!
                        </x-text>
                    </flux:card>
                @endforelse
            </div>

        </div>
    </div>

    {{-- Modals --}}

    <flux:modal wire:model="rental_offer_modal" class="min-w-196">
        @if ($this->selected_post)
            <livewire:pages::interested-operator-modal 
                :selected_post="$selected_post" 
                :key="'view-operator-' . $selected_post->id" 
        />
        @endif
    </flux:modal>

    <flux:modal wire:model="show_trip_request_modal" class="min-w-196">
        @if ($this->selected_post)
            <livewire:pages::trip-request-modal 
                :selected_post="$selected_post" 
                :key="'view-' . $selected_post->id" 
                :name="auth()->user()->name" 
                :phone_number="auth()->user()->phone_number" 
                :email_address="auth()->user()->email_address" 
                :address="auth()->user()->address" 
        />
        @endif
    </flux:modal>

    <flux:modal wire:model="show_delete_interested_modal" class="min-w-96">
        @if ($this->selected_post)
            <livewire:pages::post-delete-interest-modal 
                :selected_post="$selected_post" 
                :key="'delete-' . $selected_post->id" 
        />
        @endif
    </flux:modal>

</div>
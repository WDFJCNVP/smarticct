<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Validate;
use App\Models\Post;
use App\Models\PostInterest;

use App\Services\PostService;

new class extends Component
{
    public ?int $selectedPostId = null;
    public $selected_post;
    public bool $show_interested_modal = false;
    public bool $show_delete_interested_modal = false;

    // public function saveInterest() {

    //     $validated_attributes = $this->validate();
        
    //     app(PostService::class)->saveInterestedUser([
    //         ''
    //     ]);
    // }

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
        $this->selectedPostId = $postId;
    }

    public function interested($postId)
    {   
        $this->selected_post = null;
        $this->show_delete_interested_modal = false;

        $this->selected_post = Post::with('user')->find($postId);
        $this->show_interested_modal = true;

    }

    public function uninterested($postId)
    {
        $this->selected_post = null;
        $this->show_interested_modal = false;

        $this->selected_post = PostInterest::where('user_id', auth()->id())
                ->where('post_id', $postId)
                ->first();

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
        return Post::with('user')
            ->whereIn('status', ['published', 'rented'])
            ->withCount('postInterest')
            ->latest()
            ->get();
    }

    #[Computed]
    public function myInterestedPostIds()
    {
        return PostInterest::where('user_id', auth()->id())
            ->pluck('post_id')
            ->all();
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
    <div class="grid grid-cols-1 lg:grid-cols-10 gap-6 items-start">
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
                                'alreadyInterested' => $alreadyInterested
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

        <div class="lg:col-span-3 lg:h-[90vh] overflow-y-auto [&::-webkit-scrollbar]:hidden [-ms-overflow-style:none] [scrollbar-width:none]" x-data="{ tab: 'mine' }">

            <flux:card class="sticky top-0">
                <div class="flex gap-1 p-0.5 rounded-lg bg-zinc-100 dark:bg-zinc-800">
                    <button
                        type="button"
                        @click="tab = 'mine'"
                        :class="tab === 'mine' ? 'bg-white dark:bg-zinc-700 shadow-sm' : 'text-zinc-500'"
                        class="flex-1 text-sm font-medium py-1.5 rounded-md cursor-pointer transition-colors"
                    >
                        Your interests
                    </button>
                    <button
                        type="button"
                        @click="tab = 'replies'"
                        :class="tab === 'replies' ? 'bg-white dark:bg-zinc-700 shadow-sm' : 'text-zinc-500'"
                        class="flex-1 text-sm font-medium py-1.5 rounded-md cursor-pointer transition-colors"
                    >
                        Replies to you
                    </button>
                </div>

                <div x-show="tab === 'mine'" class="mt-3">
                    @if ($this->myInterests->isNotEmpty())
                        @foreach ($this->myInterests as $interest)
                            @php $post = $interest->post; @endphp
                            <div wire:key="my-interest-{{ $interest->id }}">
                                @if (!$loop->first)
                                    <flux:separator class="my-3" />
                                @endif
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <flux:avatar size="xs" name="{{ $post->user->name }}" />
                                        <x-text class="text-sm font-medium">{{ $post->user->name }}</x-text>
                                    </div>
                                    <flux:button icon="x-mark" variant="ghost" size="sm" wire:click="uninterested({{ $post->id }})" />
                                </div>
                                <div class="mt-1.5">
                                    @if ($post->type === 'announcement')
                                        <flux:badge size="sm" color="zinc">Announcement</flux:badge>
                                    @elseif ($post->status === 'rented')
                                        <flux:badge size="sm" color="amber">Rented</flux:badge>
                                    @elseif ($post->user->role === 'commuter')
                                        <flux:badge size="sm" color="blue">Looking for a ride</flux:badge>
                                    @else
                                        <flux:badge size="sm" color="green">Available to rent</flux:badge>
                                    @endif
                                </div>
                                <x-text class="text-xs text-zinc-500 mt-1.5 block">
                                    {{ $post->type === 'rental' ? ($post->metadata['vehicle_type'] ?? 'Vehicle') : \Illuminate\Support\Str::limit($post->body, 60) }}
                                </x-text>
                                <x-text class="text-xs text-zinc-900 dark:text-white mt-1.5 flex items-center gap-1">
                                    <flux:icon.phone class="size-3.5" />{{ $post->user->phone_number }}
                                </x-text>
                                <x-text class="text-xs text-zinc-400 mt-1">{{ $interest->created_at->diffForHumans(['short' => true]) }}</x-text>
                            </div>
                        @endforeach
                    @else
                        <x-text size="sm" class="text-zinc-500">You haven't expressed interest in anything yet.</x-text>
                    @endif
                </div>
                <div x-show="tab === 'replies'" x-cloak class="mt-3">
                    @if ($this->selectedPostId && $this->activeInterests->isNotEmpty())
                        @foreach ($this->activeInterests as $item)
                            <div wire:key="interest-{{ $item->id }}">
                                @if (!$loop->first)
                                    <flux:separator class="my-3" />
                                @endif
                                <div class="flex items-center gap-2">
                                    <flux:avatar size="xs" name="{{ $item->user->name }}" color="emerald"/>
                                    <x-text class="text-sm font-medium">{{ $item->user->name }}</x-text>
                                </div>
                                <x-text class="text-xs text-zinc-900 dark:text-white mt-1.5 flex items-center gap-1">
                                    <flux:icon.phone class="size-3.5" />{{ $item->user->phone_number }}
                                </x-text>
                                <x-text class="text-xs text-zinc-400 mt-1">{{ $item->created_at->diffForHumans(['short' => true]) }}</x-text>
                            </div>
                        @endforeach
                    @else
                        <x-text size="sm" class="text-zinc-500">Select "View interested" on one of your posts to see who replied.</x-text>
                    @endif
                </div>
            </flux:card>

        </div>
    </div>

    {{-- Modals --}}

    <flux:modal wire:model="show_interested_modal" class="min-w-196">
        @if ($this->selected_post)
            <livewire:pages::post-interest-modal 
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
<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\Attributes\Computed;
use App\Models\Post;
use App\Models\PostInterest;

new class extends Component
{
    public ?int $selectedPostId = null;

    public function canExpressInterest(Post $post): bool
    {
        $user = auth()->user();

        // You can never express interest in your own post.
        if ($user->id === $post->user_id) {
            return false;
        }

        if ($post->status !== 'published') {
            return false;
        }

        // Announcements: only admin and cashier can flag one for follow-up,
        // regardless of which role originally posted it.
        if ($post->type === 'announcement') {
            return in_array($user->role, ['admin', 'cashier']);
        }

        // Rentals: interest only flows across the operator/commuter pair.
        // An operator can express interest in a commuter's request, and a
        // commuter can express interest in an operator's listing. Same-role
        // interest (operator -> operator, commuter -> commuter) is blocked,
        // and admin/cashier never see this action on rentals.
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
        $post = Post::with('user')->findOrFail($postId);

        if (! $this->canExpressInterest($post)) {
            return;
        }

        PostInterest::firstOrCreate([
            'user_id' => auth()->id(),
            'post_id' => $postId,
        ]);
    }

    public function uninterested($postId)
    {
        PostInterest::where('user_id', auth()->id())
            ->where('post_id', $postId)
            ->delete();

        if ($this->selectedPostId === $postId) {
            unset($this->activeInterests);
        }
    }

    public function markAsRented($postId)
    {
        $post = Post::findOrFail($postId);

        // Only rental listings can move to "rented" — an announcement has
        // no such state.
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
                    @php
                        $isOwner = $post->user_id === auth()->id();
                        $isAnnouncement = $post->type === 'announcement';
                        $alreadyInterested = in_array($post->id, $this->myInterestedPostIds);

                        // A rental post reads differently depending on who
                        // posted it: an operator is offering a vehicle, a
                        // commuter is requesting one. Naming that plainly
                        // is what tells the reader whether to expect a
                        // vehicle or a rider on the other end.
                        $statusLabel = match(true) {
                            $post->status === 'rented' => 'Rented',
                            $post->status === 'archived' => 'Archived',
                            $post->status === 'published' && $post->user->role === 'commuter' => 'Looking for a ride',
                            $post->status === 'published' => 'Available to rent',
                            default => ucfirst($post->status),
                        };
                        $statusColor = match(true) {
                            $post->status === 'rented' => 'amber',
                            $post->status === 'archived' => 'zinc',
                            $post->status === 'published' && $post->user->role === 'commuter' => 'blue',
                            $post->status === 'published' => 'green',
                            default => 'zinc',
                        };

                        // Copy differs by post type: reacting to a listing
                        // is "interest", reacting to an announcement is a
                        // staff follow-up action.
                        $actionIcon = $isAnnouncement ? 'flag' : 'check-circle';
                        $actionLabelDone = $isAnnouncement ? 'Following up' : "You're interested";
                        $actionLabelIdle = $isAnnouncement ? 'Mark for follow-up' : "I'm interested";
                        $countLabel = $isAnnouncement ? 'following up' : 'interested';
                    @endphp

                    <flux:card wire:key="post-{{ $post->id }}">
                        <div class="flex items-start justify-between">
                            <div class="flex items-center gap-3">
                                <flux:avatar size="sm" name="{{ $post->user->name }}" />
                                <div>
                                    <x-text size="lg" variant="strong">{{ $post->user->name }}</x-text>
                                    <div class="flex items-center gap-2 text-xs text-zinc-500">
                                        <x-text size="sm" variant="subtle">{{ $post->created_at->diffForHumans(['short' => true]) }}</x-text>
                                    </div>
                                </div>
                            </div>

                            @if ($isOwner)
                                <flux:dropdown>
                                    <flux:button variant="ghost" size="sm" icon="ellipsis-vertical" inset="top bottom" />
                                    <flux:menu>
                                        @if ($post->type === 'rental' && $post->status !== 'rented')
                                            <flux:menu.item icon="check-circle" wire:click="markAsRented({{ $post->id }})">
                                                Mark as rented
                                            </flux:menu.item>
                                        @endif

                                        @if ($post->status === 'archived')
                                            <flux:menu.item icon="arrow-path" wire:click="restorePost({{ $post->id }})">
                                                Restore listing
                                            </flux:menu.item>
                                        @else
                                            <flux:menu.item icon="archive-box" variant="danger" wire:click="archivePost({{ $post->id }})">
                                                Archive listing
                                            </flux:menu.item>
                                        @endif
                                    </flux:menu>
                                </flux:dropdown>
                            @endif
                        </div>

                        <div class="flex items-center gap-2 mt-3">
                            @if ($post->type === 'rental')
                                <flux:badge size="sm" color="{{ $statusColor }}">{{ $statusLabel }}</flux:badge>
                            @else
                                <flux:badge size="sm" color="zinc">Announcement</flux:badge>
                            @endif

                            @if(!empty($post->metadata['vehicle_type']))
                                <flux:badge size="sm" color="blue">{{ $post->metadata['vehicle_type'] }}</flux:badge>
                            @endif
                        </div>

                        <x-text size="lg" class="mt-3 block leading-relaxed" variant="strong">
                            {{ $post->body }}
                        </x-text>

                        @if (!empty($post->metadata['attachments']))
                            @php
                                $attachments = $post->metadata['attachments'];
                                $urls = array_map(fn($path) => Storage::url($path), $attachments);
                            @endphp

                            <div x-data="{ open: false, index: 0, images: @js($urls) }" class="mt-3">
                                <div class="grid grid-cols-3 gap-1.5 auto-rows-[110px]">
                                    @foreach ($urls as $i => $url)
                                        @if ($i === 0)
                                            <button type="button" @click="open = true; index = {{ $i }}" class="col-span-2 row-span-2 relative rounded-lg overflow-hidden bg-zinc-100 dark:bg-zinc-800 group cursor-pointer">
                                                <img src="{{ $url }}" alt="Vehicle attachment image" class="w-full h-full object-cover" loading="lazy" />
                                                <div class="absolute inset-0 bg-black/0 group-hover:bg-black/25 transition-colors flex items-center justify-center">
                                                    <flux:icon.magnifying-glass-plus class="size-5 text-white opacity-0 group-hover:opacity-100 transition-opacity" />
                                                </div>
                                            </button>
                                        @elseif ($i < 3)
                                            <button type="button" @click="open = true; index = {{ $i }}" class="relative rounded-lg overflow-hidden bg-zinc-100 dark:bg-zinc-800 group cursor-pointer">
                                                <img src="{{ $url }}" alt="Vehicle attachment image" class="w-full h-full object-cover" loading="lazy" />
                                                @if ($i === 2 && count($urls) > 3)
                                                    <div class="absolute inset-0 bg-black/45 flex items-center justify-center text-white text-sm font-medium">
                                                        +{{ count($urls) - 3 }}
                                                    </div>
                                                @else
                                                    <div class="absolute inset-0 bg-black/0 group-hover:bg-black/25 transition-colors flex items-center justify-center">
                                                        <flux:icon.magnifying-glass-plus class="size-5 text-white opacity-0 group-hover:opacity-100 transition-opacity" />
                                                    </div>
                                                @endif
                                            </button>
                                        @endif
                                    @endforeach
                                </div>
                                <div
                                    x-show="open"
                                    x-cloak
                                    class="fixed inset-0 z-50 bg-black/70 flex items-center justify-center p-6"
                                    @keydown.escape.window="open = false"
                                    >
                                    <div @click.outside="open = false" class="bg-white dark:bg-zinc-900 rounded-xl overflow-hidden max-w-lg w-full">
                                        <div class="flex items-center justify-between px-4 py-2.5 border-b border-zinc-200 dark:border-zinc-700">
                                            <span class="text-sm text-zinc-500" x-text="(index + 1) + ' / ' + images.length"></span>
                                            <button @click="open = false" class="text-zinc-500 hover:text-zinc-900 dark:hover:text-white cursor-pointer">
                                                <flux:icon.x-mark class="size-5" />
                                            </button>
                                        </div>

                                        <div class="relative">
                                            <img :src="images[index]" class="w-full h-80 object-cover" alt="Vehicle attachment image, full size" />

                                            <button
                                                x-show="images.length > 1"
                                                @click="index = (index - 1 + images.length) % images.length"
                                                class="absolute left-2 top-1/2 -translate-y-1/2 bg-black/50 hover:bg-black/70 rounded-full size-8 flex items-center justify-center text-white cursor-pointer"
                                            >
                                                <flux:icon.chevron-left class="size-4" />
                                            </button>
                                            <button
                                                x-show="images.length > 1"
                                                @click="index = (index + 1) % images.length"
                                                class="absolute right-2 top-1/2 -translate-y-1/2 bg-black/50 hover:bg-black/70 rounded-full size-8 flex items-center justify-center text-white cursor-pointer"
                                            >
                                                <flux:icon.chevron-right class="size-4" />
                                            </button>
                                        </div>

                                        <div class="flex gap-1.5 p-3 overflow-x-auto" x-show="images.length > 1">
                                            <template x-for="(img, i) in images" :key="i">
                                                <button @click="index = i" class="shrink-0 cursor-pointer">
                                                    <img :src="img" class="w-12 h-9 object-cover rounded" :class="i === index ? 'ring-2 ring-blue-500' : 'opacity-60'" />
                                                </button>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif

                        <div class="mt-3 flex items-center justify-between border-t pt-3 border-zinc-200 dark:border-zinc-700">

                            @if ($isOwner && $post->post_interest_count > 0)
                                <span class="flex items-center gap-1.5 text-sm text-zinc-500">
                                    <flux:icon.users class="size-4" />
                                    {{ $post->post_interest_count }} {{ $countLabel }}
                                </span>
                            @else
                                <span></span>
                            @endif

                            <div class="flex items-center gap-2">

                                @if ($isOwner && $post->post_interest_count > 0)
                                    @if ($this->selectedPostId === $post->id)
                                        <x-button variant="ghost" class="cursor-pointer">Viewing</x-button>
                                    @else
                                        <x-button wire:click="getPostInterested({{ $post->id }})" class="cursor-pointer">View interested</x-button>
                                    @endif
                                @endif

                                @if ($this->canExpressInterest($post))
                                    @if ($alreadyInterested)
                                        <x-button
                                            icon="{{ $actionIcon }}"
                                            variant="primary"
                                            class="cursor-pointer"
                                            wire:click="uninterested({{ $post->id }})"
                                            wire:loading.attr="disabled"
                                        >
                                            {{ $actionLabelDone }}
                                        </x-button>
                                    @else
                                        <x-button
                                            icon="{{ $actionIcon }}"
                                            wire:click="interested({{ $post->id }})"
                                            class="cursor-pointer"
                                            wire:loading.attr="disabled"
                                        >
                                            {{ $actionLabelIdle }}
                                        </x-button>
                                    @endif
                                @elseif(!$isOwner && $post->type === 'rental' && $post->status === 'rented')
                                    <x-button disabled>Interested</x-button>
                                @endif
                            </div>
                        </div>
                    </flux:card>
                @empty
                    <flux:card>
                        <x-text size="sm" class="text-zinc-500 text-center block py-6">
                            No listings yet. Check back soon!
                        </x-text>
                    </flux:card>
                @endforelse
            </div>
        </div>

        <div class="lg:col-span-3 lg:h-[90vh] overflow-y-auto [&::-webkit-scrollbar]:hidden [-ms-overflow-style:none] [scrollbar-width:none]" x-data="{ tab: 'mine' }">

            {{-- One panel, two tabs — this is the fix for the old design
                 where "your interests" and "people interested in you" sat
                 in two separate boxes that only got more confusing as each
                 list grew. Only one list is ever on screen at a time. --}}
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

                {{-- Tab 1: what you're following — rentals you've marked
                     interested in (operator/commuter), or announcements
                     you've flagged for follow-up (admin/cashier).
                     canExpressInterest already restricts who can populate
                     this list, so no extra role check is needed here. --}}
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

                {{-- Tab 2: people interested in one of your own posts.
                     Ownership is already enforced in activeInterests(),
                     so this is safe to show to every role. --}}
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
</div>
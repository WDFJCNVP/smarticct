@props(['post'])

@php
    $isOwner = $post->user_id === auth()->id();
    $isAnnouncement = $post->type === 'announcement';

    $statusLabel = match(true) {
        $post->status === 'rented' => 'Not available',
        $post->status === 'archived' => 'Archived',
        $post->status === 'published' && $post->user->role === 'commuter' => 'Looking for a ride',
        $post->status === 'published' => 'Available to rent',
        default => ucfirst($post->status),
    };

    $statusColor = match(true) {
        $post->status === 'rented' => 'red',
        $post->status === 'archived' => 'zinc',
        $post->status === 'published' && $post->user->role === 'commuter' => 'green',
        $post->status === 'published' => 'green',
        default => 'zinc',
    };
@endphp

<flux:card>
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
                    @if ($post->status === 'archived')
                        <flux:menu.item icon="arrow-path" wire:click="restorePost({{ $post->id }})">
                            Unarchive Post
                        </flux:menu.item>
                    @else
                        <flux:menu.item icon="archive-box" variant="danger" wire:click="archivePost({{ $post->id }})">
                            Archive Post
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

        @if(!empty($post->metadata['from']) && !empty($post->metadata['to']))
            <flux:badge size="sm" color="yellow">
                {{ $post->metadata['from'] }}
                <flux:icon.arrow-right class="size-3.5 mx-1" />
                {{ $post->metadata['to'] }}
            </flux:badge>
        @endif

    </div>

    <x-text size="lg" class="mt-3 block leading-relaxed" variant="strong">
        {{ $post->body }}
    </x-text>

@if (!empty($post->metadata['attachments']))
        @php
            $urls = array_map(fn($path) => Storage::url($path), $post->metadata['attachments']);
            $count = count($urls);
        @endphp
        
        <div x-data="{ open: false, index: 0, images: @js($urls) }" class="mt-3">
            <div class="grid gap-1.5 
                {{ $count === 1 ? 'grid-cols-1 auto-rows-[226px]' : '' }}
                {{ $count === 2 ? 'grid-cols-2 auto-rows-[226px]' : '' }}
                {{ $count >= 3 ? 'grid-cols-3 auto-rows-[110px]' : '' }}
            ">
                @foreach ($urls as $i => $url)
                    @if ($i === 0)
                        <button type="button" @click="open = true; index = {{ $i }}" 
                            class="{{ $count >= 3 ? 'col-span-2 row-span-2' : '' }} relative rounded-lg overflow-hidden bg-zinc-100 dark:bg-zinc-800 group cursor-pointer">
                            <img src="{{ $url }}" alt="Vehicle attachment image" class="w-full h-full object-cover" loading="lazy" />
                            <div class="absolute inset-0 bg-black/0 group-hover:bg-black/25 transition-colors flex items-center justify-center">
                                <flux:icon.magnifying-glass-plus class="size-5 text-white opacity-0 group-hover:opacity-100 transition-opacity" />
                            </div>
                        </button>
                    @elseif ($i < 3)
                        <button type="button" @click="open = true; index = {{ $i }}" class="relative rounded-lg overflow-hidden bg-zinc-100 dark:bg-zinc-800 group cursor-pointer">
                            <img src="{{ $url }}" alt="Vehicle attachment image" class="w-full h-full object-cover" loading="lazy" />
                            @if ($i === 2 && $count > 3)
                                <div class="absolute inset-0 bg-black/45 flex items-center justify-center text-white text-sm font-medium">
                                    +{{ $count - 3 }}
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
        {{ $footer ?? '' }}
    </div>
</flux:card>
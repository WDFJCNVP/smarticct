<div>
    @if ($this->selectedPostId && $this->activeInterests->isNotEmpty())
        @foreach ($this->activeInterests as $item)
            <div wire:key="interest-{{ $item->id }}">
                @if (!$loop->first)
                    <flux:separator class="my-3" />
                @endif
                <div class="mb-4">
                    @if ($item->status === 'accept')
                        <flux:badge color="green" size="sm" >Accepted</flux:badge>
                    @elseif($item->status === 'cancel')
                        <flux:badge color="blue" size="sm" >Canceled</flux:badge>
                    @endif
                    
                </div>
                <div class="flex items-center gap-2">
                    <div class="flex-1 flex items-center gap-2">
                        <flux:avatar size="xs" name="{{ $item->user->name }}" color="yellow"/>
                        <div class="flex flex-col">
                            <x-text class="text-sm font-medium">{{ $item->user->name }}</x-text>
                            <x-text class="text-xs text-zinc-900 dark:text-white flex items-center gap-1">
                                {{ $item->user->phone_number }}
                            </x-text>
                        </div>
                    </div>

                    <x-text class="text-xs text-zinc-400">{{ $item->created_at->diffForHumans(['short' => true]) }}</x-text>

                </div>

                <flux:separator variant="subtle" class="my-2"/>

                <div class="flex gap-2">
                    <flux:icon.chat-bubble-bottom-center-text class="w-4 h-4" />
                    <x-text  size="sm" variant="strong" class="text-wrap">{{ $item->purpose }}</x-text>
                </div>

                <div class="flex flex-col">

                    <div class="mt-4 flex items-center">   
                        <div class="flex-1 flex items-center gap-1.5 text-zinc-500 dark:text-zinc-400">
                            <flux:icon.users class="w-4 h-4" />
                            <x-text size="sm" class="text-inherit">Total passenger/s</x-text>
                        </div>
                        <x-text size="sm" variant="strong">{{ $item->body_count }}</x-text>
                    </div>

                    <div class="mt-2 flex items-center">   
                        <div class="flex-1 flex items-center gap-1.5 text-zinc-500 dark:text-zinc-400">
                            <flux:icon.calendar-days class="w-4 h-4" />
                            <x-text size="sm" class="text-inherit">Trip date</x-text>
                        </div>
                        <x-text size="sm" variant="strong">{{ $item->trip_date->format('D, M j Y') }}</x-text>
                    </div>

                    <flux:separator class="my-2 flex" variant="subtle" />

                    <div>
                        @if ($item->trip_type === 'round_trip')
                            <flux:badge color="orange" size="sm" icon="arrows-right-left"> Round trip </flux:badge>
                        @else
                            <flux:badge color="orange" size="sm" icon="arrow-right"> One way </flux:badge>
                        @endif
                    </div>                        


                    <div class="mt-2 flex items-center">   
                        <div class="flex-1 flex items-center gap-1.5 text-zinc-500 dark:text-zinc-400">
                            <flux:icon.map-pin class="w-4 h-4" />
                            <x-text size="sm" class="text-inherit">From</x-text>
                        </div>
                        <x-text size="sm" variant="strong">{{ $item->pick_up_location }}</x-text>
                    </div>

                    <div class="mt-2 flex items-center">   
                        <div class="flex-1 flex items-center gap-1.5 text-zinc-500 dark:text-zinc-400">
                            <flux:icon.flag class="w-4 h-4" />
                            <x-text size="sm" class="text-inherit">To</x-text>
                        </div>
                        <x-text size="sm" variant="strong">{{ $item->drop_off_location }}</x-text>
                    </div>

                </div>

                <div class="mt-4 w-full">
                    <x-button wire:click="showRepliesToYouModal({{ $item->id }})" class="w-full cursor-pointer">View more</x-button>
                </div>

            </div>
        @endforeach
    @else
        <x-text size="sm" class="text-zinc-500">Select "View interested" on one of your posts to see who replied.</x-text>
    @endif
</div>

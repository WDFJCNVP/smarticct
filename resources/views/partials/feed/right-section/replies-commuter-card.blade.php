<div>
    @if ($this->activeInterests->isNotEmpty())

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
                    <x-text variant="strong" class="text-wrap">{{ $item->purpose }}</x-text>
                </div>

                <livewire:pages::post-commuter-accept-decline 

                    :this_operator="$item"
                    :key="$item->id"

                />

                {{-- <div class="flex items-center mt-3 gap-2">
                    <x-button icon="x-mark" color="red" variant="primary" />
                    <x-button icon="check" color="green" variant="primary" />
                </div> --}}

            </div>
        @endforeach
    @else
        <x-text size="sm" class="text-zinc-500">Select "View interested" on one of your posts to see who replied.</x-text>
    @endif
</div>

<div>
    <div>
    @if ($this->myInterests->isNotEmpty())
        @foreach ($this->myInterests as $interest)
            @php $post = $interest->post; @endphp
            <div wire:key="my-interest-{{ $interest->id }}">
                @if (!$loop->first)
                    <flux:separator class="my-3" />
                @endif
                <div class="mb-4">
                    @if ($interest->status === 'decline')
                        <flux:badge size="sm" color="red">Declined</flux:badge>
                    @endif
                </div>

                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <flux:avatar size="sm" name="{{ $post->user->name }}" />
                        <div class="flex flex-col items-start">
                            <x-text class="text-sm font-medium">{{ $post->user->name }}</x-text>
                            <x-text class="text-xs text-zinc-400">{{ $post->user->phone_number }}</x-text>
                        </div>
                    </div>
                    <flux:button icon="x-mark" variant="ghost" size="sm" wire:click="uninterested({{ $post->id }})" />
                </div>

                <div class="mt-1.5 gap-1 flex items-center">
                    @if ($post->type === 'announcement')
                        <flux:badge size="sm" color="zinc">Announcement</flux:badge>
                    @elseif ($post->status === 'rented')
                        <flux:badge size="sm" color="amber">Rented</flux:badge>
                    @elseif ($post->user->role === 'commuter')
                        <flux:badge size="sm" color="blue">Looking for a ride</flux:badge>
                    @else
                        <flux:badge size="sm" color="green">Available to rent</flux:badge>
                    @endif

                    @if ($post->type === 'rental')
                        <flux:badge size="sm" color="blue">{{$post->metadata['vehicle_type']}}</flux:badge>
                    @endif
                </div>

                <div class="mt-2">
                    <x-text class="text-xs text-zinc-400">{{ $interest->created_at->diffForHumans(['short' => true]) }}</x-text>
                </div>

                <flux:separator class="my-2" variant="subtle" />

                <div>
                    <x-text variant="strong">Your request:</x-text>

                    <div class="mt-1.5 flex gap-1 items-center">
                        <flux:icon.chat-bubble-bottom-center-text class="w-4 h-4" />
                        <x-text variant="strong">{{ $interest->purpose }}</x-text>
                    </div>

                    <div class="mt-1.5 flex items-center">
                        <x-text class="flex-1">Total passenger/s</x-text>
                        <x-text variant="strong">{{ $interest->body_count }}</x-text>
                    </div>

                    <div class="mt-1.5 flex items-center">
                        @if ($interest->trip_type === 'round_trip')
                            <flux:badge color="orange" size="sm"> Round trip </flux:badge>
                        @else
                            <flux:badge color="orange" size="sm"> One way </flux:badge>
                        @endif
                    </div>

                    <div class="mt-1.5 flex items-center">
                        <x-text class="flex-1">From</x-text>
                        <x-text variant="strong">{{ $interest->pick_up_location }}</x-text>
                    </div>

                    <div class="mt-1.5 flex items-center">
                        <x-text class="flex-1">To</x-text>
                        <x-text variant="strong">{{ $interest->drop_off_location }}</x-text>
                    </div>

                </div>

            </div>
        @endforeach
    @else
        <x-text size="sm" class="text-zinc-500">You haven't expressed interest in anything yet.</x-text>
    @endif
</div>

</div>

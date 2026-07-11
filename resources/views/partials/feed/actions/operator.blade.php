@if ($isOwner && $post->post_interest_count > 0)
    <span class="flex items-center gap-1.5 text-sm text-zinc-500">
        <flux:icon.users class="size-4" />
        {{ $post->post_interest_count }} interested
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
            <x-button icon="check-circle" variant="primary" class="cursor-pointer" wire:click="uninterested({{ $post->id }})" wire:loading.attr="disabled">
                You're interested
            </x-button>
        @else
            <x-button icon="check-circle" wire:click="interested({{ $post->id }})" class="cursor-pointer" wire:loading.attr="disabled">
                I'm interested
            </x-button>
        @endif
    @elseif(!$isOwner && $post->type === 'rental' && $post->status === 'rented')
        <x-button disabled>Interested</x-button>
    @endif
</div>
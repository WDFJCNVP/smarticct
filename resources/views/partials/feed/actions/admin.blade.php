@if ($post->type === 'announcement')
    @if ($isOwner && $post->post_interest_count > 0)
        <span class="flex items-center gap-1.5 text-sm text-zinc-500">
            <flux:icon.users class="size-4" />
            {{ $post->post_interest_count }} following up
        </span>
    @else
        <span></span>
    @endif

    <div class="flex items-center gap-2">
        @if ($this->canExpressInterest($post))
            @if ($alreadyInterested)
                <x-button icon="flag" variant="primary" class="cursor-pointer" wire:click="uninterested({{ $post->id }})">
                    Following up
                </x-button>
            @else
                <x-button icon="flag" class="cursor-pointer" wire:click="interested({{ $post->id }})">
                    Mark for follow-up
                </x-button>
            @endif
        @endif
    </div>
@else
    <span></span>
    <span></span>
@endif
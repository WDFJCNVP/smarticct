@if ($isOwner)
    <span class="flex items-center gap-1.5 text-sm text-zinc-500">
        <flux:icon.users class="size-4" />
        {{ $post->post_interest_count }} interested
    </span>
@else
    <span></span>
@endif

<div class="flex items-center gap-2">
    @if ($isOwner)
        @if ($this->selectedPostId === $post->id)
            <x-button variant="ghost" class="cursor-pointer">Viewing</x-button>
        @else

            <x-button href="{{ route('operator.post', [$post, 'post_interest_count' => $post->post_interest_count]) }}"  class="cursor-pointer" wire:navigate>View post</x-button>
        @endif
    @endif
</div>
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
        <span></span>
    </div>
@else
    <span></span>
    <span></span>
@endif
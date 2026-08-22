@props([
    'count'       => 0,
    'heading'     => '',
    'description' => '',
    ])

<div>
    <div>
        <flux:heading {{ $attributes->merge([]) }} size="xl">{{ $heading }}</flux:heading>
        @if ($description)
            <flux:text class="mt-1 mb-4">{{ $description }}</flux:text>
        @endif
    </div>

    {{-- Only rendered when the component is actually given slot content (e.g. a page
         with a sub-heading/count next to it). Previously this rendered unconditionally,
         which added an empty spaced heading below the description on pages like the
         Dashboard that don't use the slot — inflating the component's total height and
         breaking vertical-centering against anything placed next to it in a flex row. --}}
    @if ($slot->isNotEmpty())
        <div>
            <flux:heading size="lg" class="flex gap-2 items-center">
                {{ $slot }}
                @if ($count)
                    <flux:text class="text-base" size="2xl" variant="subtle">
                        {{ $count }}
                    </flux:text>
                @endif
            </flux:heading>
        </div>
    @endif
</div>
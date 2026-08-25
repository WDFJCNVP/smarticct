.{{--
    Reusable "real data" trend pill.
    Replaces the old pulsing green "Live" badge across the dashboards.
    Pass a signed percentage (float|null). Null = not enough history yet ("New").
--}}
@props(['value' => null, 'suffix' => null])
@php
    $isUp = $value !== null && $value > 0;
    $isDown = $value !== null && $value < 0;
@endphp
<span
    {{ $attributes->class([
        'inline-flex items-center gap-1 px-2 py-0.5 rounded-full font-secondary text-xs font-semibold whitespace-nowrap',
        'bg-success/10 text-success dark:bg-dark-success/15 dark:text-dark-success' => $isUp,
        'bg-danger/10 text-danger dark:bg-dark-danger/15 dark:text-dark-danger' => $isDown,
        'bg-light-subtle text-light-txt-muted dark:bg-dark-subtle dark:text-dark-txt-muted' => $value === null || (!$isUp && !$isDown),
    ]) }}
>
    @if ($value === null)
        <span>New</span>
    @else
        @if ($isUp)
            <flux:icon.arrow-trending-up class="w-3 h-3 shrink-0" />
        @elseif ($isDown)
            <flux:icon.arrow-trending-down class="w-3 h-3 shrink-0" />
        @else
            <flux:icon.minus class="w-3 h-3 shrink-0" />
        @endif
        <span>{{ ($isUp ? '+' : '') . number_format($value, 1) }}%</span>
    @endif
    @if ($suffix)
        <span class="font-normal opacity-70">{{ $suffix }}</span>
    @endif
</span>

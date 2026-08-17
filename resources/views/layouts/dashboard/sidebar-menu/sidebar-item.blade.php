@props([
    'icon',
    'badge' => null,
])

<flux:sidebar.item
    icon="{{ $icon }}"
    {{ $attributes->merge(['href' => $attributes->get('href')]) }}
>
    {{ $slot }}
</flux:sidebar.item>
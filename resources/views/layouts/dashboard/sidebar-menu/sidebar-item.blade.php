@props([
   'icon',
   'badge' => null
])

<flux:sidebar.item icon={{$icon}} href="{{ $attributes->get('href') }}"> {{ $slot }} </flux:sidebar.item>
@props([
   'icon',
   'badge' => null
])


   <flux:sidebar.item icon={{$icon}} href="{{ $attributes->get('href') }}" :badge="$badge" wire:navigate>
         {{ $slot }} 
   </flux:sidebar.item>




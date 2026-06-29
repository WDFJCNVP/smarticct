@props([
   'icon',
   'badge' => null
])


   <flux:sidebar.item icon={{$icon}} href="{{ $attributes->get('href') }}" wire:navigate>
         {{ $slot }} 
   </flux:sidebar.item>




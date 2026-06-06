@props([
   'icon',
   'badge' => null
])


<flux:link href="{{ $attributes->get('href') }}" wire:navigate>
   <flux:sidebar.item icon={{$icon}}>
         {{ $slot }} 
   </flux:sidebar.item>
</flux:link>



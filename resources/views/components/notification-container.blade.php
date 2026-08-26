@props([
    'notification_id' => null,
    'href' => '#',
    ])

<x-card 
    class="mb-1.5 cursor-pointer hover:bg-light-subtle dark:hover:bg-dark-subtle transition-colors duration-150" 
    wire:key="{{ $notification_id }}" 
    >
    <flux:link href="{{ $href }}" class="!no-underline hover:!no-underline" wire:navigate>
        <div class="flex items-start gap-6 ">

            {{ $slot }}

        </div>
    </flux:link>
</x-card>
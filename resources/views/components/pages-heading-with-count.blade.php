@props([
    'count'       => 0,
    'heading'     => '',
    'description' => '',
    ])


<div>
    <div class="mt-4">
        <flux:heading size="xl">{{ $heading }}</flux:heading>
        <flux:text class="mt-2">{{ $description }}</flux:text>
    </div>
    <div>
        <flux:heading size="lg" class="mt-10 mb-2 flex gap-2 items-center">
           {{ $slot }}
            <flux:text class="text-base" size="2xl" variant="subtle">
                {{ $count ? $count : '' }}
            </flux:text>
        </flux:heading>
    </div>
</div>

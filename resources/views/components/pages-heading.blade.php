@props([
    'count'       => 0,
    'heading'     => '',
    'description' => '',
    ])


<div>
    <div>
        <flux:heading  {{ $attributes->merge([]) }} size="xl">{{ $heading }}</flux:heading>
        <flux:text class="mt-1 mb-4">{{ $description }}</flux:text>
    </div>
    <div>
        <flux:heading size="lg" class=" mb-2 flex gap-2 items-center">
           {{ $slot }}
            <flux:text class="text-base" size="2xl" variant="subtle">
                {{ $count ? $count : '' }}
            </flux:text>
        </flux:heading>
    </div>
</div>

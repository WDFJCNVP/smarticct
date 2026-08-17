@php
    // Automatically extract the field name from wire:model, wire:model.live, etc., or the 'name' attribute
    $name = $attributes->whereStartsWith('wire:model')->first() ?? $attributes->get('name');
@endphp

<flux:field>
    <flux:input {{ $attributes->merge([
        'class' => 'w-full',
        'size' => 'lg',
    ]) }} />

    {{-- @if ($name)
        <flux:error :name="$name" />
    @endif --}}
</flux:field>
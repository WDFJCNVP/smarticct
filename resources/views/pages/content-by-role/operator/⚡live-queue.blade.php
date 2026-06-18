<?php

use Livewire\Component;
use Livewire\Attributes\Layout;

new #[Layout('layouts.operator-layout')] class extends Component
{
    //
};
?>

<div>
   {{-- <x-pages-heading heading="Live Queue" /> --}}

    {{-- <div class="flex items-center justify-end mb-8">
        <x-button size="sm" class="cursor-pointer" href="{{ route('operator.queued.vehicle') }}" wire:navigate>
            My queued vehicle
        </x-button>
    </div> --}}

    <div>
        <livewire-pages::queue-page />
    </div>
</div>
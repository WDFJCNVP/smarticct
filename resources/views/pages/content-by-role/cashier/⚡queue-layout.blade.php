<?php

use Livewire\Component;
use Livewire\Attributes\Layout;

new #[Layout('layouts.queue-layout')] class extends Component
{
    //
};
?>

<div>
    <div class="flex items-center justify-end my-6 gap-3">

        <x-button href="{{ route('cashier.queue.vehicle') }}" size="sm" icon="plus" class="cursor-pointer" wire:navigate>
            Queue Vehicle
        </x-button>

        <x-button href="{{ route('cashier.active-group') }}" size="sm" class="cursor-pointer" wire:navigate variant="primary">
            Active Group
        </x-button>

    </div>

    <livewire:pages::queue-page />

</div>
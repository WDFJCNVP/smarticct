<?php

use Livewire\Component;
use Livewire\Attributes\Layout;

new #[Layout('layouts.queue-layout')] class extends Component
{   

    public bool $isQueueFormRender = false;
    public bool $isCurrentGroupRender = false;

    public function renderQueueForm() {
        $this->isQueueFormRender === false ? $this->isQueueFormRender = true : $this->isQueueFormRender = false;
    }

    public function renderCurrentActiveGroup() {
        $this->isCurrentGroupRender === false ? $this->isCurrentGroupRender = true : $this->isCurrentGroupRender = false;
    }
};
?>

<div>
    {{-- <div class="flex items-center justify-end my-6 gap-3">    
           
        <x-button href=" {{ route('cashier.queue.vehicle') }}" size="sm" icon="plus" class="cursor-pointer" wire:navigate>
            Queue Vehicle
        </x-button>

        <x-button href="{{ route('cashier.active-group') }}" size="sm"  class="cursor-pointer" wire:navigate variant="primary">
            Active Group
        </x-button>

    </div>

    @if ($this->isQueueFormRender)
        <livewire-pages::content-by-role.cashier.queue-vehicle />

    @elseif ($this->isCurrentGroupRender)
        <livewire-pages::content-by-role.cashier.current_active_group />
    @endif

    <livewire-pages::queue-page /> --}}

</div>
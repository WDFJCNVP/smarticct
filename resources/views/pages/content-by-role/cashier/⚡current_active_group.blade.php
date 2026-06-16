<?php

use Livewire\Component;
use Livewire\Attributes\Computed;

use App\Models\DailyScheduleSlot;

new class extends Component
{
    #[Computed]
    public function renderCurrentActiveGroup() {
        return DailyScheduleSlot::with('vehicle')->where('schedule_date', today())->get();
    }
};
?>

<div>
    <flux:table>
        <flux:table.columns sticky class="bg-white dark:bg-zinc-900">
            <flux:table.column>No</flux:table.column>
            <flux:table.column>Plate No.</flux:table.column>
            <flux:table.column>Status</flux:table.column>
        </flux:table.columns>

        <flux:table.rows sticky class="bg-white dark:bg-zinc-900">

            @foreach($this->renderCurrentActiveGroup as $vehicle)
                <flux:table.row>
                    <flux:table.cell class="text-zinc-400 text-xs">
                        {{ $vehicle->slot_position }}
                    </flux:table.cell>
                    <flux:table.cell class="text-zinc-400 text-xs">
                        {{ $vehicle->vehicle->plate_number }}
                    </flux:table.cell>
                    <flux:table.cell class="text-zinc-400 text-xs">
                        {{ $vehicle->status }}
                    </flux:table.cell>
                </flux:table.row>
            @endforeach

        </flux:table.rows>
    </flux:table>
</div>
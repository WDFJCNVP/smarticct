<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Livewire\WithPagination;

use App\Events\UserInfoUpdated;
use Livewire\Attributes\On;

use App\Models\Vehicle;
use App\Models\User;

new #[Layout('layouts.operator-layout')]class extends Component
{

    #[Computed]
    public function vehicles() {
        return Vehicle::with(['route_list', 'queue' => function($q) {
            $q->latest();
        }])
        ->where('user_id', auth()->id())
        ->get();
    }

    #[Computed]
    public function vehicleStats(): array {
        $vehicles = $this->vehicles;
        return [
            'total'     => $vehicles->count(),
            'loading'   => $vehicles->filter(fn($vehicle) => $vehicle->queue?->status === 'loading')->count(),
            'staging'   => $vehicles->filter(fn($vehicle) => $vehicle->queue?->status === 'staging')->count(),
            'not_queue' => $vehicles->filter(fn($vehicle) => !$vehicle->queue || !in_array($vehicle->queue->status, ['loading', 'staging']))->count(),
        ];
    }


    #[On('echo:user-info-updated,UserInfoUpdated')]
    public function refreshUserInfo() {

        unset($this->vehicles);
    }

    // public function mount() {
    //     dd($this->vehicles);
    // }
}
?>

<div>
    <x-pages-heading
        heading="My vehicles"
        description="Monitor your vehicles and their current queue status here."
    />

    {{-- Stat tiles --}}
    <div class="grid grid-cols-4 gap-3 mt-6 mb-5">
        <div class="rounded-lg bg-zinc-50 dark:bg-zinc-800 p-4">
            <p class="text-xs text-zinc-400 mb-1">Total vehicles</p>
            <p class="text-2xl font-medium text-blue-700 dark:text-blue-400">
                {{ $this->vehicleStats['total'] }}
            </p>
        </div>
        <div class="rounded-lg bg-zinc-50 dark:bg-zinc-800 p-4">
            <p class="text-xs text-zinc-400 mb-1">Currently loading</p>
            <p class="text-2xl font-medium text-green-700 dark:text-green-400">
                {{ $this->vehicleStats['loading'] }}
            </p>
        </div>
        <div class="rounded-lg bg-zinc-50 dark:bg-zinc-800 p-4">
            <p class="text-xs text-zinc-400 mb-1">In staging</p>
            <p class="text-2xl font-medium text-amber-700 dark:text-amber-400">
                {{ $this->vehicleStats['staging'] }}
            </p>
        </div>
        <div class="rounded-lg bg-zinc-50 dark:bg-zinc-800 p-4">
            <p class="text-xs text-zinc-400 mb-1">Not in queue</p>
            <p class="text-2xl font-medium text-zinc-500 dark:text-zinc-400">
                {{ $this->vehicleStats['not_queue'] }}
            </p>
        </div>
    </div>

    {{-- Table --}}
    <flux:table>
        <flux:table.columns>
            <flux:table.column>#</flux:table.column>
            <flux:table.column>Plate no.</flux:table.column>
            <flux:table.column>Type</flux:table.column>
            <flux:table.column>Route</flux:table.column>
            <flux:table.column>Queue status</flux:table.column>
            <flux:table.column>Registered</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->vehicles as $index => $vehicle)

                <flux:table.row :key="$vehicle->id">

                    <flux:table.cell class="text-zinc-400">
                        {{ $index + 1 }}
                    </flux:table.cell>

                    <flux:table.cell class="font-mono font-medium">
                        {{ $vehicle->plate_number }}
                    </flux:table.cell>

                    <flux:table.cell>
                        <div class="flex items-center gap-2">
                            {{ $vehicle->vehicle_type }}
                        </div>
                    </flux:table.cell>

                    <flux:table.cell class="text-zinc-500 text-sm">
                        Iriga Terminal → {{ $vehicle->route_list->terminal }}
                    </flux:table.cell>

                    <flux:table.cell>
                        @if ($vehicle->queue?->status === 'loading')
                            <flux:badge color="green" size="sm">Loading</flux:badge>
                        @elseif ($vehicle->queue?->status === 'staging')
                            <flux:badge color="yellow" size="sm">Staging</flux:badge>
                        @else
                            <flux:badge color="red" size="sm">Departed</flux:badge>
                        @endif
                    </flux:table.cell>

                    <flux:table.cell class="text-zinc-400 text-xs">
                        {{ $vehicle->created_at->format('M d, Y') }}
                    </flux:table.cell>

                    <flux:table.cell>
                        <flux:link href="/operator/vehicles/{{ $vehicle->id }}" variant="subtle" wire:navigate>
                            <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom" />
                        </flux:link>
                    </flux:table.cell>

                </flux:table.row>

            @empty
                <flux:table.row>
                    <flux:table.cell colspan="9">
                        <div class="flex flex-col items-center justify-center py-12 gap-2">
                            <flux:icon.truck class="w-8 h-8 text-zinc-300" />
                            <p class="text-sm text-zinc-400">No vehicles registered yet.</p>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
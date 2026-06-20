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

    <div class="grid grid-cols-4 gap-3 mt-6 mb-5">
        <flux:card>
            <x-text size="xs" class="mb-1" color="blue">Total vehicles</x-text>
            <x-text class="text-2xl">
                {{ $this->vehicleStats['total'] }}
            </x-text>
        </flux:card>
        <flux:card>
            <x-text size="xs" class="mb-1">Currently loading</x-text>
            <x-text class="text-2xl" color="green">
                {{ $this->vehicleStats['loading'] }}
            </x-text>
        </flux:card>
        <flux:card>
            <x-text size="xs" class="mb-1">In staging</x-text>
            <x-text class="text-2xl " color="orange">
                {{ $this->vehicleStats['staging'] }}
            </x-text>
        </flux:card>
        <flux:card>
            <x-text size="xs" class="mb-1">Not in queue</x-text>
            <x-text class="text-2xl">
                {{ $this->vehicleStats['not_queue'] }}
            </x-text>
        </flux:card>
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
                            <x-text class="text-sm text-zinc-400">No vehicles registered yet.</x-text>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
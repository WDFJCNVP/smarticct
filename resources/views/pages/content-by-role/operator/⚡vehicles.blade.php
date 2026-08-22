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

    {{-- Table card --}}
    <flux:card class="mb-4">
        <div class="overflow-x-auto">
            <flux:table container:class="max-h-160">
                <flux:table.columns sticky class="bg-light-secondary/50 items-center bg-light-subtle/50 dark:bg-dark-secondary/50 font-secondary text-nav-label text-light-txt-muted dark:text-dark-txt-muted">
                    <flux:table.column align="center" class="px-2! md:px-4! py-2">#</flux:table.column>
                    <flux:table.column align="center" class="px-2 md:px-4 py-2">Plate no.</flux:table.column>
                    <flux:table.column align="center" class="px-2 md:px-4 py-2">Type</flux:table.column>
                    <flux:table.column align="center" class="px-2 md:px-4 py-2">Driver</flux:table.column>
                    <flux:table.column align="center" class="px-2 md:px-4 py-2">Route</flux:table.column>
                    <flux:table.column align="center" class="px-2 md:px-4 py-2">Compliance</flux:table.column>
                    <flux:table.column align="center" class="px-2 md:px-4 py-2">Queue status</flux:table.column>
                    <flux:table.column align="center" class="px-2 md:px-4 py-2">Registered</flux:table.column>
                    <flux:table.column align="center" class="px-2! md:px-4! py-2">Actions</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($this->vehicles as $index => $vehicle)

                        <flux:table.row :key="$vehicle->id">

                            <flux:table.cell align="center" class="px-2! md:px-4! py-1.5 md:py-2 font-secondary text-xs md:text-timestamp text-light-txt-muted dark:text-dark-txt-muted">
                                {{ $index + 1 }}
                            </flux:table.cell>

                            <flux:table.cell align="center" class="px-2 md:px-4 py-1.5 md:py-2 font-mono font-medium text-xs md:text-table-row text-light-txt-primary dark:text-dark-txt-primary">
                                {{ $vehicle->plate_number }}
                            </flux:table.cell>

                            <flux:table.cell align="center" class="px-2 md:px-4 py-1.5 md:py-2 font-secondary text-xs md:text-table-row text-light-txt-body dark:text-dark-txt-body">
                                {{ $vehicle->vehicle_type }}
                            </flux:table.cell>

                            <flux:table.cell align="center" class="px-2 md:px-4 py-1.5 md:py-2 font-secondary text-xs md:text-table-row text-light-txt-body dark:text-dark-txt-body">
                                {{ $vehicle->driver_name ?? '—' }}
                            </flux:table.cell>

                            <flux:table.cell align="center" class="px-2 md:px-4 py-1.5 md:py-2 font-secondary text-xs md:text-timestamp text-light-txt-muted dark:text-dark-txt-muted">
                                Iriga → {{ $vehicle->route_list->terminal }}
                            </flux:table.cell>

                            <flux:table.cell align="center" class="px-2 md:px-4 py-1.5 md:py-2">
                                <div class="flex items-center justify-center gap-1">
                                    @if($vehicle->has_or_cr && $vehicle->or_cr_expiry_date)
                                        <flux:tooltip content="OR/CR verified (expires {{ $vehicle->or_cr_expiry_date->format('M d, Y') }})">
                                            <flux:icon.check-circle class="w-4 h-4 text-green-500" />
                                        </flux:tooltip>
                                    @else
                                        <flux:tooltip content="OR/CR not verified">
                                            <flux:icon.x-circle class="w-4 h-4 text-red-400" />
                                        </flux:tooltip>
                                    @endif

                                    @if($vehicle->has_franchise && $vehicle->franchise_expiry_date)
                                        <flux:tooltip content="Franchise verified (expires {{ $vehicle->franchise_expiry_date->format('M d, Y') }})">
                                            <flux:icon.check-circle class="w-4 h-4 text-green-500" />
                                        </flux:tooltip>
                                    @else
                                        <flux:tooltip content="Franchise not verified">
                                            <flux:icon.x-circle class="w-4 h-4 text-red-400" />
                                        </flux:tooltip>
                                    @endif
                                </div>
                            </flux:table.cell>

                            <flux:table.cell align="center" class="px-2 md:px-4 py-1.5 md:py-2">
                                @if ($vehicle->queue?->status === 'loading')
                                    <flux:badge color="green" size="sm" class="font-secondary text-badge text-xs">Loading</flux:badge>
                                @elseif ($vehicle->queue?->status === 'staging')
                                    <flux:badge color="yellow" size="sm" class="font-secondary text-badge text-xs">Staging</flux:badge>
                                @else
                                    <flux:badge color="red" size="sm" class="font-secondary text-badge text-xs">Departed</flux:badge>
                                @endif
                            </flux:table.cell>

                            <flux:table.cell align="center" class="px-2 md:px-4 py-1.5 md:py-2 font-secondary text-xs md:text-timestamp text-light-txt-muted dark:text-dark-txt-muted">
                                {{ $vehicle->created_at->format('M d, Y') }}
                            </flux:table.cell>

                            <flux:table.cell align="center" class="px-2! md:px-4! py-1.5 md:py-2">
                                <flux:link href="/operator/vehicles/{{ $vehicle->id }}" variant="subtle" wire:navigate>
                                    <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom" class="scale-75 md:scale-100" />
                                </flux:link>
                            </flux:table.cell>

                        </flux:table.row>

                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="9" class="px-2 md:px-4 py-4">
                                <div class="flex flex-col items-center justify-center py-6 md:py-12 gap-2">
                                    <flux:icon.truck class="w-6 h-6 md:w-8 md:h-8 text-light-txt-muted dark:text-dark-txt-muted" />
                                    <x-text class="font-secondary text-sm md:text-table-row text-light-txt-muted dark:text-dark-txt-muted">
                                        No vehicles registered yet.
                                    </x-text>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>

        @if (method_exists($this->vehicles, 'hasPages') && $this->vehicles->hasPages())
            <div class="flex flex-wrap items-center justify-end gap-2 px-3 sm:px-4 py-2 border-t border-light-bd-default dark:border-dark-bd-default bg-light-secondary dark:bg-dark-secondary">
                {{ $this->vehicles->links() }}
            </div>
        @endif
    </flux:card>
</div>
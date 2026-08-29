<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;

use App\Models\Queue;
use App\Models\Vehicle;

new  #[Layout('layouts.operator-layout')] class extends Component
{
    use WithPagination;

    public $vehicle_type;
    public $plate_number;
    public $search = '';

    public Vehicle $vehicle;

    #[Computed]
    public function getQueuedRecords() {
        return Queue::query()
            ->where('user_id', auth()->user()->id) 
            ->where('plate_number', $this->vehicle->plate_number)
            ->when($this->search, function ($q) {
                $q->where(function ($q2) {
                    $q2->where('driver_name', 'like', '%' . $this->search . '%')
                    ->orWhere('status', 'like', '%' . $this->search . '%')
                    ->orWhere('plate_number', 'like', '%' . $this->search . '%')
                    ->orWhere('destination', 'like', '%' . $this->search . '%');
                });
            })
            ->latest()
            ->paginate(10);
    }

    #[Computed]
    public function vehicleStats(): array {
        $all = Queue::where('user_id', auth()->user()->id)->where('plate_number', $this->vehicle->plate_number)->get();
        return [
            'total'      => $all->count(),
            'departed'   => $all->where('status', 'departed')->count(),
            'today'      => $all->filter(fn($queue) => \Carbon\Carbon::parse($queue->time_queued)->isToday())->count(),
        ];
    }

    // public function mount() {
    //     dd($this->getQueuedRecords);
    // }

};
?>
<div>
    {{-- Breadcrumbs & heading in a row --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mt-2 mb-4 sm:my-4">
        <div class="order-2 sm:order-1">
            <x-text 
                class="font-medium text-light-txt-primary dark:text-dark-txt-primary"
                style="font-size: clamp(1.25rem, 4vw, var(--text-xl));"
            >
                Travel records
            </x-text>
            <x-text 
                class="text-sm text-light-txt-muted dark:text-dark-txt-muted mt-0.5"
                style="font-size: clamp(0.75rem, 2vw, var(--text-sm));"
            >
                View all queuing and departure history for this vehicle.
            </x-text>
        </div>

        <flux:breadcrumbs class="order-1 sm:order-2 text-xs sm:text-sm">
            <flux:breadcrumbs.item href="{{ route('operator.vehicles') }}" wire:navigate>
                My vehicles
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item>Travel records</flux:breadcrumbs.item>
        </flux:breadcrumbs>
    </div>

    {{-- Vehicle summary card --}}
    <flux:card class="mb-5 !p-3 sm:!p-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-4">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 sm:h-11 sm:w-11 items-center justify-center rounded-lg bg-primary/10 dark:bg-primary/20 text-primary dark:text-dark-txt-primary shrink-0">
                    <flux:icon.truck class="w-4 h-4 sm:w-5 sm:h-5" />
                </div>
                <div>
                    <x-text class="font-mono font-medium text-light-txt-primary dark:text-dark-txt-primary text-sm sm:text-base">
                        {{ $this->vehicle->plate_number }}
                    </x-text>
                    <x-text class="text-xs text-light-txt-muted dark:text-dark-txt-muted">{{ $this->vehicle->vehicle_type }}</x-text>
                </div>
            </div>

            <div class="flex flex-wrap gap-3 sm:gap-6">
                <div>
                    <span class="block text-[10px] sm:text-xs text-light-txt-muted dark:text-dark-txt-muted uppercase tracking-wider mb-0.5">Seats</span>
                    <span class="text-sm font-medium text-light-txt-body dark:text-dark-txt-body">
                        {{ $this->vehicle->total_seats }}
                    </span>
                </div>
                <div>
                    <span class="block text-[10px] sm:text-xs text-light-txt-muted dark:text-dark-txt-muted uppercase tracking-wider mb-0.5">Operator</span>
                    <span class="text-sm font-medium text-light-txt-body dark:text-dark-txt-body">
                        {{ $this->vehicle->user->name }}
                    </span>
                </div>
                <div>
                    <span class="block text-[10px] sm:text-xs text-light-txt-muted dark:text-dark-txt-muted uppercase tracking-wider mb-0.5">Driver</span>
                    <span class="text-sm font-medium text-light-txt-body dark:text-dark-txt-body">
                        {{ $this->vehicle->driver_name ?? '—' }}
                    </span>
                </div>
                <div>
                    <span class="block text-[10px] sm:text-xs text-light-txt-muted dark:text-dark-txt-muted uppercase tracking-wider mb-0.5">Compliance</span>
                    <div class="flex items-center gap-2">
                        @if($this->vehicle->has_or_cr && $this->vehicle->or_cr_expiry_date)
                            <flux:tooltip content="OR/CR verified (expires {{ $this->vehicle->or_cr_expiry_date->format('M d, Y') }})">
                                <flux:icon.check-circle class="w-4 h-4 text-success dark:text-dark-success" />
                            </flux:tooltip>
                        @else
                            <flux:tooltip content="OR/CR not verified">
                                <flux:icon.x-circle class="w-4 h-4 text-danger dark:text-dark-danger" />
                            </flux:tooltip>
                        @endif
                        @if($this->vehicle->has_franchise && $this->vehicle->franchise_expiry_date)
                            <flux:tooltip content="Franchise verified (expires {{ $this->vehicle->franchise_expiry_date->format('M d, Y') }})">
                                <flux:icon.check-circle class="w-4 h-4 text-success dark:text-dark-success" />
                            </flux:tooltip>
                        @else
                            <flux:tooltip content="Franchise not verified">
                                <flux:icon.x-circle class="w-4 h-4 text-danger dark:text-dark-danger" />
                            </flux:tooltip>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Expiry dates footer --}}
        <div class="border-t border-light-bd-default dark:border-dark-bd-default mt-3 sm:mt-4 pt-2 sm:pt-3 text-xs text-light-txt-muted dark:text-dark-txt-muted flex flex-wrap gap-3 sm:gap-4">
            @if($this->vehicle->has_or_cr && $this->vehicle->or_cr_expiry_date)
                <span>OR/CR expires: <strong>{{ $this->vehicle->or_cr_expiry_date->format('M d, Y') }}</strong></span>
            @else
                <span class="text-danger dark:text-dark-danger">OR/CR not verified</span>
            @endif
            @if($this->vehicle->has_franchise && $this->vehicle->franchise_expiry_date)
                <span>Franchise expires: <strong>{{ $this->vehicle->franchise_expiry_date->format('M d, Y') }}</strong></span>
            @else
                <span class="text-danger dark:text-dark-danger">Franchise not verified</span>
            @endif
        </div>
    </flux:card>

    {{-- KPI cards – larger on mobile --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-3 mb-5">
        <flux:card class="flex flex-row items-center justify-between gap-3 sm:flex-col sm:items-start sm:justify-start p-4 sm:p-4">
            <div class="flex items-center gap-2 sm:gap-2">
                <div class="flex items-center justify-center w-7 h-7 sm:w-7 sm:h-7 rounded-lg bg-primary/10 dark:bg-primary/20 shrink-0">
                    <flux:icon.truck class="w-4 h-4 sm:w-4 sm:h-4 text-primary dark:text-dark-txt-primary" />
                </div>
                <x-text class="font-secondary text-sm sm:text-stat-label text-light-txt-muted dark:text-dark-txt-muted">
                    Total trips
                </x-text>
            </div>
            <x-text class="font-primary text-xl sm:text-stat-value font-bold text-light-txt-primary dark:text-dark-txt-primary">
                {{ $this->vehicleStats['total'] }}
            </x-text>
        </flux:card>

        <flux:card class="flex flex-row items-center justify-between gap-3 sm:flex-col sm:items-start sm:justify-start p-4 sm:p-4">
            <div class="flex items-center gap-2 sm:gap-2">
                <div class="flex items-center justify-center w-7 h-7 sm:w-7 sm:h-7 rounded-lg bg-danger/10 dark:bg-dark-danger/20 shrink-0">
                    <flux:icon.check-circle class="w-4 h-4 sm:w-4 sm:h-4 text-danger dark:text-dark-danger" />
                </div>
                <x-text class="font-secondary text-sm sm:text-stat-label text-light-txt-muted dark:text-dark-txt-muted">
                    Departed
                </x-text>
            </div>
            <x-text class="font-primary text-xl sm:text-stat-value font-bold text-danger dark:text-dark-danger">
                {{ $this->vehicleStats['departed'] }}
            </x-text>
        </flux:card>

        <flux:card class="flex flex-row items-center justify-between gap-3 sm:flex-col sm:items-start sm:justify-start p-4 sm:p-4">
            <div class="flex items-center gap-2 sm:gap-2">
                <div class="flex items-center justify-center w-7 h-7 sm:w-7 sm:h-7 rounded-lg bg-warning/10 dark:bg-dark-warning/20 shrink-0">
                    <flux:icon.clock class="w-4 h-4 sm:w-4 sm:h-4 text-warning dark:text-dark-warning" />
                </div>
                <x-text class="font-secondary text-sm sm:text-stat-label text-light-txt-muted dark:text-dark-txt-muted">
                    Trips today
                </x-text>
            </div>
            <x-text class="font-primary text-xl sm:text-stat-value font-bold text-warning dark:text-dark-warning">
                {{ $this->vehicleStats['today'] }}
            </x-text>
        </flux:card>
    </div>

    {{-- Table card (no search bar) --}}
    <flux:card class="mb-4">
        <div class="overflow-x-auto">
            <flux:table>
                <flux:table.columns sticky class="bg-light-secondary/50 items-center bg-light-subtle/50 dark:bg-dark-secondary/50 font-secondary text-nav-label text-light-txt-muted dark:text-dark-txt-muted">
                    <flux:table.column align="center" class="px-2! md:px-4! py-2">#</flux:table.column>
                    <flux:table.column align="center" class="px-2 md:px-4 py-2">Driver</flux:table.column>
                    {{-- Hidden on mobile --}}
                    <flux:table.column align="center" class="hidden md:table-cell px-2 md:px-4 py-2">Type</flux:table.column>
                    <flux:table.column align="center" class="hidden md:table-cell px-2 md:px-4 py-2">Destination</flux:table.column>
                    <flux:table.column align="center" class="hidden md:table-cell px-2 md:px-4 py-2">Plate no.</flux:table.column>
                    <flux:table.column align="center" class="px-2 md:px-4 py-2">Status</flux:table.column>
                    <flux:table.column align="center" class="px-2 md:px-4 py-2">Seats</flux:table.column>
                    <flux:table.column align="center" class="px-2 md:px-4 py-2">Time queued</flux:table.column>
                    <flux:table.column align="center" class="px-2 md:px-4 py-2">Time departed</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($this->getQueuedRecords as $index => $queue)
                        <flux:table.row :key="$queue->id">
                            <flux:table.cell align="center" class="px-2! md:px-4! py-1.5 md:py-2 font-secondary text-xs md:text-timestamp text-light-txt-muted dark:text-dark-txt-muted">
                                {{ $index + 1 }}
                            </flux:table.cell>

                            <flux:table.cell align="center" class="px-2 md:px-4 py-1.5 md:py-2 font-secondary text-xs md:text-table-row text-light-txt-body dark:text-dark-txt-body">
                                {{ $queue->driver_name ?? '—' }}
                            </flux:table.cell>

                            <flux:table.cell align="center" class="hidden md:table-cell px-2 md:px-4 py-1.5 md:py-2 font-secondary text-xs md:text-table-row text-light-txt-body dark:text-dark-txt-body">
                                {{ $queue->vehicle_type }}
                            </flux:table.cell>

                            <flux:table.cell align="center" class="hidden md:table-cell px-2 md:px-4 py-1.5 md:py-2 font-secondary text-xs md:text-table-row text-light-txt-body dark:text-dark-txt-body">
                                {{ $queue->destination }}
                            </flux:table.cell>

                            <flux:table.cell align="center" class="hidden md:table-cell px-2 md:px-4 py-1.5 md:py-2 font-mono text-xs md:text-timestamp text-light-txt-muted dark:text-dark-txt-muted">
                                {{ $queue->plate_number }}
                            </flux:table.cell>

                            <flux:table.cell align="center" class="px-2 md:px-4 py-1.5 md:py-2">
                                @if ($queue->status === 'departed')
                                    <flux:badge color="red" size="sm" class="font-secondary text-badge text-xs">Departed</flux:badge>
                                @elseif ($queue->status === 'staging')
                                    <flux:badge color="orange" size="sm" class="font-secondary text-badge text-xs">Staging</flux:badge>
                                @else
                                    <flux:badge color="green" size="sm" class="font-secondary text-badge text-xs">Loading</flux:badge>
                                @endif
                            </flux:table.cell>

                            <flux:table.cell align="center" class="px-2 md:px-4 py-1.5 md:py-2">
                                <div class="flex items-center justify-center gap-2">
                                    <span class="text-xs md:text-timestamp tabular-nums text-light-txt-body dark:text-dark-txt-body">
                                        {{ $queue->seat_count }} / {{ $queue->seat_capacity }}
                                    </span>
                                    <div class="w-12 h-1.5 rounded-full bg-light-subtle dark:bg-dark-subtle overflow-hidden">
                                        <div
                                            class="h-full rounded-full {{ $queue->seat_count >= $queue->seat_capacity ? 'bg-success dark:bg-dark-success' : 'bg-info dark:bg-dark-info' }}"
                                            style="width: {{ $queue->seat_capacity > 0 ? ($queue->seat_count / $queue->seat_capacity * 100) : 0 }}%"
                                        ></div>
                                    </div>
                                </div>
                            </flux:table.cell>

                            <flux:table.cell align="center" class="px-2 md:px-4 py-1.5 md:py-2 font-secondary text-xs md:text-timestamp text-light-txt-muted dark:text-dark-txt-muted">
                                {{ $queue->time_queued->format('M d, Y') }}<br class="block md:hidden">
                                <span class="text-xs">{{ $queue->time_queued->format('g:i A') }}</span>
                            </flux:table.cell>

                            <flux:table.cell align="center" class="px-2 md:px-4 py-1.5 md:py-2 font-secondary text-xs md:text-timestamp text-light-txt-muted dark:text-dark-txt-muted">
                                @if ($queue->time_departed)
                                    {{ $queue->time_departed->format('M d, Y') }}<br class="block md:hidden">
                                    <span class="text-xs">{{ $queue->time_departed->format('g:i A') }}</span>
                                @else
                                    <span class="text-light-txt-muted/50">—</span>
                                @endif
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="9" class="px-2 md:px-4 py-4">
                                <div class="flex flex-col items-center justify-center py-6 md:py-12 gap-2">
                                    <flux:icon.archive-box class="w-6 h-6 md:w-8 md:h-8 text-light-txt-muted dark:text-dark-txt-muted" />
                                    <x-text class="font-secondary text-sm md:text-table-row text-light-txt-muted dark:text-dark-txt-muted">
                                        No travel records found.
                                    </x-text>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>

        @if ($this->getQueuedRecords->hasPages())
            <div class="flex flex-wrap items-center justify-end gap-2 px-3 sm:px-4 py-2 border-t border-light-bd-default dark:border-dark-bd-default bg-light-secondary dark:bg-dark-secondary">
                {{ $this->getQueuedRecords->links() }}
            </div>
        @endif
    </flux:card>
</div>
<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Livewire\WithPagination;

use App\Models\Queue;

new #[Layout('layouts.admin-layout')] class extends Component
{
    use WithPagination;

    public string $vehicleTypeFilter = '';
    public string $statusFilter = '';


    public function updatedVehicleTypeFilter()
    {
        $this->resetPage();
    }

    public function updatedStatusFilter()
    {
        $this->resetPage();
    }

    #[Computed]
    public function travelRecords()
    {
        return Queue::query()
            ->when($this->vehicleTypeFilter, fn ($q) => $q->where('vehicle_type', $this->vehicleTypeFilter))
            ->when($this->statusFilter === 'departed', fn ($q) => $q->whereNotNull('time_departed'))
            ->when($this->statusFilter === 'queued', fn ($q) => $q->whereNull('time_departed'))
            ->latest()
            ->paginate(10);
    }

    #[Computed]
    public function stats()
    {
        $base = Queue::query()
            ->when($this->vehicleTypeFilter, fn ($q) => $q->where('vehicle_type', $this->vehicleTypeFilter));

        return [
            'total' => $base->count(),
            'departed' => $base->clone()->whereNotNull('time_departed')->count(),
            'queued' => $base->clone()->whereNull('time_departed')->count(),
            'overbooked' => $base->clone()->whereColumn('seat_count', '>', 'seat_capacity')->count(),
        ];
    }

    #[Computed]
    public function vehicleTypes()
    {
        return Queue::query()->distinct()->pluck('vehicle_type');
    }
};
?>
<div>
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <x-pages-heading heading="Travel Record" description="You can monitor travel records here." />

        <div class="flex flex-wrap sm:flex-nowrap items-stretch sm:items-center gap-2 w-full sm:w-auto">
            <flux:select wire:model.live="vehicleTypeFilter" size="sm" placeholder="All vehicle types" class="w-full sm:w-40 font-secondary text-table-row">
                <flux:select.option value="">All vehicle types</flux:select.option>
                @foreach ($this->vehicleTypes as $type)
                    <flux:select.option value="{{ $type }}">{{ $type }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="statusFilter" size="sm" placeholder="All statuses" class="w-full sm:w-36 font-secondary text-table-row">
                <flux:select.option value="">All statuses</flux:select.option>
                <flux:select.option value="departed">Departed</flux:select.option>
                <flux:select.option value="queued">Queued</flux:select.option>
            </flux:select>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2 sm:gap-3 mt-6 mb-5">
        <flux:card class="flex flex-row items-center justify-between gap-2 sm:flex-col sm:items-start sm:justify-start p-3 sm:p-4">
            <div class="flex items-center gap-1.5 sm:gap-2">
                <div class="flex items-center justify-center w-6 h-6 sm:w-7 sm:h-7 rounded-lg bg-primary/10 dark:bg-primary/20">
                    <flux:icon.truck class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-primary dark:text-dark-txt-primary" />
                </div>
                <x-text class="font-secondary text-xs sm:text-stat-label text-light-txt-muted dark:text-dark-txt-muted">
                    Total trips
                </x-text>
            </div>
            <x-text class="font-primary text-stat-value font-bold text-light-txt-primary dark:text-dark-txt-primary">
                {{ $this->stats['total'] }}
            </x-text>
        </flux:card>

        <flux:card class="flex flex-row items-center justify-between gap-2 sm:flex-col sm:items-start sm:justify-start p-3 sm:p-4">
            <div class="flex items-center gap-1.5 sm:gap-2">
                <div class="flex items-center justify-center w-6 h-6 sm:w-7 sm:h-7 rounded-lg bg-success/10 dark:bg-dark-success/20">
                    <flux:icon.check-circle class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-success dark:text-dark-success" />
                </div>
                <x-text class="font-secondary text-xs sm:text-stat-label text-light-txt-muted dark:text-dark-txt-muted">
                    Departed
                </x-text>
            </div>
            <x-text class="font-primary text-stat-value font-bold text-success dark:text-dark-success">
                {{ $this->stats['departed'] }}
            </x-text>
        </flux:card>

        <flux:card class="flex flex-row items-center justify-between gap-2 sm:flex-col sm:items-start sm:justify-start p-3 sm:p-4">
            <div class="flex items-center gap-1.5 sm:gap-2">
                <div class="flex items-center justify-center w-6 h-6 sm:w-7 sm:h-7 rounded-lg bg-warning/10 dark:bg-dark-warning/20">
                    <flux:icon.clock class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-warning dark:text-dark-warning" />
                </div>
                <x-text class="font-secondary text-xs sm:text-stat-label text-light-txt-muted dark:text-dark-txt-muted">
                    Still queued
                </x-text>
            </div>
            <x-text class="font-primary text-stat-value font-bold text-warning dark:text-dark-warning">
                {{ $this->stats['queued'] }}
            </x-text>
        </flux:card>

        <flux:card class="flex flex-row items-center justify-between gap-2 sm:flex-col sm:items-start sm:justify-start p-3 sm:p-4">
            <div class="flex items-center gap-1.5 sm:gap-2">
                <div class="flex items-center justify-center w-6 h-6 sm:w-7 sm:h-7 rounded-lg bg-danger/10 dark:bg-dark-danger/20">
                    <flux:icon.exclamation-triangle class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-danger dark:text-dark-danger" />
                </div>
                <x-text class="font-secondary text-xs sm:text-stat-label text-light-txt-muted dark:text-dark-txt-muted">
                    Overbooked
                </x-text>
            </div>
            <x-text class="font-primary text-stat-value font-bold {{ $this->stats['overbooked'] > 0 ? 'text-danger dark:text-dark-danger' : 'text-light-txt-primary dark:text-dark-txt-primary' }}">
                {{ $this->stats['overbooked'] }}
            </x-text>
        </flux:card>
    </div>

    <x-pages-heading>Trip records</x-pages-heading>

    <flux:card class="mb-4">
        <div class="overflow-x-auto">
            <flux:table container:class="max-h-160">
                <flux:table.columns sticky class="bg-light-secondary/50 items-center bg-light-subtle/50 dark:bg-dark-secondary/50 font-secondary text-nav-label text-light-txt-muted dark:text-dark-txt-muted">
                    <flux:table.column align="center" class="px-2 md:px-4 py-2">Plate No.</flux:table.column>
                    <flux:table.column align="center" class="px-2 md:px-4 py-2">Driver</flux:table.column>
                    <flux:table.column align="center" class="hidden md:table-cell px-2 md:px-4 py-2">Vehicle Type</flux:table.column>
                    <flux:table.column align="center" class="hidden md:table-cell px-2 md:px-4 py-2">Occupancy</flux:table.column>
                    <flux:table.column align="center" class="hidden md:table-cell px-2 md:px-4 py-2">Queued</flux:table.column>
                    <flux:table.column align="center" class="hidden md:table-cell px-2 md:px-4 py-2">Departed</flux:table.column>
                    <flux:table.column align="center" class="px-2 md:px-4 py-2">Status</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($this->travelRecords as $record)
                        @php
                            $isOverbooked = $record->seat_count > $record->seat_capacity;
                        @endphp

                        <flux:table.row :key="$record->id" class="{{ $isOverbooked ? 'bg-danger/5 dark:bg-dark-danger/10' : '' }}">
                            <flux:table.cell align="center" class="px-2 md:px-4 py-1.5 md:py-2 font-mono text-xs md:text-table-row text-light-txt-body dark:text-dark-txt-primary">
                                {{ $record->plate_number }}
                            </flux:table.cell>

                            <flux:table.cell align="center" class="px-2 md:px-4 py-1.5 md:py-2 font-secondary text-xs md:text-table-row text-light-txt-body dark:text-dark-txt-primary">
                                {{ $record->driver_name }}
                            </flux:table.cell>

                            <flux:table.cell align="center" class="hidden md:table-cell px-2 md:px-4 py-1.5 md:py-2">
                                <flux:badge size="sm" color="{{ $record->vehicle_type === 'Bus' ? 'blue' : 'amber' }}" class="font-secondary text-badge text-xs">
                                    {{ $record->vehicle_type }}
                                </flux:badge>
                            </flux:table.cell>

                            <flux:table.cell align="center" class="hidden md:table-cell px-2 md:px-4 py-1.5 md:py-2 font-secondary text-xs md:text-timestamp {{ $isOverbooked ? 'text-danger dark:text-dark-danger' : 'text-light-txt-muted dark:text-dark-txt-muted' }}">
                                {{ $record->seat_count }}/{{ $record->seat_capacity }}
                            </flux:table.cell>

                            <flux:table.cell align="center" class="hidden md:table-cell px-2 md:px-4 py-1.5 md:py-2 font-secondary text-xs md:text-timestamp text-light-txt-muted dark:text-dark-txt-muted">
                                {{ $record->time_queued?->format('M d, Y g:i a') ?? '—' }}
                            </flux:table.cell>

                            <flux:table.cell align="center" class="hidden md:table-cell px-2 md:px-4 py-1.5 md:py-2 font-secondary text-xs md:text-timestamp text-light-txt-muted dark:text-dark-txt-muted">
                                {{ $record->time_departed?->format('M d, Y g:i a') ?? '—' }}
                            </flux:table.cell>

                            <flux:table.cell align="center" class="px-2 md:px-4 py-1.5 md:py-2">
                                @if ($record->time_departed)
                                    <flux:badge size="sm" color="green" icon="check" class="font-secondary text-badge text-xs">Departed</flux:badge>
                                @else
                                    <flux:badge size="sm" color="amber" icon="clock" class="font-secondary text-badge text-xs">Staging</flux:badge>
                                @endif
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="7" class="px-2 md:px-4 py-4">
                                <div class="flex flex-col items-center justify-center py-6 md:py-12 gap-2">
                                    <flux:icon.truck class="w-6 h-6 md:w-8 md:h-8 text-light-txt-muted dark:text-dark-txt-muted" />
                                    <x-text class="font-secondary text-sm md:text-table-row text-light-txt-muted dark:text-dark-txt-muted">
                                        No travel records match the current filters.
                                    </x-text>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>

        @if ($this->travelRecords->hasPages())
            <div class="flex flex-wrap items-center justify-end gap-2 px-3 sm:px-4 py-2 border-t border-light-bd-default dark:border-dark-bd-default bg-light-secondary dark:bg-dark-secondary">
                {{ $this->travelRecords->links() }}
            </div>
        @endif
    </flux:card>
</div>
<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\WithPagination;

use App\Models\Queue;

new class extends Component
{
    use WithPagination;

    public string $vehicleTypeFilter = '';
    public string $routeFilter = '';
    public string $statusFilter = '';
    public string $dateFrom = '';
    public string $dateTo = '';

    public string $exportVehicleType = '';
    public string $exportRoute = '';
    public string $exportDateFrom = '';
    public string $exportDateTo = '';

    public function mount()
    {
        $this->dateFrom = today()->toDateString();
        $this->dateTo   = today()->toDateString();
    }

    public function updatedVehicleTypeFilter()
    {
        $this->resetPage();
    }

    public function updatedRouteFilter()
    {
        $this->resetPage();
    }

    public function updatedStatusFilter()
    {
        $this->resetPage();
    }

    public function updatedDateFrom()
    {
        $this->resetPage();
    }

    public function updatedDateTo()
    {
        $this->resetPage();
    }

    public function resetDateRange()
    {
        $this->dateFrom = today()->toDateString();
        $this->dateTo   = today()->toDateString();
        $this->resetPage();
    }

    public function prepareExportModal()
    {
        $this->exportVehicleType = $this->vehicleTypeFilter;
        $this->exportRoute       = $this->routeFilter;
        $this->exportDateFrom    = $this->dateFrom;
        $this->exportDateTo      = $this->dateTo;
    }

    public function setExportRangeAllTime()
    {
        $this->exportDateFrom = '';
        $this->exportDateTo = '';
    }

    public function setExportRangeToday()
    {
        $this->exportDateFrom = today()->toDateString();
        $this->exportDateTo = today()->toDateString();
    }

    #[Computed]
    public function exportRangePreset(): string
    {
        if ($this->exportDateFrom === '' && $this->exportDateTo === '') {
            return 'all';
        }

        $today = today()->toDateString();

        if ($this->exportDateFrom === $today && $this->exportDateTo === $today) {
            return 'today';
        }

        return 'custom';
    }

    #[Computed]
    public function exportUrl(): string
    {
        return route('dispatch-log.export', array_filter([
            'from'         => $this->exportDateFrom,
            'to'           => $this->exportDateTo,
            'vehicle_type' => $this->exportVehicleType,
            'route'        => $this->exportRoute,
        ]));
    }

    #[Computed]
    public function travelRecords()
    {
        return Queue::query()
            ->whereDate('time_queued', '>=', $this->dateFrom)
            ->whereDate('time_queued', '<=', $this->dateTo)
            ->when($this->vehicleTypeFilter, fn ($q) => $q->where('vehicle_type', $this->vehicleTypeFilter))
            ->when($this->routeFilter, fn ($q) => $q->where('destination', $this->routeFilter))
            ->when($this->statusFilter === 'departed', fn ($q) => $q->whereNotNull('time_departed'))
            ->when($this->statusFilter === 'queued', fn ($q) => $q->whereNull('time_departed'))
            ->latest('time_queued')
            ->paginate(10);
    }

    #[Computed]
    public function stats()
    {
        $base = Queue::query()
            ->whereDate('time_queued', '>=', $this->dateFrom)
            ->whereDate('time_queued', '<=', $this->dateTo)
            ->when($this->vehicleTypeFilter, fn ($q) => $q->where('vehicle_type', $this->vehicleTypeFilter))
            ->when($this->routeFilter, fn ($q) => $q->where('destination', $this->routeFilter));

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

    #[Computed]
    public function routes()
    {
        return Queue::query()->distinct()->pluck('destination');
    }

    public function render(): mixed
    {
        $layout = match (auth()->user()->role) {
            'cashier' => 'layouts.cashier-layout',
            default   => 'layouts.admin-layout',
        };

        return $this->view()->layout($layout);
    }
};
?>
<div>
    {{-- Page header with the same style as route page --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div>
            <x-heading
                size="xl"
                class="!font-primary !font-bold !text-light-txt-primary dark:!text-dark-txt-primary"
                style="font-size: var(--text-page-title)"
            >
                Travel Record
            </x-heading>
            <x-text variant="subtle" class="!font-secondary mt-1 block" style="font-size: var(--text-helper)">
                You can monitor travel records here.
            </x-text>
        </div>

        <div class="flex flex-col sm:flex-row sm:items-center gap-2 w-full sm:w-auto shrink-0">
            <div class="flex items-center gap-2 w-full sm:w-auto">
                <flux:input
                    type="date"
                    wire:model.live="dateFrom"
                    size="sm"
                    class="w-full sm:w-36 font-secondary text-table-row dark:bg-dark-secondary dark:border-dark-bd-default dark:text-dark-txt-primary"
                />
                <span class="text-light-txt-muted dark:text-dark-txt-muted text-sm shrink-0">to</span>
                <flux:input
                    type="date"
                    wire:model.live="dateTo"
                    size="sm"
                    class="w-full sm:w-36 font-secondary text-table-row dark:bg-dark-secondary dark:border-dark-bd-default dark:text-dark-txt-primary"
                />
            </div>

            <div class="flex items-center gap-2 w-full sm:w-auto">
                <flux:button
                    wire:click="resetDateRange"
                    size="sm"
                    variant="ghost"
                    class="font-secondary flex-1 sm:flex-none justify-center"
                >
                    Today
                </flux:button>

                <flux:modal.trigger name="export-dispatch-log">
                    <flux:button
                        wire:click="prepareExportModal"
                        icon="arrow-down-tray"
                        size="sm"
                        variant="primary"
                        class="font-secondary flex-1 sm:flex-none justify-center"
                    >
                        Export PDF
                    </flux:button>
                </flux:modal.trigger>
            </div>
        </div>
    </div>

    <div class="flex flex-wrap sm:flex-nowrap items-stretch sm:items-center gap-2 w-full sm:w-auto mt-3">
        <flux:select
            wire:model.live="vehicleTypeFilter"
            size="sm"
            placeholder="All vehicle types"
            class="w-full sm:w-40 font-secondary text-table-row dark:bg-dark-secondary dark:border-dark-bd-default dark:text-dark-txt-primary"
        >
            <flux:select.option value="">All vehicle types</flux:select.option>
            @foreach ($this->vehicleTypes as $type)
                <flux:select.option value="{{ $type }}">{{ $type }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select
            wire:model.live="routeFilter"
            size="sm"
            placeholder="All routes"
            class="w-full sm:w-40 font-secondary text-table-row dark:bg-dark-secondary dark:border-dark-bd-default dark:text-dark-txt-primary"
        >
            <flux:select.option value="">All routes</flux:select.option>
            @foreach ($this->routes as $route)
                <flux:select.option value="{{ $route }}">{{ $route }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select
            wire:model.live="statusFilter"
            size="sm"
            placeholder="All statuses"
            class="w-full sm:w-36 font-secondary text-table-row dark:bg-dark-secondary dark:border-dark-bd-default dark:text-dark-txt-primary"
        >
            <flux:select.option value="">All statuses</flux:select.option>
            <flux:select.option value="departed">Departed</flux:select.option>
            <flux:select.option value="queued">Queued</flux:select.option>
        </flux:select>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2 sm:gap-3 mt-6 mb-5">
        <flux:card class="p-3 sm:p-4">
            <div class="flex items-center gap-1.5 sm:gap-2 mb-1.5">
                <div class="flex items-center justify-center w-6 h-6 sm:w-7 sm:h-7 rounded-lg bg-primary/10 dark:bg-primary/20 shrink-0">
                    <flux:icon.truck class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-primary dark:text-dark-txt-primary" />
                </div>
                <x-text class="font-secondary text-xs sm:text-stat-label text-light-txt-muted dark:text-dark-txt-muted">
                    Total trips
                </x-text>
            </div>
            <x-text class="font-primary text-stat-value font-bold text-light-txt-primary dark:text-dark-txt-primary block">
                {{ $this->stats['total'] }}
            </x-text>
        </flux:card>

        <flux:card class="p-3 sm:p-4">
            <div class="flex items-center gap-1.5 sm:gap-2 mb-1.5">
                <div class="flex items-center justify-center w-6 h-6 sm:w-7 sm:h-7 rounded-lg bg-success/10 dark:bg-dark-success/20 shrink-0">
                    <flux:icon.check-circle class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-success dark:text-dark-success" />
                </div>
                <x-text class="font-secondary text-xs sm:text-stat-label text-light-txt-muted dark:text-dark-txt-muted">
                    Departed
                </x-text>
            </div>
            <x-text class="font-primary text-stat-value font-bold text-success dark:text-dark-success block">
                {{ $this->stats['departed'] }}
            </x-text>
        </flux:card>

        <flux:card class="p-3 sm:p-4">
            <div class="flex items-center gap-1.5 sm:gap-2 mb-1.5">
                <div class="flex items-center justify-center w-6 h-6 sm:w-7 sm:h-7 rounded-lg bg-warning/10 dark:bg-dark-warning/20 shrink-0">
                    <flux:icon.clock class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-warning dark:text-dark-warning" />
                </div>
                <x-text class="font-secondary text-xs sm:text-stat-label text-light-txt-muted dark:text-dark-txt-muted">
                    Still queued
                </x-text>
            </div>
            <x-text class="font-primary text-stat-value font-bold text-warning dark:text-dark-warning block">
                {{ $this->stats['queued'] }}
            </x-text>
        </flux:card>

        <flux:card class="p-3 sm:p-4">
            <div class="flex items-center gap-1.5 sm:gap-2 mb-1.5">
                <div class="flex items-center justify-center w-6 h-6 sm:w-7 sm:h-7 rounded-lg bg-danger/10 dark:bg-dark-danger/20 shrink-0">
                    <flux:icon.exclamation-triangle class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-danger dark:text-dark-danger" />
                </div>
                <x-text class="font-secondary text-xs sm:text-stat-label text-light-txt-muted dark:text-dark-txt-muted">
                    Overbooked
                </x-text>
            </div>
            <x-text class="font-primary text-stat-value font-bold {{ $this->stats['overbooked'] > 0 ? 'text-danger dark:text-dark-danger' : 'text-light-txt-primary dark:text-dark-txt-primary' }} block">
                {{ $this->stats['overbooked'] }}
            </x-text>
        </flux:card>
    </div>

    <flux:card class="mb-4 p-0! overflow-hidden">
        <div class="overflow-x-auto">
            <flux:table container:class="md:max-h-160">
                <flux:table.columns sticky class="bg-light-secondary/50 items-center bg-light-subtle/50 dark:bg-dark-secondary/50 font-secondary text-nav-label text-light-txt-muted dark:text-dark-txt-muted">
                    <flux:table.column align="center" class="px-2 md:px-4 py-2">Plate No.</flux:table.column>
                    <flux:table.column align="center" class="px-2 md:px-4 py-2">Driver</flux:table.column>
                    <flux:table.column align="center" class="hidden md:table-cell px-2 md:px-4 py-2">Vehicle Type</flux:table.column>
                    <flux:table.column align="center" class="hidden md:table-cell px-2 md:px-4 py-2">Route</flux:table.column>
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

                            <flux:table.cell align="center" class="hidden md:table-cell px-2 md:px-4 py-1.5 md:py-2 font-secondary text-xs md:text-table-row text-light-txt-body dark:text-dark-txt-primary">
                                {{ $record->destination }}
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
                            <flux:table.cell colspan="8" class="px-2 md:px-4 py-4">
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

    <flux:modal
        name="export-dispatch-log"
        :closable="false"
        class="w-[calc(100%-2rem)] sm:max-w-lg md:max-w-2xl mx-auto rounded-xl overflow-hidden"
    >
        <div class="flex flex-col p-4 sm:p-6 !pr-4 sm:!pr-6 space-y-5 overflow-y-auto max-h-[70vh]">
            <!-- Header -->
            <div class="flex items-start justify-between">
                <div>
                    <flux:heading size="xl" class="!font-primary !font-bold text-light-txt-primary dark:text-dark-txt-primary">
                        Export dispatch log
                    </flux:heading>
                    <flux:text class="mt-1 font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">
                        Choose what to include in the PDF. This starts from your current view — narrow it down for a specific route/vehicle type daily log, or leave as "All" for the full log.
                    </flux:text>
                </div>
                <flux:modal.close>
                    <button type="button" class="p-1 rounded-full hover:bg-light-subtle dark:hover:bg-dark-subtle text-light-txt-muted dark:text-dark-txt-muted -mt-1">
                        <flux:icon name="x-mark" class="w-5 h-5" />
                    </button>
                </flux:modal.close>
            </div>

            <!-- Fields -->
            <flux:field>
                <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">Date range</flux:label>
                <div class="flex gap-2 mt-1.5">
                    <button
                        type="button"
                        wire:click="setExportRangeAllTime"
                        class="flex-1 rounded-lg border px-3 py-2 font-secondary text-sm font-medium transition text-center
                            {{ $this->exportRangePreset === 'all'
                                ? 'bg-primary text-white border-primary'
                                : 'bg-transparent text-light-txt-body dark:text-dark-txt-body border-light-bd-default dark:border-dark-bd-default hover:bg-light-subtle dark:hover:bg-dark-subtle' }}"
                    >
                        All Time
                    </button>
                    <button
                        type="button"
                        wire:click="setExportRangeToday"
                        class="flex-1 rounded-lg border px-3 py-2 font-secondary text-sm font-medium transition text-center
                            {{ $this->exportRangePreset === 'today'
                                ? 'bg-primary text-white border-primary'
                                : 'bg-transparent text-light-txt-body dark:text-dark-txt-body border-light-bd-default dark:border-dark-bd-default hover:bg-light-subtle dark:hover:bg-dark-subtle' }}"
                    >
                        Today
                    </button>
                </div>
            </flux:field>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <flux:field>
                    <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">From</flux:label>
                    <flux:input
                        type="date"
                        wire:model.live="exportDateFrom"
                        size="sm"
                        class="font-secondary text-table-row bg-light-primary dark:bg-dark-surface text-light-txt-body dark:text-dark-txt-primary border-light-bd-default dark:border-dark-bd-default placeholder:text-light-txt-muted dark:placeholder:text-dark-txt-muted"
                    />
                </flux:field>
                <flux:field>
                    <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">To</flux:label>
                    <flux:input
                        type="date"
                        wire:model.live="exportDateTo"
                        size="sm"
                        class="font-secondary text-table-row bg-light-primary dark:bg-dark-surface text-light-txt-body dark:text-dark-txt-primary border-light-bd-default dark:border-dark-bd-default placeholder:text-light-txt-muted dark:placeholder:text-dark-txt-muted"
                    />
                </flux:field>
            </div>

            <flux:field>
                <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">Vehicle type</flux:label>
                <flux:select
                    wire:model.live="exportVehicleType"
                    size="sm"
                    placeholder="All vehicle types"
                    class="font-secondary text-table-row bg-light-primary dark:bg-dark-surface text-light-txt-body dark:text-dark-txt-primary border-light-bd-default dark:border-dark-bd-default"
                >
                    <flux:select.option value="">All vehicle types</flux:select.option>
                    @foreach ($this->vehicleTypes as $type)
                        <flux:select.option value="{{ $type }}">{{ $type }}</flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>

            <flux:field>
                <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">Route</flux:label>
                <flux:select
                    wire:model.live="exportRoute"
                    size="sm"
                    placeholder="All routes"
                    class="font-secondary text-table-row bg-light-primary dark:bg-dark-surface text-light-txt-body dark:text-dark-txt-primary border-light-bd-default dark:border-dark-bd-default"
                >
                    <flux:select.option value="">All routes</flux:select.option>
                    @foreach ($this->routes as $route)
                        <flux:select.option value="{{ $route }}">{{ $route }}</flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>

            <!-- Footer -->
            <div class="flex flex-col-reverse sm:flex-row justify-end items-stretch sm:items-center gap-2 pt-2 border-t border-light-bd-default dark:border-dark-bd-default">
                <flux:modal.close class="w-full sm:w-auto">
                    <flux:button type="button" variant="ghost" class="w-full sm:w-auto justify-center font-secondary">
                        Cancel
                    </flux:button>
                </flux:modal.close>
                <flux:button
                    href="{{ $this->exportUrl }}"
                    icon="arrow-down-tray"
                    variant="primary"
                    class="font-secondary w-full sm:w-auto justify-center"
                >
                    Download PDF
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
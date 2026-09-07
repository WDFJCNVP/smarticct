<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\WithPagination;

use App\Models\Queue;

new class extends Component
{
    use WithPagination;

    // Live-updated properties (search & sort)
    public string $search = '';
    public string $sortBy = 'desc';

    // Applied filter values (only changed via modal)
    public string $vehicleTypeFilter = '';
    public string $routeFilter = '';
    public string $statusFilter = '';
    public string $dateFrom = '';
    public string $dateTo = '';

    // Temporary values for the modal
    public string $tempVehicleTypeFilter = '';
    public string $tempRouteFilter = '';
    public string $tempStatusFilter = '';
    public string $tempDateFrom = '';
    public string $tempDateTo = '';

    public string $exportVehicleType = '';
    public string $exportRoute = '';
    public string $exportDateFrom = '';
    public string $exportDateTo = '';
    public string $exportPaper = 'legal';
    public string $exportOrientation = 'portrait';

    public function mount()
    {
        // Defaults to today's activity; the operator can widen the range
        // from the Filters modal.
        $this->dateFrom = today()->toDateString();
        $this->dateTo   = today()->toDateString();
        $this->syncTempFromActual();
    }

    private function syncTempFromActual(): void
    {
        $this->tempVehicleTypeFilter = $this->vehicleTypeFilter;
        $this->tempRouteFilter = $this->routeFilter;
        $this->tempStatusFilter = $this->statusFilter;
        $this->tempDateFrom = $this->dateFrom;
        $this->tempDateTo = $this->dateTo;
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->sortBy = 'desc';
        $this->vehicleTypeFilter = '';
        $this->routeFilter = '';
        $this->statusFilter = '';
        $this->dateFrom = today()->toDateString();
        $this->dateTo   = today()->toDateString();
        $this->syncTempFromActual();
        $this->resetPage();
    }

    public function applyFilters(): void
    {
        $this->vehicleTypeFilter = $this->tempVehicleTypeFilter;
        $this->routeFilter = $this->tempRouteFilter;
        $this->statusFilter = $this->tempStatusFilter;
        $this->dateFrom = $this->tempDateFrom;
        $this->dateTo = $this->tempDateTo;
        $this->resetPage();
        $this->dispatch('close-filters-modal');
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedSortBy()
    {
        $this->resetPage();
    }

    #[Computed]
    public function hasActiveFilters(): bool
    {
        $today = today()->toDateString();

        return $this->search !== '' ||
               $this->sortBy !== 'desc' ||
               $this->vehicleTypeFilter !== '' ||
               $this->routeFilter !== '' ||
               $this->statusFilter !== '' ||
               $this->dateFrom !== $today ||
               $this->dateTo !== $today;
    }

    #[Computed]
    public function modalFilterCount(): int
    {
        $today = today()->toDateString();

        return count(array_filter([
            $this->vehicleTypeFilter,
            $this->routeFilter,
            $this->statusFilter,
        ])) + (($this->dateFrom !== $today || $this->dateTo !== $today) ? 1 : 0);
    }

    // Called right when the export modal opens, so its fields always start
    // aligned with whatever the operator is currently looking at.
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
        return route('operator.travel.history.export', array_filter([
            'from'         => $this->exportDateFrom,
            'to'           => $this->exportDateTo,
            'vehicle_type' => $this->exportVehicleType,
            'route'        => $this->exportRoute,
            'paper'        => $this->exportPaper,
            'orientation'  => $this->exportOrientation,
        ]));
    }

    // Same params as exportUrl, plus preview=1 so the controller streams the
    // PDF inline instead of forcing a download or logging it as an export.
    #[Computed]
    public function exportPreviewUrl(): string
    {
        return route('operator.travel.history.export', array_filter([
            'from'         => $this->exportDateFrom,
            'to'           => $this->exportDateTo,
            'vehicle_type' => $this->exportVehicleType,
            'route'        => $this->exportRoute,
            'paper'        => $this->exportPaper,
            'orientation'  => $this->exportOrientation,
            'preview'      => 1,
        ]));
    }

    #[Computed]
    public function baseQuery()
    {
        return Queue::query()
            ->where('user_id', auth()->id())
            ->when($this->search !== '', fn ($q) => $q->where(function ($qq) {
                $qq->where('plate_number', 'like', '%' . $this->search . '%')
                   ->orWhere('driver_name', 'like', '%' . $this->search . '%');
            }))
            ->when($this->vehicleTypeFilter !== '', fn ($q) => $q->where('vehicle_type', $this->vehicleTypeFilter))
            ->when($this->routeFilter !== '', fn ($q) => $q->where('destination', $this->routeFilter))
            ->when($this->statusFilter === 'departed', fn ($q) => $q->whereNotNull('time_departed'))
            ->when($this->statusFilter === 'queued', fn ($q) => $q->whereNull('time_departed'))
            ->when($this->dateFrom !== '', fn ($q) => $q->whereDate('time_queued', '>=', $this->dateFrom))
            ->when($this->dateTo !== '', fn ($q) => $q->whereDate('time_queued', '<=', $this->dateTo))
            ->when($this->sortBy === 'desc', fn ($q) => $q->latest('time_queued'))
            ->when($this->sortBy === 'asc', fn ($q) => $q->oldest('time_queued'));
    }

    #[Computed]
    public function travelRecords()
    {
        return $this->baseQuery->paginate(10);
    }

    #[Computed]
    public function stats()
    {
        $base = $this->baseQuery;

        return [
            'total'    => $base->clone()->count(),
            'departed' => $base->clone()->whereNotNull('time_departed')->count(),
            'queued'   => $base->clone()->whereNull('time_departed')->count(),
        ];
    }

    #[Computed]
    public function vehicleTypes()
    {
        return Queue::query()->where('user_id', auth()->id())->distinct()->pluck('vehicle_type');
    }

    #[Computed]
    public function routes()
    {
        return Queue::query()->where('user_id', auth()->id())->distinct()->pluck('destination');
    }

    public function render(): mixed
    {
        return $this->view()->layout('layouts.operator-layout');
    }
};
?>
<div>
    {{-- ====== PAGE HEADER (mini-navbar: heading left, actions right) ====== --}}
    <x-page-header
        heading="All queueing & departure activity across your vehicles."
        class="mb-6"
    >
        <div class="flex items-center gap-2 w-full sm:w-auto">
            <flux:modal.trigger name="travel-history-filters">
                <button
                    type="button"
                    class="relative flex items-center gap-1.5 sm:gap-2 px-2.5 sm:px-3.5 h-8 sm:h-9 rounded-lg bg-primary text-white border-0 hover:bg-primary-hover dark:bg-secondary dark:text-[var(--color-dark-primary)] dark:hover:bg-secondary-hover transition font-secondary text-xs sm:text-table-row shrink-0"
                >
                    <flux:icon.funnel class="w-3 h-3 sm:w-3.5 sm:h-3.5 text-white dark:text-[var(--color-dark-primary)]" />
                    <span class="hidden sm:inline">Filters</span>
                    @if ($this->modalFilterCount > 0)
                        <span class="flex items-center justify-center w-4 h-4 rounded-full bg-primary dark:bg-dark-txt-primary text-white dark:text-primary text-[10px] font-bold">
                            {{ $this->modalFilterCount }}
                        </span>
                    @endif
                </button>
            </flux:modal.trigger>

            <flux:modal.trigger name="export-travel-history">
                <button
                    type="button"
                    wire:click="prepareExportModal"
                    class="relative flex items-center gap-1.5 sm:gap-2 px-2.5 sm:px-3.5 h-8 sm:h-9 rounded-lg bg-black text-white border-0 hover:bg-neutral-800 dark:bg-white dark:text-black dark:hover:bg-neutral-200 transition font-secondary text-xs sm:text-table-row shrink-0"
                >
                    <flux:icon.arrow-down-tray class="w-3 h-3 sm:w-3.5 sm:h-3.5 text-white dark:text-black" />
                    <span class="hidden sm:inline">Export PDF</span>
                </button>
            </flux:modal.trigger>
        </div>
    </x-page-header>

    {{-- Mobile-visible heading, since the page header above is desktop-only --}}
    <div class="sm:hidden mb-4 pb-4 border-b border-light-bd-default dark:border-dark-bd-default">
        <x-heading
            size="xl"
            class="!font-primary !font-bold !text-light-txt-primary dark:!text-dark-txt-primary"
            style="font-size: var(--text-page-title)"
        >
            Travel History
        </x-heading>
    </div>

    {{-- ====== SEARCH & SORT (inline) ====== --}}
    <div class="flex flex-col sm:flex-row sm:items-center gap-3 mb-6">
        {{-- Search --}}
        <div class="flex-1">
            <flux:input
                wire:model.live.debounce.300ms="search"
                placeholder="Search plate number or driver..."
                class="w-full font-secondary text-table-row dark:bg-dark-secondary dark:border-dark-bd-default dark:text-dark-txt-primary"
                icon="magnifying-glass"
            />
        </div>

        {{-- Sort dropdown + Clear button --}}
        <div class="w-full sm:w-auto flex items-center gap-2">
            <flux:select
                wire:model.live="sortBy"
                size="sm"
                class="flex-1 sm:w-36 font-secondary text-table-row dark:bg-dark-secondary dark:border-dark-bd-default dark:text-dark-txt-primary"
            >
                <flux:select.option value="desc">Latest first</flux:select.option>
                <flux:select.option value="asc">Oldest first</flux:select.option>
            </flux:select>

            @if ($this->hasActiveFilters)
                <flux:button
                    wire:click="resetFilters"
                    size="sm"
                    variant="ghost"
                    icon="x-mark"
                    class="shrink-0 font-secondary"
                >
                    Clear
                </flux:button>
            @endif
        </div>
    </div>

    {{-- ====== FILTERS MODAL (date range lives in here) ====== --}}
    <flux:modal
        name="travel-history-filters"
        :closable="false"
        class="w-[calc(100%-2rem)] sm:max-w-lg md:max-w-2xl mx-auto rounded-xl overflow-hidden"
        x-on:close-filters-modal.window="$flux.modal('travel-history-filters').close()"
    >
        <div class="flex flex-col p-4 sm:p-6 !pr-4 sm:!pr-6 space-y-5 overflow-y-auto max-h-[70vh]">
            <!-- Header -->
            <div class="flex items-start justify-between">
                <div>
                    <flux:heading size="xl" class="!font-primary !font-bold text-light-txt-primary dark:text-dark-txt-primary">
                        Filter Travel Records
                    </flux:heading>
                    <flux:text class="mt-1 font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">
                        Refine your travel history.
                    </flux:text>
                </div>
                <flux:modal.close>
                    <button type="button" class="p-1 rounded-full hover:bg-light-subtle dark:hover:bg-dark-subtle text-light-txt-muted dark:text-dark-txt-muted -mt-1">
                        <flux:icon name="x-mark" class="w-5 h-5" />
                    </button>
                </flux:modal.close>
            </div>

            <!-- Fields (bound to temp properties) -->
            <div class="space-y-4">
                <flux:select wire:model.live="tempVehicleTypeFilter" label="Vehicle Type">
                    <flux:select.option value="">All vehicle types</flux:select.option>
                    @foreach ($this->vehicleTypes as $type)
                        <flux:select.option value="{{ $type }}">{{ $type }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model.live="tempRouteFilter" label="Route">
                    <flux:select.option value="">All routes</flux:select.option>
                    @foreach ($this->routes as $route)
                        <flux:select.option value="{{ $route }}">{{ $route }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model.live="tempStatusFilter" label="Status">
                    <flux:select.option value="">All statuses</flux:select.option>
                    <flux:select.option value="departed">Departed</flux:select.option>
                    <flux:select.option value="queued">Queued</flux:select.option>
                </flux:select>

                <div class="grid grid-cols-2 gap-3">
                    <flux:input
                        type="date"
                        wire:model.live="tempDateFrom"
                        label="From"
                        class="font-secondary text-table-row dark:bg-dark-secondary dark:border-dark-bd-default dark:text-dark-txt-primary"
                    />
                    <flux:input
                        type="date"
                        wire:model.live="tempDateTo"
                        label="To"
                        class="font-secondary text-table-row dark:bg-dark-secondary dark:border-dark-bd-default dark:text-dark-txt-primary"
                    />
                </div>
            </div>

            <!-- Footer -->
            <div class="flex flex-col-reverse sm:flex-row justify-end items-stretch sm:items-center gap-2 pt-2 border-t border-light-bd-default dark:border-dark-bd-default">
                <div class="flex-1 flex items-center">
                    <button
                        type="button"
                        wire:click="resetFilters"
                        class="font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted hover:text-light-txt-primary dark:hover:text-dark-txt-primary transition"
                    >
                        Reset all
                    </button>
                </div>
                <flux:modal.close class="w-full sm:w-auto">
                    <flux:button type="button" variant="ghost" class="w-full sm:w-auto justify-center font-secondary">
                        Cancel
                    </flux:button>
                </flux:modal.close>
                <flux:button
                    variant="primary"
                    wire:click="applyFilters"
                    class="font-secondary w-full sm:w-auto justify-center"
                >
                    Apply Filters
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Stats cards – consistent layout --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 sm:gap-3 mb-6">
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
    </div>

    {{-- Table – standard card with p-0 and sticky headers --}}
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
                        <flux:table.row :key="$record->id">
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

                            <flux:table.cell align="center" class="hidden md:table-cell px-2 md:px-4 py-1.5 md:py-2 font-secondary text-xs md:text-timestamp text-light-txt-muted dark:text-dark-txt-muted">
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
                                    <flux:badge size="sm" color="amber" icon="clock" class="font-secondary text-badge text-xs">{{ ucfirst($record->status) }}</flux:badge>
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
                                    @if ($this->hasActiveFilters)
                                        <button wire:click="resetFilters" class="font-secondary text-sm text-primary hover:text-primary-hover dark:text-primary dark:hover:text-primary-hover">
                                            Clear filters
                                        </button>
                                    @endif
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

    {{-- Export Modal – consistent with other modals --}}
    <flux:modal
        name="export-travel-history"
        :closable="false"
        class="w-[calc(100%-2rem)] sm:max-w-lg md:max-w-2xl mx-auto rounded-xl overflow-hidden"
    >
        <div class="flex flex-col p-4 sm:p-6 !pr-4 sm:!pr-6 space-y-5 overflow-y-auto max-h-[70vh]">
            <!-- Header -->
            <div class="flex items-start justify-between">
                <div>
                    <flux:heading size="xl" class="!font-primary !font-bold text-light-txt-primary dark:text-dark-txt-primary">
                        Export travel history
                    </flux:heading>
                    <flux:text class="mt-1 font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">
                        Choose what to include in the PDF. This starts from your current view — narrow it down, or leave as "All" for a full daily log.
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

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <flux:field>
                    <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">Paper size</flux:label>
                    <flux:select wire:model.live="exportPaper" size="sm" class="font-secondary text-table-row">
                        <flux:select.option value="letter">Letter</flux:select.option>
                        <flux:select.option value="legal">Legal</flux:select.option>
                        <flux:select.option value="a4">A4</flux:select.option>
                    </flux:select>
                </flux:field>
                <flux:field>
                    <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">Orientation</flux:label>
                    <flux:select wire:model.live="exportOrientation" size="sm" class="font-secondary text-table-row">
                        <flux:select.option value="portrait">Portrait</flux:select.option>
                        <flux:select.option value="landscape">Landscape</flux:select.option>
                    </flux:select>
                </flux:field>
            </div>

            <!-- Footer -->
            <div class="flex flex-col-reverse sm:flex-row justify-end items-stretch sm:items-center gap-2 pt-2 border-t border-light-bd-default dark:border-dark-bd-default">
                <flux:modal.close class="w-full sm:w-auto">
                    <flux:button type="button" variant="ghost" class="w-full sm:w-auto justify-center font-secondary">
                        Cancel
                    </flux:button>
                </flux:modal.close>
                <flux:button
                    type="button"
                    x-on:click="Flux.modal('export-travel-history').close(); Flux.modal('preview-travel-history').show()"
                    icon="eye"
                    variant="primary"
                    class="font-secondary w-full sm:w-auto justify-center"
                >
                    Preview
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal
        name="preview-travel-history"
        :closable="false"
        class="w-[calc(100%-2rem)] sm:max-w-3xl mx-auto rounded-xl overflow-hidden"
    >
        <div class="flex flex-col p-4 sm:p-6 !pr-4 sm:!pr-6 space-y-4">
            <div class="flex items-start justify-between">
                <flux:heading size="xl" class="!font-primary !font-bold text-light-txt-primary dark:text-dark-txt-primary">
                    Preview
                </flux:heading>
                <button
                    type="button"
                    x-on:click="Flux.modal('preview-travel-history').close()"
                    class="p-1 rounded-full hover:bg-light-subtle dark:hover:bg-dark-subtle text-light-txt-muted dark:text-dark-txt-muted -mt-1"
                >
                    <flux:icon name="x-mark" class="w-5 h-5" />
                </button>
            </div>

            <iframe
                wire:key="{{ $this->exportPreviewUrl }}"
                src="{{ $this->exportPreviewUrl }}"
                class="w-full h-[60vh] rounded-lg border border-light-bd-default dark:border-dark-bd-default bg-white"
            ></iframe>

            <div class="flex flex-col-reverse sm:flex-row justify-end items-stretch sm:items-center gap-2 pt-2 border-t border-light-bd-default dark:border-dark-bd-default">
                <flux:button
                    type="button"
                    x-on:click="Flux.modal('preview-travel-history').close(); Flux.modal('export-travel-history').show()"
                    variant="ghost"
                    class="w-full sm:w-auto justify-center font-secondary"
                >
                    Back to filters
                </flux:button>
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

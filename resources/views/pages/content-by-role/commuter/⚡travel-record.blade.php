<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Livewire\WithPagination;

use App\Models\TravelRecord;

new #[Layout('layouts.commuter-layout')] class extends Component
{
    use WithPagination;

    // Live‑updated properties (search & sort)
    public string $search = '';
    public string $sortBy = 'desc';

    // Applied filter values (only changed via modal)
    public string $vehicleTypeFilter = '';
    public string $statusFilter = '';
    public string $dateFrom = '';
    public string $dateTo = '';

    // Temporary values for the modal
    public string $tempVehicleTypeFilter = '';
    public string $tempStatusFilter = '';
    public string $tempDateFrom = '';
    public string $tempDateTo = '';

    public function mount()
    {
        $this->syncTempFromActual();
    }

    private function syncTempFromActual(): void
    {
        $this->tempVehicleTypeFilter = $this->vehicleTypeFilter;
        $this->tempStatusFilter = $this->statusFilter;
        $this->tempDateFrom = $this->dateFrom;
        $this->tempDateTo = $this->dateTo;
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->sortBy = 'desc';
        $this->vehicleTypeFilter = '';
        $this->statusFilter = '';
        $this->dateFrom = '';
        $this->dateTo = '';
        $this->syncTempFromActual();
        $this->resetPage();
    }

    public function applyFilters(): void
    {
        $this->vehicleTypeFilter = $this->tempVehicleTypeFilter;
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
        return $this->search !== '' ||
               $this->vehicleTypeFilter !== '' ||
               $this->statusFilter !== '' ||
               $this->dateFrom !== '' ||
               $this->dateTo !== '' ||
               $this->sortBy !== 'desc';
    }

    #[Computed]
    public function baseQuery()
    {
        return TravelRecord::with('queue', 'user.card')
            ->where('user_id', auth()->id())
            ->when($this->search !== '', fn ($q) => $q->where('destination', 'like', '%' . $this->search . '%'))
            ->when($this->vehicleTypeFilter !== '', fn ($q) => $q->where('vehicle_type', $this->vehicleTypeFilter))
            ->when($this->statusFilter === 'completed', fn ($q) => $q->whereHas('queue', fn ($qq) => $qq->whereNotNull('time_departed')))
            ->when($this->statusFilter === 'in_transit', fn ($q) => $q->where(function ($qq) {
                $qq->whereDoesntHave('queue')
                   ->orWhereHas('queue', fn ($qqq) => $qqq->whereNull('time_departed'));
            }))
            ->when($this->dateFrom !== '', fn ($q) => $q->whereDate('departed_at', '>=', $this->dateFrom))
            ->when($this->dateTo !== '', fn ($q) => $q->whereDate('departed_at', '<=', $this->dateTo))
            ->when($this->sortBy === 'desc', fn ($q) => $q->latest())
            ->when($this->sortBy === 'asc', fn ($q) => $q->oldest());
    }

    #[Computed]
    public function getTravelRecords()
    {
        return $this->baseQuery->paginate(10);
    }

    #[Computed]
    public function stats()
    {
        $base = $this->baseQuery;

        return [
            'total'      => $base->clone()->count(),
            'completed'  => $base->clone()->whereHas('queue', fn ($q) => $q->whereNotNull('time_departed'))->count(),
            'in_transit' => $base->clone()->where(function ($q) {
                $q->whereDoesntHave('queue')
                  ->orWhereHas('queue', fn ($qq) => $qq->whereNull('time_departed'));
            })->count(),
            'fare_spent' => $base->clone()->sum('amount'),
        ];
    }

    #[Computed]
    public function vehicleTypes()
    {
        return TravelRecord::where('user_id', auth()->id())->distinct()->pluck('vehicle_type')->filter()->values();
    }
};
?>

<div>
    {{-- ====== PAGE HEADER ====== --}}
    <x-page-header
        heading="My Travels"
        class="mb-6"
    >
        <flux:modal.trigger name="commuter-travel-filters">
            <button
                type="button"
                class="relative flex items-center gap-1.5 sm:gap-2 px-2.5 sm:px-3.5 h-8 sm:h-9 rounded-lg bg-primary text-white border-0 hover:bg-primary-hover dark:bg-secondary dark:text-[var(--color-dark-primary)] dark:hover:bg-secondary-hover transition font-secondary text-xs sm:text-table-row shrink-0"
            >
                <flux:icon.funnel class="w-3 h-3 sm:w-3.5 sm:h-3.5 text-white dark:text-[var(--color-dark-primary)]" />
                <span class="hidden sm:inline">Filters</span>
                @php
                    $modalFilterCount = count(array_filter([
                        $this->vehicleTypeFilter,
                        $this->statusFilter,
                        $this->dateFrom,
                        $this->dateTo,
                    ]));
                @endphp
                @if ($modalFilterCount > 0)
                    <span class="flex items-center justify-center w-4 h-4 rounded-full bg-primary dark:bg-dark-txt-primary text-white dark:text-primary text-[10px] font-bold">
                        {{ $modalFilterCount }}
                    </span>
                @endif
            </button>
        </flux:modal.trigger>
    </x-page-header>

    {{-- Mobile-visible heading, since the page header above is desktop-only --}}
    <div class="sm:hidden mb-4 pb-4 border-b border-light-bd-default dark:border-dark-bd-default">
        <x-heading
            size="xl"
            class="!font-primary !font-bold !text-light-txt-primary dark:!text-dark-txt-primary"
            style="font-size: var(--text-page-title)"
        >
            My Travels
        </x-heading>
    </div>

    {{-- ===================== STATS ===================== --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-2 sm:gap-3 mb-6">
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
                    Completed
                </x-text>
            </div>
            <x-text class="font-primary text-stat-value font-bold text-success dark:text-dark-success block">
                {{ $this->stats['completed'] }}
            </x-text>
        </flux:card>

        <flux:card class="p-3 sm:p-4">
            <div class="flex items-center gap-1.5 sm:gap-2 mb-1.5">
                <div class="flex items-center justify-center w-6 h-6 sm:w-7 sm:h-7 rounded-lg bg-warning/10 dark:bg-dark-warning/20 shrink-0">
                    <flux:icon.clock class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-warning dark:text-dark-warning" />
                </div>
                <x-text class="font-secondary text-xs sm:text-stat-label text-light-txt-muted dark:text-dark-txt-muted">
                    In transit
                </x-text>
            </div>
            <x-text class="font-primary text-stat-value font-bold text-warning dark:text-dark-warning block">
                {{ $this->stats['in_transit'] }}
            </x-text>
        </flux:card>

        <flux:card class="p-3 sm:p-4">
            <div class="flex items-center gap-1.5 sm:gap-2 mb-1.5">
                <div class="flex items-center justify-center w-6 h-6 sm:w-7 sm:h-7 rounded-lg bg-info/10 dark:bg-dark-info/20 shrink-0">
                    <flux:icon.banknotes class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-info dark:text-dark-info" />
                </div>
                <x-text class="font-secondary text-xs sm:text-stat-label text-light-txt-muted dark:text-dark-txt-muted">
                    Fare spent
                </x-text>
            </div>
            <x-text class="font-primary text-stat-value font-bold text-light-txt-primary dark:text-dark-txt-primary block">
                ₱{{ number_format($this->stats['fare_spent'], 2) }}
            </x-text>
        </flux:card>
    </div>

    {{-- ====== SEARCH & SORT (inline) ====== --}}
    <div class="flex flex-col sm:flex-row sm:items-center gap-3 mb-6">
        {{-- Search --}}
        <div class="flex-1">
            <flux:input
                wire:model.live.debounce.300ms="search"
                placeholder="Search destination..."
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

    {{-- ====== FILTERS MODAL ====== --}}
    <flux:modal
        name="commuter-travel-filters"
        :closable="false"
        class="w-[calc(100%-2rem)] sm:max-w-lg md:max-w-2xl mx-auto rounded-xl overflow-hidden"
        x-on:close-filters-modal.window="$flux.modal('commuter-travel-filters').close()"
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
                    <flux:select.option value="">All types</flux:select.option>
                    @foreach ($this->vehicleTypes as $type)
                        <flux:select.option value="{{ $type }}">{{ $type }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model.live="tempStatusFilter" label="Status">
                    <flux:select.option value="">All statuses</flux:select.option>
                    <flux:select.option value="completed">Completed</flux:select.option>
                    <flux:select.option value="in_transit">In Transit</flux:select.option>
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

    {{-- ===================== TABLE ===================== --}}
    {{-- Only Destination, Vehicle Type, Fare Paid, and Status show on small screens.
         Plate No., Boarded, and Departed reveal progressively at sm/md/lg so the
         table rarely needs horizontal scrolling on phones. --}}
    <flux:card class="overflow-x-auto overflow-y-hidden p-0">
        <flux:table>
            <flux:table.columns sticky class="bg-light-secondary dark:bg-dark-secondary">
                <flux:table.column align="center" class="px-1! sm:px-2! md:px-4! py-2">Destination</flux:table.column>
                <flux:table.column align="center" class="px-1 sm:px-2 md:px-4 py-2">Vehicle Type</flux:table.column>
                <flux:table.column align="center" class="hidden sm:table-cell px-2 md:px-4 py-2">Plate No.</flux:table.column>
                <flux:table.column align="center" class="px-1 sm:px-2 md:px-4 py-2">Fare Paid</flux:table.column>
                <flux:table.column align="center" class="hidden md:table-cell px-4 py-2">Boarded</flux:table.column>
                <flux:table.column align="center" class="hidden lg:table-cell px-4 py-2">Departed</flux:table.column>
                <flux:table.column align="center" class="px-1! sm:px-2! md:px-4! py-2">Status</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->getTravelRecords as $record)
                    <flux:table.row :key="$record->id">
                        <flux:table.cell align="center" class="px-1 sm:px-2 md:px-4 py-1.5 md:py-2 font-secondary text-xs md:text-table-row text-light-txt-body dark:text-dark-txt-body">
                            {{ $record->queue->destination ?? $record->destination ?? '—' }}
                        </flux:table.cell>

                        <flux:table.cell align="center" class="px-1 sm:px-2 md:px-4 py-1.5 md:py-2">
                            <flux:badge size="sm" color="{{ ($record->queue->vehicle_type ?? $record->vehicle_type) === 'Bus' ? 'blue' : 'amber' }}">
                                {{ $record->queue->vehicle_type ?? $record->vehicle_type }}
                            </flux:badge>
                        </flux:table.cell>

                        <flux:table.cell align="center" class="hidden sm:table-cell px-2 md:px-4 py-1.5 md:py-2 font-secondary text-xs md:text-table-row text-light-txt-body dark:text-dark-txt-body">
                            {{ $record->queue->plate_number ?? $record->plate_number ?? '—' }}
                        </flux:table.cell>

                        <flux:table.cell align="center" class="px-1 sm:px-2 md:px-4 py-1.5 md:py-2 font-secondary text-xs md:text-table-row tabular-nums font-medium text-light-txt-primary dark:text-dark-txt-primary">
                            ₱{{ number_format($record->amount ?? 0, 2) }}
                        </flux:table.cell>

                        <flux:table.cell align="center" class="hidden md:table-cell px-4 py-1.5 md:py-2 font-secondary text-xs md:text-table-row text-light-txt-muted dark:text-dark-txt-muted tabular-nums">
                            {{ $record->queue->time_queued?->format('M d, Y \a\t g:i a') ?? '—' }}
                        </flux:table.cell>

                        <flux:table.cell align="center" class="hidden lg:table-cell px-4 py-1.5 md:py-2 font-secondary text-xs md:text-table-row text-light-txt-muted dark:text-dark-txt-muted tabular-nums">
                            {{ $record->queue->time_departed?->format('M d, Y \a\t g:i a') ?? $record->departed_at?->format('M d, Y \a\t g:i a') ?? '—' }}
                        </flux:table.cell>

                        <flux:table.cell align="center" class="px-1! sm:px-2! md:px-4! py-1.5 md:py-2">
                            @if ($record->queue->time_departed ?? $record->departed_at ?? null)
                                <flux:badge size="sm" color="green" icon="check">Completed</flux:badge>
                            @else
                                <flux:badge size="sm" color="amber" icon="clock">In Transit</flux:badge>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7" class="text-center py-12">
                            <div class="flex flex-col items-center justify-center gap-2">
                                <flux:icon.document-text class="w-8 h-8 text-light-txt-muted dark:text-dark-txt-muted" />
                                @if ($this->hasActiveFilters)
                                    <p class="font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">No travel records match your filters.</p>
                                    <button wire:click="resetFilters" class="font-secondary text-sm text-primary hover:text-primary-hover dark:text-primary dark:hover:text-primary-hover">
                                        Clear filters
                                    </button>
                                @else
                                    <p class="font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">No travel records yet.</p>
                                @endif
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <div class="mt-4">
        {{ $this->getTravelRecords->links() }}
    </div>
</div>
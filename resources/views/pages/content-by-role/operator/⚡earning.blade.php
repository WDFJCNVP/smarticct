<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Livewire\WithPagination;

use App\Models\TravelRecord;
use App\Models\CardTransaction;
use App\Models\Card;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;

new #[Layout('layouts.operator-layout')] class extends Component
{
    use WithPagination;

    public string $dateFrom = '';
    public string $dateTo = '';
    public string $vehicleTypeFilter = '';
    public string $routeFilter = '';
    public string $driverFilter = '';
    public string $paymentMethodFilter = '';

    public function mount()
    {
        $this->dateFrom = today()->toDateString();
        $this->dateTo   = today()->toDateString();
    }

    public function updatedDateFrom() { $this->resetPage(); $this->pushChartUpdates(); }
    public function updatedDateTo() { $this->resetPage(); $this->pushChartUpdates(); }
    public function updatedVehicleTypeFilter() { $this->resetPage(); $this->pushChartUpdates(); }
    public function updatedRouteFilter() { $this->resetPage(); $this->pushChartUpdates(); }
    public function updatedDriverFilter() { $this->resetPage(); $this->pushChartUpdates(); }
    public function updatedPaymentMethodFilter() { $this->resetPage(); $this->pushChartUpdates(); }

    public function resetDateRange()
    {
        $this->dateFrom = today()->toDateString();
        $this->dateTo   = today()->toDateString();
        $this->resetPage();
        $this->pushChartUpdates();
    }

    public function setRangeThisWeek()
    {
        $this->dateFrom = now()->startOfWeek()->toDateString();
        $this->dateTo   = now()->endOfWeek()->toDateString();
        $this->resetPage();
        $this->pushChartUpdates();
    }

    public function setRangeThisMonth()
    {
        $this->dateFrom = now()->startOfMonth()->toDateString();
        $this->dateTo   = now()->endOfMonth()->toDateString();
        $this->resetPage();
        $this->pushChartUpdates();
    }

    public function clearFilters()
    {
        $this->reset(['vehicleTypeFilter', 'routeFilter', 'driverFilter', 'paymentMethodFilter']);
        $this->resetPage();
        $this->pushChartUpdates();
    }

    // wire:ignore keeps Livewire from touching the chart canvases on every
    // re-render (Chart.js owns that DOM), so filter/date changes have to
    // reach the charts explicitly via these browser events instead.
    private function pushChartUpdates(): void
    {
        unset($this->earningsOverTime, $this->paymentSplit, $this->topDriversChart, $this->stats, $this->paymentMethodMap, $this->driverPerformance);

        $this->dispatch('earnings-chart-updated', chart: $this->earningsOverTime);
        $this->dispatch('payment-split-chart-updated', chart: $this->paymentSplit);
        $this->dispatch('top-drivers-chart-updated', chart: $this->topDriversChart);
    }

    #[Computed]
    public function activeFilterCount()
    {
        return collect([$this->vehicleTypeFilter, $this->routeFilter, $this->driverFilter, $this->paymentMethodFilter])
            ->filter(fn ($v) => filled($v))
            ->count();
    }

    #[Computed]
    public function operatorCard(): ?Card
    {
        return Card::where('user_id', Auth::id())->first();
    }

    /**
     * Every commuter fare that lands in this operator's card balance leaves a
     * 'fare_earning' CardTransaction behind, tagged by how it was collected:
     *   FARE-{travel_record_id}      -> commuter tapped their own card
     *   FARECASH-{travel_record_id}  -> a cashier logged cash on the commuter's behalf
     * A TravelRecord with neither (OPFARE-...) is cash the operator logged
     * directly themselves — still a cash fare, just never touched a card.
     * reference_id/reference_type aren't mass-assignable on CardTransaction,
     * so reference_no is the only reliable link back to the travel record.
     */
    #[Computed]
    public function paymentMethodMap(): array
    {
        $card = $this->operatorCard;

        $cardIds = [];

        if ($card) {
            CardTransaction::where('card_id', $card->id)
                ->where('transaction_type', 'fare_earning')
                ->where('reference_no', 'like', 'FARE-%')
                ->whereDate('transaction_time', '>=', $this->dateFrom)
                ->whereDate('transaction_time', '<=', $this->dateTo)
                ->pluck('reference_no')
                ->each(function ($referenceNo) use (&$cardIds) {
                    $cardIds[] = (int) str_replace('FARE-', '', $referenceNo);
                });
        }

        return $cardIds;
    }

    private function baseQuery()
    {
        return TravelRecord::query()
            ->whereHas('queue', fn ($q) => $q->where('user_id', Auth::id()))
            ->whereDate('created_at', '>=', $this->dateFrom)
            ->whereDate('created_at', '<=', $this->dateTo)
            ->when($this->vehicleTypeFilter, fn ($q, $v) => $q->where('vehicle_type', $v))
            ->when($this->routeFilter, fn ($q, $v) => $q->where('destination', $v))
            ->when($this->driverFilter, fn ($q, $v) => $q->where('driver_name', $v));
    }

    private function filteredQuery()
    {
        $query = $this->baseQuery();
        $cardIds = $this->paymentMethodMap;

        if ($this->paymentMethodFilter === 'card') {
            $query->whereIn('id', $cardIds ?: [0]);
        } elseif ($this->paymentMethodFilter === 'cash') {
            $query->whereNotIn('id', $cardIds ?: [0]);
        }

        return $query;
    }

    #[Computed]
    public function trips()
    {
        return $this->filteredQuery()->latest('created_at')->paginate(10);
    }

    public function paymentMethodFor(int $travelRecordId): string
    {
        return in_array($travelRecordId, $this->paymentMethodMap, true) ? 'card' : 'cash';
    }

    #[Computed]
    public function stats(): array
    {
        $rows = $this->baseQuery()->get(['id', 'amount']);
        $cardIds = $this->paymentMethodMap;

        $total     = (float) $rows->sum('amount');
        $cardTotal = (float) $rows->whereIn('id', $cardIds)->sum('amount');
        $cashTotal = $total - $cardTotal;
        $trips     = $rows->count();

        return [
            'total'    => $total,
            'card'     => $cardTotal,
            'cash'     => $cashTotal,
            'trips'    => $trips,
            'avg'      => $trips > 0 ? $total / $trips : 0,
            'cardPct'  => $total > 0 ? round($cardTotal / $total * 100) : 0,
            'cashPct'  => $total > 0 ? round($cashTotal / $total * 100) : 0,
        ];
    }

    #[Computed]
    public function earningsOverTime(): array
    {
        $start = Carbon::parse($this->dateFrom)->startOfDay();
        $end   = Carbon::parse($this->dateTo)->endOfDay();

        // Cap the series so a wide "all time" range still renders a readable chart.
        if ($start->diffInDays($end) > 60) {
            $start = $end->copy()->subDays(60)->startOfDay();
        }

        $sums = $this->baseQuery()
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('DATE(created_at) as day, SUM(amount) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $labels = [];
        $data = [];

        for ($cursor = $start->copy(); $cursor->lte($end); $cursor->addDay()) {
            $key = $cursor->format('Y-m-d');
            $labels[] = $cursor->format('M j');
            $data[] = round((float) ($sums[$key] ?? 0), 2);
        }

        return compact('labels', 'data');
    }

    #[Computed]
    public function paymentSplit(): array
    {
        $stats = $this->stats;

        return [
            'labels' => ['Card', 'Cash'],
            'data'   => [round($stats['card'], 2), round($stats['cash'], 2)],
        ];
    }

    #[Computed]
    public function driverPerformance()
    {
        return $this->baseQuery()
            ->whereNotNull('driver_name')
            ->selectRaw('driver_name, COUNT(*) as trips, SUM(amount) as earnings, AVG(amount) as avg_fare')
            ->groupBy('driver_name')
            ->orderByDesc('earnings')
            ->get();
    }

    #[Computed]
    public function topDriversChart(): array
    {
        $top = $this->driverPerformance->take(8);

        return [
            'labels' => $top->pluck('driver_name')->toArray(),
            'data'   => $top->pluck('earnings')->map(fn ($v) => round((float) $v, 2))->toArray(),
        ];
    }

    #[Computed]
    public function vehicleTypes()
    {
        return TravelRecord::whereHas('queue', fn ($q) => $q->where('user_id', Auth::id()))
            ->whereNotNull('vehicle_type')->distinct()->orderBy('vehicle_type')->pluck('vehicle_type');
    }

    #[Computed]
    public function routes()
    {
        return TravelRecord::whereHas('queue', fn ($q) => $q->where('user_id', Auth::id()))
            ->whereNotNull('destination')->distinct()->orderBy('destination')->pluck('destination');
    }

    #[Computed]
    public function drivers()
    {
        return TravelRecord::whereHas('queue', fn ($q) => $q->where('user_id', Auth::id()))
            ->whereNotNull('driver_name')->distinct()->orderBy('driver_name')->pluck('driver_name');
    }
};
?>
<div>
    {{-- Header --}}
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        <div>
            <x-heading
                size="xl"
                class="!font-primary !font-bold !text-light-txt-primary dark:!text-dark-txt-primary"
                style="font-size: var(--text-page-title)"
            >
                Earnings
            </x-heading>
            <x-text variant="subtle" class="!font-secondary mt-1 block" style="font-size: var(--text-helper)">
                Fare revenue from your vehicles — cash and card — with driver performance.
            </x-text>
        </div>

        <div class="flex flex-col sm:flex-row sm:items-center gap-2 w-full lg:w-auto">
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
                <flux:button wire:click="resetDateRange" size="sm" variant="ghost" class="font-secondary flex-1 sm:flex-none justify-center">
                    Today
                </flux:button>
                <flux:button wire:click="setRangeThisWeek" size="sm" variant="ghost" class="font-secondary flex-1 sm:flex-none justify-center">
                    This week
                </flux:button>
                <flux:button wire:click="setRangeThisMonth" size="sm" variant="ghost" class="font-secondary flex-1 sm:flex-none justify-center">
                    This month
                </flux:button>
            </div>
        </div>
    </div>

    {{-- Filters --}}
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
            wire:model.live="driverFilter"
            size="sm"
            placeholder="All drivers"
            class="w-full sm:w-40 font-secondary text-table-row dark:bg-dark-secondary dark:border-dark-bd-default dark:text-dark-txt-primary"
        >
            <flux:select.option value="">All drivers</flux:select.option>
            @foreach ($this->drivers as $driver)
                <flux:select.option value="{{ $driver }}">{{ $driver }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select
            wire:model.live="paymentMethodFilter"
            size="sm"
            placeholder="All payment methods"
            class="w-full sm:w-44 font-secondary text-table-row dark:bg-dark-secondary dark:border-dark-bd-default dark:text-dark-txt-primary"
        >
            <flux:select.option value="">All payment methods</flux:select.option>
            <flux:select.option value="card">Card</flux:select.option>
            <flux:select.option value="cash">Cash</flux:select.option>
        </flux:select>

        @if ($this->activeFilterCount > 0)
            <flux:button wire:click="clearFilters" size="sm" variant="ghost" icon="x-mark" class="font-secondary shrink-0">
                Clear
            </flux:button>
        @endif
    </div>

    {{-- Stats cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-2 sm:gap-3 mt-6 mb-5">
        <flux:card class="p-3 sm:p-4">
            <div class="flex items-center gap-1.5 sm:gap-2 mb-1.5">
                <div class="flex items-center justify-center w-6 h-6 sm:w-7 sm:h-7 rounded-lg bg-primary/10 dark:bg-primary/20 shrink-0">
                    <flux:icon.banknotes class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-primary dark:text-dark-txt-primary" />
                </div>
                <x-text class="font-secondary text-xs sm:text-stat-label text-light-txt-muted dark:text-dark-txt-muted">
                    Total earnings
                </x-text>
            </div>
            <x-text class="font-primary text-stat-value font-bold text-light-txt-primary dark:text-dark-txt-primary block">
                ₱{{ number_format($this->stats['total'], 2) }}
            </x-text>
        </flux:card>

        <flux:card class="p-3 sm:p-4">
            <div class="flex items-center gap-1.5 sm:gap-2 mb-1.5">
                <div class="flex items-center justify-center w-6 h-6 sm:w-7 sm:h-7 rounded-lg bg-info/10 dark:bg-dark-info/20 shrink-0">
                    <flux:icon.credit-card class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-info dark:text-dark-info" />
                </div>
                <x-text class="font-secondary text-xs sm:text-stat-label text-light-txt-muted dark:text-dark-txt-muted">
                    Card
                </x-text>
            </div>
            <x-text class="font-primary text-stat-value font-bold text-info dark:text-dark-info block">
                ₱{{ number_format($this->stats['card'], 2) }}
            </x-text>
            <x-text class="font-secondary text-xs text-light-txt-muted dark:text-dark-txt-muted">
                {{ $this->stats['cardPct'] }}% of total
            </x-text>
        </flux:card>

        <flux:card class="p-3 sm:p-4">
            <div class="flex items-center gap-1.5 sm:gap-2 mb-1.5">
                <div class="flex items-center justify-center w-6 h-6 sm:w-7 sm:h-7 rounded-lg bg-success/10 dark:bg-dark-success/20 shrink-0">
                    <flux:icon.banknotes class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-success dark:text-dark-success" />
                </div>
                <x-text class="font-secondary text-xs sm:text-stat-label text-light-txt-muted dark:text-dark-txt-muted">
                    Cash
                </x-text>
            </div>
            <x-text class="font-primary text-stat-value font-bold text-success dark:text-dark-success block">
                ₱{{ number_format($this->stats['cash'], 2) }}
            </x-text>
            <x-text class="font-secondary text-xs text-light-txt-muted dark:text-dark-txt-muted">
                {{ $this->stats['cashPct'] }}% of total
            </x-text>
        </flux:card>

        <flux:card class="p-3 sm:p-4">
            <div class="flex items-center gap-1.5 sm:gap-2 mb-1.5">
                <div class="flex items-center justify-center w-6 h-6 sm:w-7 sm:h-7 rounded-lg bg-warning/10 dark:bg-dark-warning/20 shrink-0">
                    <flux:icon.users class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-warning dark:text-dark-warning" />
                </div>
                <x-text class="font-secondary text-xs sm:text-stat-label text-light-txt-muted dark:text-dark-txt-muted">
                    Trips
                </x-text>
            </div>
            <x-text class="font-primary text-stat-value font-bold text-light-txt-primary dark:text-dark-txt-primary block">
                {{ $this->stats['trips'] }}
            </x-text>
        </flux:card>

        <flux:card class="p-3 sm:p-4">
            <div class="flex items-center gap-1.5 sm:gap-2 mb-1.5">
                <div class="flex items-center justify-center w-6 h-6 sm:w-7 sm:h-7 rounded-lg bg-secondary/10 shrink-0">
                    <flux:icon.calculator class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-secondary" />
                </div>
                <x-text class="font-secondary text-xs sm:text-stat-label text-light-txt-muted dark:text-dark-txt-muted">
                    Avg. fare
                </x-text>
            </div>
            <x-text class="font-primary text-stat-value font-bold text-light-txt-primary dark:text-dark-txt-primary block">
                ₱{{ number_format($this->stats['avg'], 2) }}
            </x-text>
        </flux:card>
    </div>

    {{-- Charts --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-3 mb-6">
        <flux:card class="p-4 lg:col-span-2">
            <div class="flex items-center justify-between gap-2 mb-2">
                <x-text class="font-secondary text-sm sm:text-card-title font-semibold text-light-txt-primary dark:text-dark-txt-primary">
                    Earnings over time
                </x-text>
            </div>
            <div
                wire:ignore
                x-data="lineChart(@js($this->earningsOverTime))"
                @earnings-chart-updated.window="update($event.detail.chart)"
            >
                <div class="relative h-44 sm:h-56">
                    <canvas x-ref="canvas" x-show="!empty"></canvas>
                    <div x-show="empty" class="absolute inset-0 flex flex-col items-center justify-center gap-1.5 text-center px-4">
                        <flux:icon.chart-bar class="w-6 h-6 text-light-txt-muted dark:text-dark-txt-muted" />
                        <span class="font-secondary text-xs sm:text-sm text-light-txt-muted dark:text-dark-txt-muted">No earnings yet</span>
                    </div>
                </div>
            </div>
        </flux:card>

        <flux:card class="p-4">
            <div class="flex items-center justify-between gap-2 mb-2">
                <x-text class="font-secondary text-sm sm:text-card-title font-semibold text-light-txt-primary dark:text-dark-txt-primary">
                    Cash vs. card
                </x-text>
            </div>
            <div
                wire:ignore
                x-data="donutChart(@js($this->paymentSplit), { colorKeys: ['info', 'success'] })"
                @payment-split-chart-updated.window="update($event.detail.chart)"
            >
                <div class="relative h-44 sm:h-56">
                    <canvas x-ref="canvas" x-show="!empty"></canvas>
                    <div x-show="empty" class="absolute inset-0 flex flex-col items-center justify-center gap-1.5 text-center px-4">
                        <flux:icon.chart-pie class="w-6 h-6 text-light-txt-muted dark:text-dark-txt-muted" />
                        <span class="font-secondary text-xs sm:text-sm text-light-txt-muted dark:text-dark-txt-muted">No earnings yet</span>
                    </div>
                </div>
            </div>
        </flux:card>
    </div>

    {{-- Driver performance --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-3 mb-6">
        <flux:card class="p-4 lg:col-span-2">
            <div class="flex items-center justify-between gap-2 mb-2">
                <x-text class="font-secondary text-sm sm:text-card-title font-semibold text-light-txt-primary dark:text-dark-txt-primary">
                    Top drivers by earnings
                </x-text>
            </div>
            <div
                wire:ignore
                x-data="barChart(@js($this->topDriversChart), { label: 'Earnings (₱)', colorKey: 'primary' })"
                @top-drivers-chart-updated.window="update($event.detail.chart)"
            >
                <div class="relative h-44 sm:h-56">
                    <canvas x-ref="canvas" x-show="!empty"></canvas>
                    <div x-show="empty" class="absolute inset-0 flex flex-col items-center justify-center gap-1.5 text-center px-4">
                        <flux:icon.chart-bar class="w-6 h-6 text-light-txt-muted dark:text-dark-txt-muted" />
                        <span class="font-secondary text-xs sm:text-sm text-light-txt-muted dark:text-dark-txt-muted">No driver data yet</span>
                    </div>
                </div>
            </div>
        </flux:card>

        <flux:card class="p-4 overflow-hidden">
            <x-text class="font-secondary text-sm sm:text-card-title font-semibold text-light-txt-primary dark:text-dark-txt-primary block mb-3">
                Driver leaderboard
            </x-text>

            <div class="space-y-2 max-h-56 overflow-y-auto pr-1">
                @forelse ($this->driverPerformance as $index => $driver)
                    <div class="flex items-center justify-between gap-2 py-1.5 {{ $index > 0 ? 'border-t border-light-bd-default dark:border-dark-bd-default' : '' }}">
                        <div class="flex items-center gap-2 min-w-0">
                            @if ($index === 0)
                                <flux:badge size="sm" color="amber" class="font-secondary text-badge text-xs shrink-0">1st</flux:badge>
                            @elseif ($index === 1)
                                <flux:badge size="sm" color="zinc" class="font-secondary text-badge text-xs shrink-0">2nd</flux:badge>
                            @elseif ($index === 2)
                                <flux:badge size="sm" color="orange" class="font-secondary text-badge text-xs shrink-0">3rd</flux:badge>
                            @else
                                <span class="font-secondary text-xs text-light-txt-muted dark:text-dark-txt-muted w-7 text-center shrink-0">{{ $index + 1 }}</span>
                            @endif
                            <div class="min-w-0">
                                <x-text class="font-secondary text-sm text-light-txt-body dark:text-dark-txt-primary block truncate">
                                    {{ $driver->driver_name }}
                                </x-text>
                                <x-text class="font-secondary text-xs text-light-txt-muted dark:text-dark-txt-muted">
                                    {{ $driver->trips }} {{ Str::plural('trip', $driver->trips) }} · avg ₱{{ number_format($driver->avg_fare, 0) }}
                                </x-text>
                            </div>
                        </div>
                        <x-text class="font-primary text-sm font-bold text-light-txt-primary dark:text-dark-txt-primary shrink-0">
                            ₱{{ number_format($driver->earnings, 2) }}
                        </x-text>
                    </div>
                @empty
                    <div class="flex flex-col items-center justify-center py-8 gap-2">
                        <flux:icon.user-group class="w-6 h-6 text-light-txt-muted dark:text-dark-txt-muted" />
                        <x-text class="font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted text-center">
                            No driver activity in this range.
                        </x-text>
                    </div>
                @endforelse
            </div>
        </flux:card>
    </div>

    {{-- Trips table --}}
    <flux:card class="mb-4 p-0! overflow-hidden">
        <div class="overflow-x-auto">
            <flux:table container:class="md:max-h-160">
                <flux:table.columns sticky class="bg-light-secondary/50 items-center bg-light-subtle/50 dark:bg-dark-secondary/50 font-secondary text-nav-label text-light-txt-muted dark:text-dark-txt-muted">
                    <flux:table.column align="center" class="px-2 md:px-4 py-2">Date</flux:table.column>
                    <flux:table.column align="center" class="px-2 md:px-4 py-2">Driver</flux:table.column>
                    <flux:table.column align="center" class="hidden md:table-cell px-2 md:px-4 py-2">Plate No.</flux:table.column>
                    <flux:table.column align="center" class="hidden md:table-cell px-2 md:px-4 py-2">Route</flux:table.column>
                    <flux:table.column align="center" class="hidden md:table-cell px-2 md:px-4 py-2">Vehicle Type</flux:table.column>
                    <flux:table.column align="center" class="px-2 md:px-4 py-2">Method</flux:table.column>
                    <flux:table.column align="center" class="px-2 md:px-4 py-2">Fare</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($this->trips as $trip)
                        <flux:table.row :key="$trip->id">
                            <flux:table.cell align="center" class="px-2 md:px-4 py-1.5 md:py-2 font-secondary text-xs md:text-timestamp text-light-txt-muted dark:text-dark-txt-muted">
                                {{ $trip->created_at?->format('M d, Y g:i a') ?? '—' }}
                            </flux:table.cell>

                            <flux:table.cell align="center" class="px-2 md:px-4 py-1.5 md:py-2 font-secondary text-xs md:text-table-row text-light-txt-body dark:text-dark-txt-primary">
                                {{ $trip->driver_name }}
                            </flux:table.cell>

                            <flux:table.cell align="center" class="hidden md:table-cell px-2 md:px-4 py-1.5 md:py-2 font-mono text-xs md:text-table-row text-light-txt-body dark:text-dark-txt-primary">
                                {{ $trip->plate_number }}
                            </flux:table.cell>

                            <flux:table.cell align="center" class="hidden md:table-cell px-2 md:px-4 py-1.5 md:py-2 font-secondary text-xs md:text-table-row text-light-txt-body dark:text-dark-txt-primary">
                                {{ $trip->destination }}
                            </flux:table.cell>

                            <flux:table.cell align="center" class="hidden md:table-cell px-2 md:px-4 py-1.5 md:py-2">
                                <flux:badge size="sm" color="{{ $trip->vehicle_type === 'Bus' ? 'blue' : 'amber' }}" class="font-secondary text-badge text-xs">
                                    {{ $trip->vehicle_type }}
                                </flux:badge>
                            </flux:table.cell>

                            <flux:table.cell align="center" class="px-2 md:px-4 py-1.5 md:py-2">
                                @if ($this->paymentMethodFor($trip->id) === 'card')
                                    <flux:badge size="sm" color="blue" icon="credit-card" class="font-secondary text-badge text-xs">Card</flux:badge>
                                @else
                                    <flux:badge size="sm" color="green" icon="banknotes" class="font-secondary text-badge text-xs">Cash</flux:badge>
                                @endif
                            </flux:table.cell>

                            <flux:table.cell align="center" class="px-2 md:px-4 py-1.5 md:py-2 font-primary text-xs md:text-table-row font-semibold text-light-txt-primary dark:text-dark-txt-primary">
                                ₱{{ number_format($trip->amount, 2) }}
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="7" class="px-2 md:px-4 py-4">
                                <div class="flex flex-col items-center justify-center py-6 md:py-12 gap-2">
                                    <flux:icon.banknotes class="w-6 h-6 md:w-8 md:h-8 text-light-txt-muted dark:text-dark-txt-muted" />
                                    <x-text class="font-secondary text-sm md:text-table-row text-light-txt-muted dark:text-dark-txt-muted">
                                        No fare payments match the current filters.
                                    </x-text>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>

        @if ($this->trips->hasPages())
            <div class="flex flex-wrap items-center justify-end gap-2 px-3 sm:px-4 py-2 border-t border-light-bd-default dark:border-dark-bd-default bg-light-secondary dark:bg-dark-secondary">
                {{ $this->trips->links() }}
            </div>
        @endif
    </flux:card>
</div>
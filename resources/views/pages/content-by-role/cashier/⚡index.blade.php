<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use App\Models\Queue;
use App\Models\CardTransaction;
use App\Models\CashTransaction;
use App\Models\TopUpTransaction;
use Illuminate\Support\Facades\Auth;

new #[Layout('layouts.cashier-layout')] class extends Component
{
    public string $range = '7'; // days: 7, 14, 30

    // ===================== KPI CARDS =====================

    #[Computed]
    public function myTransactionsToday()
    {
        // Top-ups aren't included here — top_up_transactions has no cashier-attribution
        // column, so it can't be scoped to "mine". See topUpsToday() below instead.
        $cardFees = CardTransaction::where('processed_by', Auth::id())
            ->where('transaction_type', 'operator_payment')
            ->whereDate('transaction_time', today())
            ->count();

        $cashFees = CashTransaction::where('processed_by', Auth::id())
            ->where('status', 'success')
            ->whereDate('created_at', today())
            ->count();

        return $cardFees + $cashFees;
    }

    #[Computed]
    public function myCollectedToday()
    {
        $cardFees = CardTransaction::where('processed_by', Auth::id())
            ->where('transaction_type', 'operator_payment')
            ->whereDate('transaction_time', today())
            ->sum('amount');

        $cashFees = CashTransaction::where('processed_by', Auth::id())
            ->where('status', 'success')
            ->whereDate('created_at', today())
            ->sum('amount');

        return $cardFees + $cashFees;
    }

    #[Computed]
    public function topUpsToday()
    {
        // Terminal-wide — top_up_transactions has no processed_by column
        return TopUpTransaction::where('status', 'paid')
            ->whereDate('created_at', today())
            ->count();
    }

    #[Computed]
    public function myQueueFeesToday()
    {
        $card = CardTransaction::where('processed_by', Auth::id())
            ->where('transaction_type', 'operator_payment')
            ->whereDate('transaction_time', today())
            ->count();

        $cash = CashTransaction::where('processed_by', Auth::id())
            ->where('status', 'success')
            ->whereDate('created_at', today())
            ->count();

        return $card + $cash;
    }

    #[Computed]
    public function vehiclesLoggedToday()
    {
        // Terminal-wide — queues has no cashier-attribution column yet
        return Queue::whereDate('time_queued', today())->count();
    }

    #[Computed]
    public function failedTransactionsToday()
    {
        return CardTransaction::whereIn('status', ['failed', 'insufficient_balance'])
            ->whereDate('created_at', today())
            ->count();
    }

    // ===================== CHARTS =====================

    #[Computed]
    public function myCollectionsOverTime()
    {
        $days = max(1, (int) $this->range);
        $start = today()->subDays($days - 1);
        $end = today();

        $cardFees = CardTransaction::where('processed_by', Auth::id())
            ->where('transaction_type', 'operator_payment')
            ->whereBetween('transaction_time', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->selectRaw('DATE(transaction_time) as day, COUNT(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $cashFees = CashTransaction::where('processed_by', Auth::id())
            ->where('status', 'success')
            ->whereBetween('created_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $labels = [];
        $data = [];

        for ($cursor = $start->copy(); $cursor->lte($end); $cursor->addDay()) {
            $key = $cursor->format('Y-m-d');
            $labels[] = $cursor->format('M j');
            $data[] = (int) (($cardFees[$key] ?? 0) + ($cashFees[$key] ?? 0));
        }

        return compact('labels', 'data');
    }

    #[Computed]
    public function myCollectionsByMode()
    {
        $days = max(1, (int) $this->range);
        $start = today()->subDays($days - 1)->startOfDay();
        $end = today()->endOfDay();

        $cash = CashTransaction::where('processed_by', Auth::id())
            ->where('status', 'success')
            ->whereBetween('created_at', [$start, $end])
            ->sum('amount');

        $cardFees = CardTransaction::where('processed_by', Auth::id())
            ->where('transaction_type', 'operator_payment')
            ->whereBetween('transaction_time', [$start, $end])
            ->sum('amount');

        $labels = [];
        $data = [];

        if ($cash > 0) {
            $labels[] = 'Cash';
            $data[] = (float) $cash;
        }

        if ($cardFees > 0) {
            $labels[] = 'Card';
            $data[] = (float) $cardFees;
        }

        if (empty($labels)) {
            $labels = ['No collections yet'];
            $data = [1];
        }

        return compact('labels', 'data');
    }

    #[Computed]
    public function todayQueueVolumeByVehicleType()
    {
        // Terminal-wide, today only — mirrors the admin chart but scoped to this shift
        $counts = Queue::whereDate('time_queued', today())
            ->selectRaw('vehicle_type, count(*) as total')
            ->groupBy('vehicle_type')
            ->pluck('total', 'vehicle_type');

        return [
            'labels' => $counts->keys()->map(fn ($t) => ucfirst(str_replace('_', ' ', $t)))->values()->toArray(),
            'data' => $counts->values()->toArray(),
        ];
    }

    // ===================== TABLES =====================

    #[Computed]
    public function todaysVehicleLog()
    {
        // Terminal-wide daily log — replaces the manual logbook
        return Queue::whereDate('time_queued', today())
            ->latest('time_queued')
            ->get(['id', 'vehicle_type', 'destination', 'status', 'time_queued', 'time_departed']);
    }

    #[Computed]
    public function myRecentTransactions()
    {
        $cardFees = CardTransaction::where('processed_by', Auth::id())
            ->where('transaction_type', 'operator_payment')
            ->latest('transaction_time')
            ->limit(5)
            ->get(['id', 'amount', 'transaction_time'])
            ->map(fn ($t) => (object) [
                'label' => 'Queue fee',
                'mode' => 'Card',
                'amount' => $t->amount,
                'occurred_at' => $t->transaction_time,
            ]);

        $cashFees = CashTransaction::where('processed_by', Auth::id())
            ->where('status', 'success')
            ->latest('created_at')
            ->limit(5)
            ->get(['id', 'amount', 'created_at'])
            ->map(fn ($t) => (object) [
                'label' => 'Queue fee',
                'mode' => 'Cash',
                'amount' => $t->amount,
                'occurred_at' => $t->created_at,
            ]);

        return $cardFees->concat($cashFees)
            ->sortByDesc('occurred_at')
            ->take(5)
            ->values();
    }

    // ===================== EXPORT (stub) =====================

    public function exportVehicleLog()
    {
        // TODO: wire this to a real export — e.g. Maatwebsite/Excel or a signed CSV route.
        // Left as an action stub since no export package/route was specified.
        $this->dispatch('vehicle-log-export-requested');
    }

    // ===================== FILTER UPDATES =====================

    public function updatedRange()
    {
        $this->dispatch('collections-chart-updated', chart: $this->myCollectionsOverTime);
        $this->dispatch('collections-mode-chart-updated', chart: $this->myCollectionsByMode);
    }

    // ===================== TREND (REAL DATA — REPLACES "LIVE") =====================

    #[Computed]
    public function collectionsTrend()
    {
        $days = max(1, (int) $this->range);
        $currentStart = today()->subDays($days - 1)->startOfDay();
        $currentEnd = today()->endOfDay();
        $previousStart = today()->subDays(($days * 2) - 1)->startOfDay();
        $previousEnd = today()->subDays($days)->endOfDay();

        $count = function ($start, $end) {
            $card = CardTransaction::where('processed_by', Auth::id())
                ->where('transaction_type', 'operator_payment')
                ->whereBetween('transaction_time', [$start, $end])
                ->count();
            $cash = CashTransaction::where('processed_by', Auth::id())
                ->where('status', 'success')
                ->whereBetween('created_at', [$start, $end])
                ->count();

            return $card + $cash;
        };

        $current = $count($currentStart, $currentEnd);
        $previous = $count($previousStart, $previousEnd);

        if ($previous == 0) {
            return $current > 0 ? 100.0 : null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    // ===================== LIVE REFRESH =====================

    #[On('echo:vehicle-queue,.QueuedVehicleEvent')]
    #[On('echo:card-transaction-created,.CardTransactionCreated')]
    #[On('echo:cash-transaction-created,.CashTransactionCreated')]
    public function refreshDashboard()
    {
        unset(
            $this->myTransactionsToday,
            $this->myCollectedToday,
            $this->topUpsToday,
            $this->collectionsTrend,
            $this->myQueueFeesToday,
            $this->vehiclesLoggedToday,
            $this->failedTransactionsToday,
            $this->myCollectionsOverTime,
            $this->myCollectionsByMode,
            $this->todayQueueVolumeByVehicleType,
            $this->todaysVehicleLog,
            $this->myRecentTransactions,
        );

        $this->dispatch('collections-chart-updated', chart: $this->myCollectionsOverTime);
        $this->dispatch('collections-mode-chart-updated', chart: $this->myCollectionsByMode);
        $this->dispatch('queue-volume-chart-updated', chart: $this->todayQueueVolumeByVehicleType);
        $this->dispatch('status-strip-updated', transactions: $this->myTransactionsToday, collected: $this->myCollectedToday);
    }
};
?>

<div>
    {{-- ===================== SCOPED STYLES ===================== --}}
    <style>
        .flap-flip { animation: flap-flip 0.5s ease-out; }
        @keyframes flap-flip {
            0%   { transform: rotateX(90deg); opacity: 0.3; }
            60%  { transform: rotateX(0deg); opacity: 1; }
            100% { transform: rotateX(0deg); opacity: 1; }
        }
        @media (prefers-reduced-motion: reduce) {
            .flap-flip { animation: none; }
        }
        .zone-bar { width: 4px; height: 1.1rem; border-radius: 2px; }
        .zone-rule { border: none; border-top: 1.5px dashed; opacity: 0.35; margin-top: 0.5rem; margin-bottom: 1rem; }
        .live-badge {
            display: inline-flex; align-items: center; gap: 0.375rem;
            padding: 0.1875rem 0.625rem; border-radius: 9999px;
        }
    </style>

    {{-- ===================== HEADER ===================== --}}
    <div class="mb-6">
        <div class="flex items-center justify-between gap-3 sm:gap-4">
            <x-pages-heading
                heading="Cashier Dashboard"
                description="Your shift transactions and queue activity."
                class="text-xl sm:text-2xl font-extrabold"
            />

            <div class="flex items-center gap-2 sm:gap-3 shrink-0">
                <div class="hidden sm:flex flex-col items-end">
                    <span
                        class="text-xs text-light-txt-muted dark:text-dark-txt-muted font-secondary leading-none mb-0.5"
                        x-data="{ date: '' }"
                        x-init="date = new Date().toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })"
                        x-text="date"
                    ></span>
                    <div
                        class="flex items-center gap-1.5 sm:gap-2 font-primary text-base sm:text-xl font-bold tabular-nums text-light-txt-primary dark:text-dark-txt-primary whitespace-nowrap"
                        x-data="{
                            now: '',
                            tick() {
                                this.now = new Date().toLocaleString('en-US', {
                                    hour: '2-digit', minute: '2-digit', second: '2-digit',
                                });
                            },
                        }"
                        x-init="tick(); setInterval(() => tick(), 1000)"
                    >
                        <span class="relative flex h-1.5 w-1.5 sm:h-2 sm:w-2 shrink-0">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-success dark:bg-dark-success opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-1.5 w-1.5 sm:h-2 sm:w-2 bg-success dark:bg-dark-success"></span>
                        </span>
                        <span x-text="now"></span>
                    </div>
                </div>

                <button
                    type="button"
                    class="relative flex items-center justify-center w-8 h-8 sm:w-9 sm:h-9 rounded-lg border border-light-bd-default dark:border-dark-bd-default text-light-txt-muted dark:text-dark-txt-muted hover:bg-light-subtle dark:hover:bg-dark-subtle transition shrink-0"
                    aria-label="Notifications"
                >
                    <flux:icon.bell class="w-3.5 h-3.5 sm:w-4 sm:h-4" />
                    <span class="absolute top-1.5 right-1.5 w-1.5 h-1.5 rounded-full bg-danger dark:bg-dark-danger"></span>
                </button>

                <flux:modal.trigger name="cashier-filters">
                    <button
                        type="button"
                        class="relative flex items-center gap-1.5 sm:gap-2 px-2.5 sm:px-3.5 h-8 sm:h-9 rounded-lg border border-light-bd-default dark:border-dark-bd-default text-light-txt-body dark:text-dark-txt-body hover:bg-light-subtle dark:hover:bg-dark-subtle transition font-secondary text-xs sm:text-table-row shrink-0"
                    >
                        <flux:icon.funnel class="w-3 h-3 sm:w-3.5 sm:h-3.5 text-light-txt-muted dark:text-dark-txt-muted" />
                        <span class="hidden sm:inline">Filters</span>
                        @if ((int) $this->range !== 7)
                            <span class="flex items-center justify-center w-4 h-4 rounded-full bg-primary dark:bg-dark-txt-primary text-white dark:text-primary text-[10px] font-bold">1</span>
                        @endif
                    </button>
                </flux:modal.trigger>
            </div>
        </div>
        <hr class="zone-rule border-light-bd-default dark:border-dark-bd-default mt-4 mb-0">
    </div>

    {{-- ===================== FILTERS MODAL ===================== --}}
    <flux:modal name="cashier-filters" class="md:w-96">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Filters</flux:heading>
                <flux:subheading>Narrow down your collections history.</flux:subheading>
            </div>
            <div class="space-y-4">
                <flux:select wire:model.live="range" label="Date range">
                    <flux:select.option value="7">Last 7 days</flux:select.option>
                    <flux:select.option value="14">Last 14 days</flux:select.option>
                    <flux:select.option value="30">Last 30 days</flux:select.option>
                </flux:select>
            </div>
            <div class="flex items-center justify-between pt-4 border-t border-light-bd-default dark:border-dark-bd-default">
                <button
                    type="button"
                    wire:click="$set('range', '7')"
                    class="font-secondary text-table-row text-light-txt-muted dark:text-dark-txt-muted hover:text-light-txt-primary dark:hover:text-dark-txt-primary transition"
                >
                    Reset to 7 days
                </button>
                <flux:modal.close>
                    <flux:button variant="primary">Done</flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>

    {{-- ===================== LIVE STATUS STRIP ===================== --}}
    <div
        class="mt-6 mb-6 rounded-xl border border-light-bd-default dark:border-dark-bd-default bg-primary text-white overflow-hidden"
        x-data="{
            transactions: @js($this->myTransactionsToday),
            collected: @js($this->myCollectedToday),
            flipT: false,
            flipC: false,
        }"
        @status-strip-updated.window="
            if ($event.detail.transactions !== transactions) { transactions = $event.detail.transactions; flipT = true; setTimeout(() => flipT = false, 500); }
            if ($event.detail.collected !== collected) { collected = $event.detail.collected; flipC = true; setTimeout(() => flipC = false, 500); }
        "
    >
        <div class="flex flex-col sm:flex-row divide-y sm:divide-y-0 sm:divide-x divide-white/15">
            <div class="flex items-center gap-3 px-5 py-4 flex-1">
                <span class="relative flex h-2 w-2 shrink-0">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-success opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2 w-2 bg-success"></span>
                </span>
                <div>
                    <div class="font-secondary text-nav-label font-semibold uppercase tracking-wide text-white/80">My Transactions Today</div>
                    <div class="font-primary text-3xl font-extrabold tabular-nums" :class="{ 'flap-flip': flipT }" x-text="transactions"></div>
                </div>
            </div>
            <div class="flex items-center gap-3 px-5 py-4 flex-1">
                <flux:icon.banknotes class="w-5 h-5 text-white/70 shrink-0" />
                <div>
                    <div class="font-secondary text-nav-label font-semibold uppercase tracking-wide text-white/80">Total Collected Today</div>
                    <div class="font-primary text-3xl font-extrabold tabular-nums" :class="{ 'flap-flip': flipC }" x-text="'₱' + Number(collected).toLocaleString('en-US', { minimumFractionDigits: 2 })"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- ===================== ZONE: OVERVIEW ===================== --}}
    <div class="flex items-center gap-2.5 text-light-txt-primary dark:text-dark-txt-primary">
        <span class="zone-bar bg-primary dark:bg-dark-txt-primary"></span>
        <span class="font-secondary text-nav-label font-bold uppercase tracking-widest">Overview</span>
    </div>
    <hr class="zone-rule border-light-bd-default dark:border-dark-bd-default">

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2 sm:gap-3 mb-8">
        <flux:card class="p-3 sm:p-4">
            <div class="flex items-center gap-1.5 sm:gap-2.5">
                <div class="flex items-center justify-center w-6 h-6 sm:w-8 sm:h-8 rounded-lg bg-success/10 dark:bg-dark-success/20 shrink-0">
                    <flux:icon.credit-card class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-success dark:text-dark-success" />
                </div>
                <x-text class="font-secondary text-xs sm:text-stat-label font-medium text-light-txt-body dark:text-dark-txt-body">Top-ups today (terminal)</x-text>
            </div>
            <x-text class="font-primary text-xl sm:text-stat-value font-bold tabular-nums text-success dark:text-dark-success block mt-2 sm:mt-3">
                {{ $this->topUpsToday }}
            </x-text>
        </flux:card>

        <flux:card class="p-3 sm:p-4">
            <div class="flex items-center gap-1.5 sm:gap-2.5">
                <div class="flex items-center justify-center w-6 h-6 sm:w-8 sm:h-8 rounded-lg bg-info/10 dark:bg-dark-info/20 shrink-0">
                    <flux:icon.ticket class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-info dark:text-dark-info" />
                </div>
                <x-text class="font-secondary text-xs sm:text-stat-label font-medium text-light-txt-body dark:text-dark-txt-body">Queue fees collected</x-text>
            </div>
            <x-text class="font-primary text-xl sm:text-stat-value font-bold tabular-nums text-info dark:text-dark-info block mt-2 sm:mt-3">
                {{ $this->myQueueFeesToday }}
            </x-text>
        </flux:card>

        <flux:card class="p-3 sm:p-4">
            <div class="flex items-center gap-1.5 sm:gap-2.5">
                <div class="flex items-center justify-center w-6 h-6 sm:w-8 sm:h-8 rounded-lg bg-primary/10 dark:bg-primary/20 shrink-0">
                    <flux:icon.truck class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-primary dark:text-dark-txt-primary" />
                </div>
                <x-text class="font-secondary text-xs sm:text-stat-label font-medium text-light-txt-body dark:text-dark-txt-body">Vehicles logged today</x-text>
            </div>
            <x-text class="font-primary text-xl sm:text-stat-value font-bold tabular-nums text-light-txt-primary dark:text-dark-txt-primary block mt-2 sm:mt-3">
                {{ $this->vehiclesLoggedToday }}
            </x-text>
        </flux:card>

        <flux:card class="p-3 sm:p-4">
            <div class="flex items-center gap-1.5 sm:gap-2.5">
                <div class="flex items-center justify-center w-6 h-6 sm:w-8 sm:h-8 rounded-lg bg-danger/10 dark:bg-dark-danger/20 shrink-0">
                    <flux:icon.exclamation-triangle class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-danger dark:text-dark-danger" />
                </div>
                <x-text class="font-secondary text-xs sm:text-stat-label font-medium text-light-txt-body dark:text-dark-txt-body">Failed transactions today</x-text>
            </div>
            <x-text class="font-primary text-xl sm:text-stat-value font-bold tabular-nums text-danger dark:text-dark-danger block mt-2 sm:mt-3">
                {{ $this->failedTransactionsToday }}
            </x-text>
        </flux:card>
    </div>

    {{-- ===================== CHARTS: MY COLLECTIONS ===================== --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-3 mb-8">
        <flux:card class="p-4 lg:col-span-2">
            <div class="flex items-center justify-between gap-2 mb-2">
                <x-text class="font-secondary text-sm sm:text-card-title font-semibold text-light-txt-primary dark:text-dark-txt-primary">
                    My collections over time
                </x-text>
                <x-dashboard.trend-pill :value="$this->collectionsTrend" :suffix="$this->range . 'd'" />
            </div>
            <div
                wire:ignore
                x-data="lineChart(@js($this->myCollectionsOverTime))"
                @collections-chart-updated.window="update($event.detail.chart)"
            >
                <div class="relative h-44 sm:h-56">
                    <canvas x-ref="canvas" x-show="!empty"></canvas>
                    <div x-show="empty" class="absolute inset-0 flex flex-col items-center justify-center gap-1.5 text-center px-4">
                        <flux:icon.chart-bar class="w-6 h-6 text-light-txt-muted dark:text-dark-txt-muted" />
                        <span class="font-secondary text-xs sm:text-sm text-light-txt-muted dark:text-dark-txt-muted">No data yet</span>
                    </div>
                </div>
            </div>
        </flux:card>

        <flux:card class="p-4">
            <div class="flex items-center justify-between gap-2 mb-2">
                <x-text class="font-secondary text-sm sm:text-card-title font-semibold text-light-txt-primary dark:text-dark-txt-primary">
                    Collections by mode
                </x-text>
                <span class="font-secondary text-xs font-medium text-light-txt-muted dark:text-dark-txt-muted">{{ array_sum($this->myCollectionsByMode['data']) }} total</span>
            </div>
            <div
                wire:ignore
                x-data="donutChart(@js($this->myCollectionsByMode))"
                @collections-mode-chart-updated.window="update($event.detail.chart)"
            >
                <div class="relative h-44 sm:h-56">
                    <canvas x-ref="canvas" x-show="!empty"></canvas>
                    <div x-show="empty" class="absolute inset-0 flex flex-col items-center justify-center gap-1.5 text-center px-4">
                        <flux:icon.chart-pie class="w-6 h-6 text-light-txt-muted dark:text-dark-txt-muted" />
                        <span class="font-secondary text-xs sm:text-sm text-light-txt-muted dark:text-dark-txt-muted">No data yet</span>
                    </div>
                </div>
            </div>
        </flux:card>
    </div>

    {{-- ===================== ZONE: QUEUE & VEHICLE LOG ===================== --}}
    <div class="flex items-center gap-2.5 text-light-txt-primary dark:text-dark-txt-primary">
        <span class="zone-bar bg-info dark:bg-dark-info"></span>
        <span class="font-secondary text-nav-label font-bold uppercase tracking-widest">Queue &amp; Vehicle Log</span>
    </div>
    <hr class="zone-rule border-light-bd-default dark:border-dark-bd-default">

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-3 mb-8">
        <flux:card class="p-4">
            <div class="flex items-center justify-between gap-2 mb-2">
                <x-text class="font-secondary text-sm sm:text-card-title font-semibold text-light-txt-primary dark:text-dark-txt-primary">
                    Today's tickets by vehicle type
                </x-text>
                <span class="font-secondary text-xs font-medium text-light-txt-muted dark:text-dark-txt-muted">{{ array_sum($this->todayQueueVolumeByVehicleType['data']) }} today</span>
            </div>
            <div
                wire:ignore
                x-data="barChart(@js($this->todayQueueVolumeByVehicleType), { label: 'Vehicles', colorKey: 'info' })"
                @queue-volume-chart-updated.window="update($event.detail.chart)"
            >
                <div class="relative h-44 sm:h-56">
                    <canvas x-ref="canvas" x-show="!empty"></canvas>
                    <div x-show="empty" class="absolute inset-0 flex flex-col items-center justify-center gap-1.5 text-center px-4">
                        <flux:icon.chart-bar class="w-6 h-6 text-light-txt-muted dark:text-dark-txt-muted" />
                        <span class="font-secondary text-xs sm:text-sm text-light-txt-muted dark:text-dark-txt-muted">No data yet</span>
                    </div>
                </div>
            </div>
        </flux:card>

        {{-- Today's vehicle log — replaces the manual logbook, exportable --}}
        <flux:card class="p-0 lg:col-span-2 overflow-hidden">
            <div class="flex items-center justify-between px-4 pt-4">
                <x-text class="font-secondary text-sm sm:text-card-title font-semibold text-light-txt-primary dark:text-dark-txt-primary">
                    Today's vehicle log
                </x-text>
                <button
                    type="button"
                    wire:click="exportVehicleLog"
                    class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg border border-light-bd-default dark:border-dark-bd-default text-light-txt-muted dark:text-dark-txt-muted hover:bg-light-subtle dark:hover:bg-dark-subtle transition font-secondary text-xs"
                >
                    <flux:icon.arrow-down-tray class="w-3.5 h-3.5" />
                    Export
                </button>
            </div>
            <div class="overflow-x-auto mt-2 max-h-72">
                <table class="w-full font-secondary text-table-row">
                    <thead>
                        <tr class="text-left text-light-txt-body dark:text-dark-txt-body border-b border-light-bd-default dark:border-dark-bd-default">
                            <th class="py-2 px-4 font-semibold">Type</th>
                            <th class="py-2 px-4 font-semibold">Destination</th>
                            <th class="py-2 px-4 font-semibold">Status</th>
                            <th class="py-2 px-4 font-semibold text-right">Queued</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->todaysVehicleLog as $entry)
                            <tr class="border-b border-light-bd-default/50 dark:border-dark-bd-default/50 last:border-0">
                                <td class="py-2.5 px-4 text-light-txt-body dark:text-dark-txt-body">{{ ucfirst(str_replace('_', ' ', $entry->vehicle_type)) }}</td>
                                <td class="py-2.5 px-4 text-light-txt-muted dark:text-dark-txt-muted">{{ $entry->destination }}</td>
                                <td class="py-2.5 px-4">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-badge font-medium bg-light-subtle dark:bg-dark-subtle text-light-txt-primary dark:text-dark-txt-primary">
                                        {{ ucfirst($entry->status) }}
                                    </span>
                                </td>
                                <td class="py-2.5 px-4 text-right font-secondary text-timestamp tabular-nums text-light-txt-muted dark:text-dark-txt-muted whitespace-nowrap">
                                    {{ $entry->time_queued?->format('g:i A') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-8 text-center text-light-txt-muted dark:text-dark-txt-muted">
                                    No vehicles logged yet today.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="h-1"></div>
        </flux:card>
    </div>

    {{-- ===================== ZONE: RECENT ACTIVITY ===================== --}}
    <div class="flex items-center gap-2.5 text-light-txt-primary dark:text-dark-txt-primary">
        <span class="zone-bar bg-secondary"></span>
        <span class="font-secondary text-nav-label font-bold uppercase tracking-widest">Recent Activity</span>
    </div>
    <hr class="zone-rule border-light-bd-default dark:border-dark-bd-default">

    <flux:card class="p-0 overflow-hidden">
        <div class="px-4 pt-4">
            <x-text class="font-secondary text-sm sm:text-card-title font-semibold text-light-txt-primary dark:text-dark-txt-primary">
                My recent transactions
            </x-text>
        </div>
        <div class="overflow-x-auto mt-2">
            <table class="w-full font-secondary text-table-row">
                <thead>
                    <tr class="text-left text-light-txt-body dark:text-dark-txt-body border-b border-light-bd-default dark:border-dark-bd-default">
                        <th class="py-2 px-4 font-semibold">Type</th>
                        <th class="py-2 px-4 font-semibold">Mode</th>
                        <th class="py-2 px-4 font-semibold text-right">Amount</th>
                        <th class="py-2 px-4 font-semibold text-right">When</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->myRecentTransactions as $txn)
                        <tr class="border-b border-light-bd-default/50 dark:border-dark-bd-default/50 last:border-0">
                            <td class="py-2.5 px-4 text-light-txt-body dark:text-dark-txt-body">{{ $txn->label }}</td>
                            <td class="py-2.5 px-4">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-badge font-medium bg-light-subtle dark:bg-dark-subtle text-light-txt-primary dark:text-dark-txt-primary">
                                    {{ $txn->mode }}
                                </span>
                            </td>
                            <td class="py-2.5 px-4 text-right font-primary text-stat-value font-semibold text-success dark:text-dark-success">
                                ₱{{ number_format($txn->amount, 2) }}
                            </td>
                            <td class="py-2.5 px-4 text-right font-secondary text-timestamp tabular-nums text-light-txt-muted dark:text-dark-txt-muted whitespace-nowrap">
                                {{ \Illuminate\Support\Carbon::parse($txn->occurred_at)->diffForHumans() }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-8 text-center text-light-txt-muted dark:text-dark-txt-muted">
                                No transactions processed yet today.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="h-1"></div>
    </flux:card>
</div>
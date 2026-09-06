<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use App\Models\TravelRecord;
use App\Models\TripRequest;
use App\Models\Card;
use App\Models\CardTransaction;
use App\Models\Queue;
use Illuminate\Support\Facades\Auth;

new #[Layout('layouts.commuter-layout')] class extends Component
{
    public string $range = '7'; // days: 7, 14, 30

    // ===================== KPI CARDS =====================

    #[Computed]
    public function totalTrips()
    {
        return TravelRecord::where('user_id', Auth::id())->count();
    }

    #[Computed]
    public function activeRentalRequests()
    {
        return TripRequest::where('user_id', Auth::id())
            ->whereIn('status', ['pending', 'accept'])
            ->count();
    }

    #[Computed]
    public function cardPointsBalance()
    {
        $card = Card::where('user_id', Auth::id())->first();
        return $card ? $card->balance : null;
    }

    #[Computed]
    public function pointsSpent()
    {
        $card = Card::where('user_id', Auth::id())->first();
        if (!$card) return 0;

        return CardTransaction::where('card_id', $card->id)
            ->where('transaction_type', 'fare_payment')
            ->sum('amount');
    }

    // ===================== CHARTS =====================

    #[Computed]
    public function tripsOverTime()
    {
        $days = max(1, (int) $this->range);
        $start = today()->subDays($days - 1);
        $end = today();

        $counts = TravelRecord::where('user_id', Auth::id())
            ->whereBetween('created_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $labels = [];
        $data = [];
        for ($cursor = $start->copy(); $cursor->lte($end); $cursor->addDay()) {
            $key = $cursor->format('Y-m-d');
            $labels[] = $cursor->format('M j');
            $data[] = (int) ($counts[$key] ?? 0);
        }

        return compact('labels', 'data');
    }

    #[Computed]
    public function mostUsedRoutes()
    {
        $counts = TravelRecord::where('user_id', Auth::id())
            ->selectRaw('destination, COUNT(*) as total')
            ->groupBy('destination')
            ->orderByDesc('total')
            ->limit(5)
            ->pluck('total', 'destination');

        return [
            'labels' => $counts->keys()->values()->toArray(),
            'data'   => $counts->values()->toArray(),
        ];
    }

    #[Computed]
    public function rentalRequestStatusSplit()
    {
        $counts = TripRequest::where('user_id', Auth::id())
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'labels' => $counts->keys()->map(fn ($s) => ucfirst($s))->values()->toArray(),
            'data'   => $counts->values()->toArray(),
        ];
    }

    // ===================== RECENT ACTIVITY =====================

    #[Computed]
    public function recentTrips()
    {
        return TravelRecord::where('user_id', Auth::id())
            ->latest()
            ->limit(5)
            ->get(['id', 'destination', 'vehicle_type', 'created_at']);
    }

    #[Computed]
    public function recentRentalRequests()
    {
        return TripRequest::where('user_id', Auth::id())
            ->latest()
            ->limit(5)
            ->get(['id', 'pick_up_location', 'drop_off_location', 'status', 'created_at']);
    }

    #[Computed]
    public function recentCardTransactions()
    {
        $card = Card::where('user_id', Auth::id())->first();
        if (!$card) return collect();

        return CardTransaction::where('card_id', $card->id)
            ->latest()
            ->limit(5)
            ->get(['id', 'transaction_type', 'amount', 'created_at']);
    }

    // ===================== QUEUE SNAPSHOT =====================

    #[Computed]
    public function queueSnapshot()
    {
        return Queue::whereIn('status', ['staging', 'loading'])
            ->selectRaw('vehicle_type, destination, COUNT(*) as count')
            ->groupBy('vehicle_type', 'destination')
            ->orderBy('vehicle_type')
            ->get();
    }

    // ===================== RANGE UPDATE =====================

    public function updatedRange()
    {
        $this->dispatch('trips-chart-updated', chart: $this->tripsOverTime);
        $this->dispatch('routes-chart-updated', chart: $this->mostUsedRoutes);
        $this->dispatch('rental-status-chart-updated', chart: $this->rentalRequestStatusSplit);
    }

    // ===================== TREND (REAL DATA — REPLACES "LIVE") =====================

    #[Computed]
    public function tripsTrend()
    {
        $days = max(1, (int) $this->range);
        $currentStart = today()->subDays($days - 1)->startOfDay();
        $currentEnd = today()->endOfDay();
        $previousStart = today()->subDays(($days * 2) - 1)->startOfDay();
        $previousEnd = today()->subDays($days)->endOfDay();

        $current = TravelRecord::where('user_id', Auth::id())->whereBetween('created_at', [$currentStart, $currentEnd])->count();
        $previous = TravelRecord::where('user_id', Auth::id())->whereBetween('created_at', [$previousStart, $previousEnd])->count();

        if ($previous == 0) {
            return $current > 0 ? 100.0 : null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    // ===================== LIVE REFRESH =====================

    #[On('echo:queue-updated,.QueueUpdated')]
    #[On('echo:travel-record-created,.TravelRecordCreated')]
    #[On('echo:card-transaction-created,.CardTransactionCreated')]
    public function refreshDashboard()
    {
        unset(
            $this->totalTrips,
            $this->activeRentalRequests,
            $this->cardPointsBalance,
            $this->tripsTrend,
            $this->pointsSpent,
            $this->tripsOverTime,
            $this->mostUsedRoutes,
            $this->rentalRequestStatusSplit,
            $this->recentTrips,
            $this->recentRentalRequests,
            $this->recentCardTransactions,
            $this->queueSnapshot,
        );

        $this->dispatch('trips-chart-updated', chart: $this->tripsOverTime);
        $this->dispatch('routes-chart-updated', chart: $this->mostUsedRoutes);
        $this->dispatch('rental-status-chart-updated', chart: $this->rentalRequestStatusSplit);
        $this->dispatch('kpi-updated', [
            'trips'   => $this->totalTrips,
            'rentals' => $this->activeRentalRequests,
            'points'  => $this->cardPointsBalance,
            'spent'   => $this->pointsSpent,
        ]);
        $this->dispatch('queue-snapshot-updated', snapshot: $this->queueSnapshot);
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

    {{-- ===================== MINI-NAVBAR ===================== --}}
    <x-page-header
        heading="Your expenses, travel, and rental overview"
    >
        <flux:modal.trigger name="commuter-filters">
            <button
                type="button"
                class="relative flex items-center gap-1.5 sm:gap-2 px-2.5 sm:px-3.5 h-8 sm:h-9 rounded-lg bg-primary text-white border-0 hover:bg-primary-hover dark:bg-secondary dark:text-[var(--color-dark-primary)] dark:hover:bg-secondary-hover transition font-secondary text-xs sm:text-table-row shrink-0"
            >
                <flux:icon.funnel class="w-3 h-3 sm:w-3.5 sm:h-3.5 text-white dark:text-[var(--color-dark-primary)]" />
                <span class="hidden sm:inline">Filters</span>
                @if ((int) $this->range !== 7)
                    <span class="flex items-center justify-center w-4 h-4 rounded-full bg-primary dark:bg-dark-txt-primary text-white dark:text-primary text-[10px] font-bold">
                        1
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
            My Dashboard
        </x-heading>
    </div>

    {{-- ===================== FILTERS MODAL ===================== --}}
    <flux:modal name="commuter-filters" class="md:w-96">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Filters</flux:heading>
                <flux:subheading>Narrow down your dashboard data.</flux:subheading>
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

    {{-- ===================== ZONE: OVERVIEW ===================== --}}
    <div class="flex items-center gap-2.5 text-light-txt-primary dark:text-dark-txt-primary">
        <span class="zone-bar bg-primary dark:bg-dark-txt-primary"></span>
        <span class="font-secondary text-nav-label font-bold uppercase tracking-widest">Overview</span>
    </div>
    <hr class="zone-rule border-light-bd-default dark:border-dark-bd-default">

    <div
        class="grid grid-cols-2 lg:grid-cols-4 gap-2 sm:gap-3 mb-8"
        x-data="{
            trips: @js($this->totalTrips),
            rentals: @js($this->activeRentalRequests),
            points: @js($this->cardPointsBalance),
            spent: @js($this->pointsSpent),
            flipT: false, flipR: false, flipP: false, flipS: false,
        }"
        @kpi-updated.window="
            if ($event.detail.trips !== trips) { trips = $event.detail.trips; flipT = true; setTimeout(() => flipT = false, 500); }
            if ($event.detail.rentals !== rentals) { rentals = $event.detail.rentals; flipR = true; setTimeout(() => flipR = false, 500); }
            if ($event.detail.points !== points) { points = $event.detail.points; flipP = true; setTimeout(() => flipP = false, 500); }
            if ($event.detail.spent !== spent) { spent = $event.detail.spent; flipS = true; setTimeout(() => flipS = false, 500); }
        "
    >
        <flux:card class="p-3 sm:p-4">
            <div class="flex items-center justify-center w-8 h-8 sm:w-10 sm:h-10 rounded-xl bg-primary/10 dark:bg-primary/20 shrink-0">
                <flux:icon.map-pin class="w-4 h-4 sm:w-5 sm:h-5 text-primary dark:text-dark-txt-primary" />
            </div>
            <x-text class="font-secondary text-xs sm:text-stat-label font-medium text-light-txt-body dark:text-dark-txt-body block mt-2.5 sm:mt-3">Trips taken</x-text>
            <x-text class="font-primary text-xl sm:text-stat-value font-bold tabular-nums text-light-txt-primary dark:text-dark-txt-primary block mt-0.5" x-bind:class="{ 'flap-flip': flipT }" x-text="trips"></x-text>
        </flux:card>

        <flux:card class="p-3 sm:p-4">
            <div class="flex items-center justify-center w-8 h-8 sm:w-10 sm:h-10 rounded-xl bg-secondary/10 shrink-0">
                <flux:icon.chat-bubble-left-right class="w-4 h-4 sm:w-5 sm:h-5 text-secondary" />
            </div>
            <x-text class="font-secondary text-xs sm:text-stat-label font-medium text-light-txt-body dark:text-dark-txt-body block mt-2.5 sm:mt-3">Active rentals</x-text>
            <x-text class="font-primary text-xl sm:text-stat-value font-bold tabular-nums text-light-txt-primary dark:text-dark-txt-primary block mt-0.5" x-bind:class="{ 'flap-flip': flipR }" x-text="rentals"></x-text>
        </flux:card>

        <flux:card class="p-3 sm:p-4">
            <div class="flex items-center justify-center w-8 h-8 sm:w-10 sm:h-10 rounded-xl bg-info/10 dark:bg-dark-info/20 shrink-0">
                <flux:icon.credit-card class="w-4 h-4 sm:w-5 sm:h-5 text-info dark:text-dark-info" />
            </div>
            <x-text class="font-secondary text-xs sm:text-stat-label font-medium text-light-txt-body dark:text-dark-txt-body block mt-2.5 sm:mt-3">Points balance</x-text>
            <div class="font-primary text-xl sm:text-stat-value font-bold tabular-nums text-info dark:text-dark-info mt-0.5" :class="{ 'flap-flip': flipP }">
                <span x-show="points !== null" x-text="Number(points).toFixed(0)"></span>
                <span x-show="points === null" class="text-sm font-normal opacity-70 text-light-txt-muted dark:text-dark-txt-muted">No card</span>
            </div>
        </flux:card>

        <flux:card class="p-3 sm:p-4">
            <div class="flex items-center justify-center w-8 h-8 sm:w-10 sm:h-10 rounded-xl bg-warning/10 dark:bg-dark-warning/20 shrink-0">
                <flux:icon.arrow-up class="w-4 h-4 sm:w-5 sm:h-5 text-warning dark:text-dark-warning" />
            </div>
            <x-text class="font-secondary text-xs sm:text-stat-label font-medium text-light-txt-body dark:text-dark-txt-body block mt-2.5 sm:mt-3">Points spent</x-text>
            <x-text class="font-primary text-xl sm:text-stat-value font-bold tabular-nums text-warning dark:text-dark-warning block mt-0.5" x-bind:class="{ 'flap-flip': flipS }" x-text="Number(spent).toFixed(0)"></x-text>
        </flux:card>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-3 mb-8">
        <flux:card class="p-4 lg:col-span-2">
            <div class="flex items-center justify-between gap-2 mb-2">
                <x-text class="font-secondary text-sm sm:text-card-title font-semibold text-light-txt-primary dark:text-dark-txt-primary">
                    Trips over time
                </x-text>
                <x-dashboard.trend-pill :value="$this->tripsTrend" :suffix="$this->range . 'd'" />
            </div>
            <div
                wire:ignore
                x-data="lineChart(@js($this->tripsOverTime))"
                @trips-chart-updated.window="update($event.detail.chart)"
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
                    Most used routes
                </x-text>
                <span class="font-secondary text-xs font-medium text-light-txt-muted dark:text-dark-txt-muted">{{ array_sum($this->mostUsedRoutes['data']) }} trips</span>
            </div>
            <div
                wire:ignore
                x-data="donutChart(@js($this->mostUsedRoutes))"
                @routes-chart-updated.window="update($event.detail.chart)"
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

    {{-- ===================== ZONE: MY RENTALS ===================== --}}
    <div class="flex items-center gap-2.5 text-light-txt-primary dark:text-dark-txt-primary">
        <span class="zone-bar bg-secondary"></span>
        <span class="font-secondary text-nav-label font-bold uppercase tracking-widest">My Rentals</span>
    </div>
    <hr class="zone-rule border-light-bd-default dark:border-dark-bd-default">

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-3 mb-8">
        <flux:card class="p-4">
            <div class="flex items-center justify-between gap-2 mb-2">
                <x-text class="font-secondary text-sm sm:text-card-title font-semibold text-light-txt-primary dark:text-dark-txt-primary">
                    Rental request status
                </x-text>
                <span class="font-secondary text-xs font-medium text-light-txt-muted dark:text-dark-txt-muted">{{ array_sum($this->rentalRequestStatusSplit['data']) }} total</span>
            </div>
            <div
                wire:ignore
                x-data="donutChart(@js($this->rentalRequestStatusSplit))"
                @rental-status-chart-updated.window="update($event.detail.chart)"
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

        <flux:card class="p-4">
            <x-text class="font-secondary text-sm sm:text-card-title font-semibold text-light-txt-primary dark:text-dark-txt-primary block mb-2">
                Recent rental requests
            </x-text>
            <div class="mt-2 space-y-2">
                @forelse ($this->recentRentalRequests as $req)
                    <div class="flex justify-between items-center gap-2 border-b border-light-bd-default/50 dark:border-dark-bd-default/50 pb-2">
                        <div class="min-w-0">
                            <span class="font-secondary text-sm text-light-txt-body dark:text-dark-txt-body break-words">
                                {{ $req->pick_up_location }} → {{ $req->drop_off_location }}
                            </span>
                            <span class="block font-secondary text-timestamp text-light-txt-muted dark:text-dark-txt-muted">
                                {{ $req->created_at->diffForHumans() }}
                            </span>
                        </div>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-badge font-medium shrink-0
                            @if($req->status === 'pending') bg-warning/10 text-warning dark:bg-dark-warning/20 dark:text-dark-warning
                            @elseif($req->status === 'accept') bg-info/10 text-info dark:bg-dark-info/20 dark:text-dark-info
                            @elseif($req->status === 'ongoing') bg-success/10 text-success dark:bg-dark-success/20 dark:text-dark-success
                            @else bg-light-subtle text-light-txt-muted dark:bg-dark-subtle dark:text-dark-txt-muted
                            @endif">
                            {{ $req->status === 'accept' ? 'Accepted' : ucfirst($req->status) }}
                        </span>
                    </div>
                @empty
                    <p class="text-light-txt-muted dark:text-dark-txt-muted py-4 text-center">No rental requests yet.</p>
                @endforelse
            </div>
        </flux:card>
    </div>

    {{-- ===================== ZONE: RECENT ACTIVITY ===================== --}}
    <div class="flex items-center gap-2.5 text-light-txt-primary dark:text-dark-txt-primary">
        <span class="zone-bar bg-info dark:bg-dark-info"></span>
        <span class="font-secondary text-nav-label font-bold uppercase tracking-widest">Recent Activity</span>
    </div>
    <hr class="zone-rule border-light-bd-default dark:border-dark-bd-default">

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
        <flux:card class="p-4">
            <x-text class="font-secondary text-sm sm:text-card-title font-semibold text-light-txt-primary dark:text-dark-txt-primary block mb-2">
                Recent trips
            </x-text>
            <div class="mt-2 space-y-2">
                @forelse ($this->recentTrips as $trip)
                    <div class="flex justify-between items-center gap-2 border-b border-light-bd-default/50 dark:border-dark-bd-default/50 pb-2">
                        <div class="min-w-0">
                            <span class="font-secondary text-sm text-light-txt-body dark:text-dark-txt-body break-words">
                                {{ $trip->destination }} ({{ ucfirst($trip->vehicle_type) }})
                            </span>
                            <span class="block font-secondary text-timestamp text-light-txt-muted dark:text-dark-txt-muted">
                                {{ $trip->created_at->diffForHumans() }}
                            </span>
                        </div>
                    </div>
                @empty
                    <p class="text-light-txt-muted dark:text-dark-txt-muted py-4 text-center">No trips recorded yet.</p>
                @endforelse
            </div>
        </flux:card>

        <flux:card class="p-4">
            <x-text class="font-secondary text-sm sm:text-card-title font-semibold text-light-txt-primary dark:text-dark-txt-primary block mb-2">
                Recent card activity
            </x-text>
            <div class="mt-2 space-y-2">
                @forelse ($this->recentCardTransactions as $txn)
                    <div class="flex justify-between items-center gap-2 border-b border-light-bd-default/50 dark:border-dark-bd-default/50 pb-2">
                        <div class="min-w-0">
                            <span class="font-secondary text-sm text-light-txt-body dark:text-dark-txt-body capitalize break-words">
                                {{ str_replace('_', ' ', $txn->transaction_type) }}
                            </span>
                            <span class="block font-secondary text-timestamp text-light-txt-muted dark:text-dark-txt-muted">
                                {{ $txn->created_at->diffForHumans() }}
                            </span>
                        </div>
                        <span class="font-primary text-stat-value font-semibold shrink-0
                            @if($txn->transaction_type === 'top_up') text-success dark:text-dark-success
                            @else text-light-txt-primary dark:text-dark-txt-primary
                            @endif">
                            {{ $txn->transaction_type === 'top_up' ? '+' : '-' }}{{ number_format($txn->amount, 0) }}
                        </span>
                    </div>
                @empty
                    <p class="text-light-txt-muted dark:text-dark-txt-muted py-4 text-center">No card transactions.</p>
                @endforelse
            </div>
        </flux:card>
    </div>
</div>
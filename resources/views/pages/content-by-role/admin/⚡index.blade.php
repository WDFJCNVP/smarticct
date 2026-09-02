<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Queue;
use App\Models\CardTransaction;
use App\Models\CashTransaction;
use App\Models\TopUpTransaction;
use App\Models\Card;
use App\Models\Post;
use App\Models\TripRequest;
use App\Models\RentTransaction;
use App\Models\AuditLog;

new #[Layout('layouts.admin-layout')] class extends Component
{
    public string $range = '7'; // days: 7, 14, 30
    public string $peakVehicleType = 'all'; // filter for the peak-queueing-time chart

    // ===================== KPI CARDS =====================

    #[Computed]
    public function totalUsers()
    {
        return User::whereIn('role', ['operator', 'commuter'])->count();
    }

    #[Computed]
    public function totalVehicles()
    {
        return Vehicle::count();
    }

    #[Computed]
    public function registeredToday()
    {
        return User::whereIn('role', ['operator', 'commuter'])
            ->whereDate('created_at', today())
            ->count();
    }

    #[Computed]
    public function vehiclesQueueingNow()
    {
        return Queue::whereIn('status', ['staging', 'loading'])->count();
    }

#[Computed]
public function totalRevenue()
{
    // 1. Digital card fee earnings
    $cardFees = CardTransaction::whereIn('transaction_type', ['queueing_fee', 'operator_payment'])
        ->where('status', 'success')
        ->sum('amount');

    // 2. Over-the-counter cash fees
    $cashFees = CashTransaction::where('status', 'success')
        ->sum('amount');

    // 3. Admin withdrawals (pending and completed)
    $totalWithdrawn = CardTransaction::where('transaction_type', 'admin_withdrawal')
        ->whereIn('status', ['pending', 'success'])
        ->sum('amount');

    // Net remaining balance/revenue
    return max(0.0, ($cardFees + $cashFees) - $totalWithdrawn);
}

#[Computed]
public function todayRevenue()
{
    $cardFees = CardTransaction::whereIn('transaction_type', ['queueing_fee', 'operator_payment'])
        ->where('status', 'success')
        ->whereDate('transaction_time', today())
        ->sum('amount');

    $cashFees = CashTransaction::where('status', 'success')
        ->whereDate('created_at', today())
        ->sum('amount');

    return $cardFees + $cashFees;
}

    #[Computed]
    public function queueFeeRevenueToday()
    {
        return Queue::whereDate('time_queued', today())
            ->join('operator_ticket_rates', 'queues.vehicle_type', '=', 'operator_ticket_rates.vehicle_type')
            ->sum('operator_ticket_rates.queueing_fee');
    }

    #[Computed]
    public function cardAdoptionRate()
    {
        $totalUsers = $this->totalUsers ?: 1;
        $totalCards = Card::count();

        return round(($totalCards / $totalUsers) * 100, 1);
    }

    #[Computed]
    public function failedTransactionsCount()
    {
        return CardTransaction::whereIn('status', ['failed', 'insufficient_balance'])
            ->whereDate('created_at', today())
            ->count();
    }

    // ===================== CHARTS =====================

    #[Computed]
    public function roleSplit()
    {
        $counts = User::whereIn('role', ['operator', 'commuter'])
            ->selectRaw('role, count(*) as total')
            ->groupBy('role')
            ->pluck('total', 'role');

        return [
            'labels' => $counts->keys()->map(fn ($r) => ucfirst($r))->values()->toArray(),
            'data' => $counts->values()->toArray(),
        ];
    }

    #[Computed]
    public function commuterTypeSplit()
    {
        $counts = User::where('role', 'commuter')
            ->whereNotNull('commuter_type')
            ->selectRaw('commuter_type, count(*) as total')
            ->groupBy('commuter_type')
            ->pluck('total', 'commuter_type');

        return [
            'labels' => $counts->keys()->map(fn ($t) => ucwords(str_replace('_', ' ', $t)))->values()->toArray(),
            'data' => $counts->values()->toArray(),
        ];
    }

    #[Computed]
    public function registrationsChart()
    {
        $days = max(1, (int) $this->range);
        $start = today()->subDays($days - 1);
        $end = today();

        $counts = User::whereIn('role', ['operator', 'commuter'])
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
    public function revenueByPaymentMethod()
    {
        $days = max(1, (int) $this->range);

        $counts = TopUpTransaction::where('status', 'paid')
            ->whereBetween('created_at', [today()->subDays($days - 1)->startOfDay(), today()->endOfDay()])
            ->selectRaw('payment_method, SUM(amount_paid) as total')
            ->groupBy('payment_method')
            ->pluck('total', 'payment_method');

        return [
            'labels' => $counts->keys()->map(fn ($m) => ucfirst($m))->values()->toArray(),
            'data' => $counts->values()->toArray(),
        ];
    }

    #[Computed]
    public function queueStatusBreakdown()
    {
        $counts = Queue::whereDate('time_queued', today())
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'labels' => $counts->keys()->map(fn ($s) => ucfirst($s))->values()->toArray(),
            'data' => $counts->values()->toArray(),
        ];
    }

    #[Computed]
    public function queueVolumeByVehicleType()
    {
        $days = max(1, (int) $this->range);

        $counts = Queue::whereBetween('time_queued', [today()->subDays($days - 1)->startOfDay(), today()->endOfDay()])
            ->selectRaw('vehicle_type, count(*) as total')
            ->groupBy('vehicle_type')
            ->pluck('total', 'vehicle_type');

        return [
            'labels' => $counts->keys()->values()->toArray(),
            'data' => $counts->values()->toArray(),
        ];
    }

    #[Computed]
    public function postsByRole()
    {
        $days = max(1, (int) $this->range);

        $counts = Post::query()
            ->join('users', 'posts.user_id', '=', 'users.id')
            ->where('posts.type', 'rental')
            ->whereIn('users.role', ['operator', 'commuter'])
            ->whereDate('posts.created_at', '>=', today()->subDays($days - 1))
            ->selectRaw('users.role as role, count(*) as total')
            ->groupBy('users.role')
            ->pluck('total', 'role');

        return [
            'labels' => $counts->keys()->map(fn ($r) => ucfirst($r))->values()->toArray(),
            'data' => $counts->values()->toArray(),
        ];
    }

    #[Computed]
    public function rentTransactionFunnel()
    {
        $counts = RentTransaction::selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'labels' => $counts->keys()->map(fn ($s) => ucfirst($s))->values()->toArray(),
            'data' => $counts->values()->toArray(),
        ];
    }

    #[Computed]
    public function tripRequestStatusSplit()
    {
        $counts = TripRequest::selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'labels' => $counts->keys()->map(fn ($s) => ucfirst($s))->values()->toArray(),
            'data' => $counts->values()->toArray(),
        ];
    }

    #[Computed]
    public function cardsByStatus()
    {
        $counts = Card::selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'labels' => $counts->keys()->map(fn ($s) => ucfirst($s))->values()->toArray(),
            'data' => $counts->values()->toArray(),
        ];
    }

    #[Computed]
    public function auditActionVolume()
    {
        $days = max(1, (int) $this->range);

        $counts = AuditLog::whereBetween('created_at', [today()->subDays($days - 1)->startOfDay(), today()->endOfDay()])
            ->selectRaw('action, count(*) as total')
            ->groupBy('action')
            ->orderByDesc('total')
            ->limit(8)
            ->pluck('total', 'action');

        return [
            'labels' => $counts->keys()->values()->toArray(),
            'data' => $counts->values()->toArray(),
        ];
    }

    #[Computed]
    public function recentAuditEntries()
    {
        return AuditLog::with('user:id,name')
            ->latest()
            ->limit(5)
            ->get(['id', 'user_id', 'action', 'subject', 'channel', 'created_at']);
    }

    // ===================== NEW: QUEUE PAYMENT MODE SPLIT =====================

    #[Computed]
    public function queuePaymentModeSplit()
    {
        // Card payments for queue fees today
        $cardFees = CardTransaction::where('transaction_type', 'operator_payment')
            ->whereDate('transaction_time', today())
            ->sum('amount');

        // Cash payments for queue fees today
        $cashFees = CashTransaction::whereDate('created_at', today())
            ->where('status', 'success')
            ->sum('amount');

        $labels = [];
        $data = [];

        if ($cardFees > 0) {
            $labels[] = 'Card';
            $data[] = (float) $cardFees;
        }

        if ($cashFees > 0) {
            $labels[] = 'Cash';
            $data[] = (float) $cashFees;
        }

        if (empty($labels)) {
            return ['labels' => [], 'data' => []];
        }

        return [
            'labels' => $labels,
            'data' => $data,
        ];
    }

    // ===================== PEAK QUEUEING TIME (by vehicle type) =====================

    #[Computed]
    public function vehicleTypeOptions()
    {
        return Vehicle::query()->whereNotNull('vehicle_type')->distinct()->orderBy('vehicle_type')->pluck('vehicle_type');
    }

    #[Computed]
    public function peakTimeByVehicleType()
    {
        $days = max(1, (int) $this->range);
        $start = today()->subDays($days - 1)->startOfDay();
        $end = today()->endOfDay();

        $counts = Queue::whereBetween('time_queued', [$start, $end])
            ->when($this->peakVehicleType !== 'all', fn ($q) => $q->where('vehicle_type', $this->peakVehicleType))
            ->selectRaw('HOUR(time_queued) as hour, count(*) as total')
            ->groupBy('hour')
            ->pluck('total', 'hour');

        $labels = [];
        $data = [];

        for ($hour = 0; $hour < 24; $hour++) {
            $labels[] = \Carbon\Carbon::createFromTime($hour)->format('g A');
            $data[] = (int) ($counts[$hour] ?? 0);
        }

        return compact('labels', 'data');
    }

    #[Computed]
    public function peakHour()
    {
        $chart = $this->peakTimeByVehicleType;
        $max = max($chart['data']);

        if ($max <= 0) {
            return null;
        }

        return [
            'time' => $chart['labels'][array_search($max, $chart['data'])],
            'count' => $max,
        ];
    }

    public function updatedPeakVehicleType()
    {
        $this->dispatch('peak-time-chart-updated', chart: $this->peakTimeByVehicleType);
    }

    // ===================== OPERATOR FRANCHISE EXPIRY =====================

    #[Computed]
    public function expiringOperatorDocs()
    {
        $soon = today()->addDays(30)->endOfDay();

        return Vehicle::with('user:id,name,phone_number')
            ->whereNotNull('franchise_expiry_date')
            ->where('franchise_expiry_date', '<=', $soon)
            ->orderBy('franchise_expiry_date', 'asc')
            ->limit(8)
            ->get(['id', 'user_id', 'plate_number', 'vehicle_type', 'franchise_expiry_date']);
    }

    // ===================== TREND (REAL DATA — REPLACES "LIVE") =====================

    private function trendPercent(string $modelClass, string $dateColumn, ?\Closure $scope = null, ?string $sumColumn = null, ?int $windowDays = null): ?float
    {
        $days = $windowDays ?? max(1, (int) $this->range);

        $currentStart = today()->subDays($days - 1)->startOfDay();
        $currentEnd = today()->endOfDay();
        $previousStart = today()->subDays(($days * 2) - 1)->startOfDay();
        $previousEnd = today()->subDays($days)->endOfDay();

        $build = function ($start, $end) use ($modelClass, $dateColumn, $scope) {
            $query = $modelClass::query()->whereBetween($dateColumn, [$start, $end]);

            return $scope ? $scope($query) : $query;
        };

        $current = $sumColumn ? $build($currentStart, $currentEnd)->sum($sumColumn) : $build($currentStart, $currentEnd)->count();
        $previous = $sumColumn ? $build($previousStart, $previousEnd)->sum($sumColumn) : $build($previousStart, $previousEnd)->count();

        if ($previous == 0) {
            return $current > 0 ? 100.0 : null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    #[Computed]
    public function totalUsersTrend()
    {
        $current = $this->totalUsers;
        $previous = User::whereIn('role', ['operator', 'commuter'])->where('created_at', '<=', today()->subDays(7))->count();

        if ($previous == 0) {
            return $current > 0 ? 100.0 : null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    #[Computed]
    public function totalVehiclesTrend()
    {
        $current = $this->totalVehicles;
        $previous = Vehicle::where('created_at', '<=', today()->subDays(7))->count();

        if ($previous == 0) {
            return $current > 0 ? 100.0 : null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    #[Computed]
    public function registeredTodayTrend()
    {
        return $this->trendPercent(User::class, 'created_at', fn ($q) => $q->whereIn('role', ['operator', 'commuter']), null, 1);
    }

    #[Computed]
    public function queueingNowTrend()
    {
        $current = $this->vehiclesQueueingNow;
        $cutoff = now()->subDay();

        $previous = Queue::whereIn('status', ['staging', 'loading'])
            ->whereDate('time_queued', $cutoff->toDateString())
            ->whereTime('time_queued', '<=', $cutoff->format('H:i:s'))
            ->count();

        if ($previous == 0) {
            return $current > 0 ? 100.0 : null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    #[Computed]
    public function revenueTodayTrend()
    {
        return $this->trendPercent(TopUpTransaction::class, 'created_at', fn ($q) => $q->where('status', 'paid'), 'amount_paid', 1);
    }

    #[Computed]
    public function queueFeeRevenueTrend()
    {
        $today = Queue::whereDate('time_queued', today())
            ->join('operator_ticket_rates', 'queues.vehicle_type', '=', 'operator_ticket_rates.vehicle_type')
            ->sum('operator_ticket_rates.queueing_fee');
        $yesterday = Queue::whereDate('time_queued', today()->subDay())
            ->join('operator_ticket_rates', 'queues.vehicle_type', '=', 'operator_ticket_rates.vehicle_type')
            ->sum('operator_ticket_rates.queueing_fee');

        if ($yesterday == 0) {
            return $today > 0 ? 100.0 : null;
        }

        return round((($today - $yesterday) / $yesterday) * 100, 1);
    }

    #[Computed]
    public function failedTransactionsTrend()
    {
        return $this->trendPercent(CardTransaction::class, 'created_at', fn ($q) => $q->whereIn('status', ['failed', 'insufficient_balance']), null, 1);
    }

    #[Computed]
    public function cardAdoptionTrend()
    {
        $current = $this->cardAdoptionRate;

        $usersAWeekAgo = User::whereIn('role', ['operator', 'commuter'])->where('created_at', '<=', today()->subDays(7))->count() ?: 1;
        $cardsAWeekAgo = Card::where('created_at', '<=', today()->subDays(7))->count();
        $previous = round(($cardsAWeekAgo / $usersAWeekAgo) * 100, 1);

        if ($previous == 0) {
            return $current > 0 ? 100.0 : null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    #[Computed]
    public function registrationsTrend()
    {
        return $this->trendPercent(User::class, 'created_at', fn ($q) => $q->whereIn('role', ['operator', 'commuter']));
    }

    #[Computed]
    public function revenueTrend()
    {
        return $this->trendPercent(TopUpTransaction::class, 'created_at', fn ($q) => $q->where('status', 'paid'), 'amount_paid');
    }

    #[Computed]
    public function queueVolumeTrend()
    {
        return $this->trendPercent(Queue::class, 'time_queued');
    }

    #[Computed]
    public function postsTrend()
    {
        return $this->trendPercent(
            Post::class,
            'created_at',
            fn ($q) => $q->where('type', 'rental')->whereHas('user', fn ($u) => $u->whereIn('role', ['operator', 'commuter']))
        );
    }

    #[Computed]
    public function auditTrend()
    {
        return $this->trendPercent(AuditLog::class, 'created_at');
    }

    // ===================== FILTER UPDATES =====================

    public function updatedRange()
    {
        $this->dispatch('registrations-chart-updated', chart: $this->registrationsChart);
        $this->dispatch('revenue-chart-updated', chart: $this->revenueByPaymentMethod);
        $this->dispatch('queue-volume-chart-updated', chart: $this->queueVolumeByVehicleType);
        $this->dispatch('posts-by-role-chart-updated', chart: $this->postsByRole);
        $this->dispatch('audit-action-chart-updated', chart: $this->auditActionVolume);
        $this->dispatch('queue-payment-mode-updated', chart: $this->queuePaymentModeSplit);
        $this->dispatch('peak-time-chart-updated', chart: $this->peakTimeByVehicleType);
    }

    // ===================== LIVE REFRESH =====================

    #[On('echo:user-info-updated,.UserInfoUpdated')]
    #[On('echo:vehicle-queue,.QueuedVehicleEvent')]
    public function refreshDashboard()
    {
        unset(
            $this->totalUsers,
            $this->totalVehicles,
            $this->registeredToday,
            $this->vehiclesQueueingNow,
            $this->todayRevenue,
            $this->queueFeeRevenueToday,
            $this->cardAdoptionRate,
            $this->failedTransactionsCount,
            $this->roleSplit,
            $this->commuterTypeSplit,
            $this->registrationsChart,
            $this->revenueByPaymentMethod,
            $this->queueStatusBreakdown,
            $this->queueVolumeByVehicleType,
            $this->postsByRole,
            $this->rentTransactionFunnel,
            $this->tripRequestStatusSplit,
            $this->cardsByStatus,
            $this->auditActionVolume,
            $this->recentAuditEntries,
            $this->queuePaymentModeSplit,
            $this->peakTimeByVehicleType,
            $this->peakHour,
            $this->expiringOperatorDocs,
            $this->totalUsersTrend,
            $this->totalVehiclesTrend,
            $this->registeredTodayTrend,
            $this->queueingNowTrend,
            $this->revenueTodayTrend,
            $this->queueFeeRevenueTrend,
            $this->failedTransactionsTrend,
            $this->cardAdoptionTrend,
            $this->registrationsTrend,
            $this->revenueTrend,
            $this->queueVolumeTrend,
            $this->postsTrend,
            $this->auditTrend,
        );

        $this->dispatch('peak-time-chart-updated', chart: $this->peakTimeByVehicleType);
        $this->dispatch('role-chart-updated', chart: $this->roleSplit);
        $this->dispatch('commuter-type-chart-updated', chart: $this->commuterTypeSplit);
        $this->dispatch('registrations-chart-updated', chart: $this->registrationsChart);
        $this->dispatch('revenue-chart-updated', chart: $this->revenueByPaymentMethod);
        $this->dispatch('queue-status-chart-updated', chart: $this->queueStatusBreakdown);
        $this->dispatch('queue-volume-chart-updated', chart: $this->queueVolumeByVehicleType);
        $this->dispatch('posts-by-role-chart-updated', chart: $this->postsByRole);
        $this->dispatch('rent-funnel-chart-updated', chart: $this->rentTransactionFunnel);
        $this->dispatch('trip-request-chart-updated', chart: $this->tripRequestStatusSplit);
        $this->dispatch('cards-status-chart-updated', chart: $this->cardsByStatus);
        $this->dispatch('audit-action-chart-updated', chart: $this->auditActionVolume);
        $this->dispatch('queue-payment-mode-updated', chart: $this->queuePaymentModeSplit);
        $this->dispatch('status-strip-updated', queueing: $this->vehiclesQueueingNow, revenue: $this->todayRevenue);
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
        <div class="flex items-start justify-between gap-3 sm:gap-4">
            <x-pages-heading
                heading="Admin Dashboard"
                description="System overview and key metrics."
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

                <livewire:pages::notification-bell />

                <flux:modal.trigger name="admin-filters">
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
    <flux:modal name="admin-filters" class="md:w-96">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Filters</flux:heading>
                <flux:subheading>Narrow down the dashboard data.</flux:subheading>
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
            queueing: @js($this->vehiclesQueueingNow),
            revenue: @js($this->todayRevenue),
            flipQ: false,
            flipR: false,
        }"
        @status-strip-updated.window="
            if ($event.detail.queueing !== queueing) { queueing = $event.detail.queueing; flipQ = true; setTimeout(() => flipQ = false, 500); }
            if ($event.detail.revenue !== revenue) { revenue = $event.detail.revenue; flipR = true; setTimeout(() => flipR = false, 500); }
        "
    >
        <div class="flex flex-col sm:flex-row divide-y sm:divide-y-0 sm:divide-x divide-white/15">
            <div class="flex items-center justify-between gap-3 px-5 py-4 flex-1">
                <div class="flex items-center gap-3">
                    <flux:icon.truck class="w-5 h-5 text-white/70 shrink-0" />
                    <div>
                        <div class="font-secondary text-nav-label font-semibold uppercase tracking-wide text-white/80">Now Queueing</div>
                        <div class="font-primary text-3xl font-extrabold tabular-nums" :class="{ 'flap-flip': flipQ }" x-text="queueing"></div>
                    </div>
                </div>
                <x-dashboard.trend-pill :value="$this->queueingNowTrend" class="!bg-white/15 !text-white" suffix="vs yday" />
            </div>
            <div class="flex items-center justify-between gap-3 px-5 py-4 flex-1">
                <div class="flex items-center gap-3">
                    <flux:icon.banknotes class="w-5 h-5 text-white/70 shrink-0" />
                    <div>
                        <div class="font-secondary text-nav-label font-semibold uppercase tracking-wide text-white/80">Revenue Today</div>
                        <div class="font-primary text-3xl font-extrabold tabular-nums" :class="{ 'flap-flip': flipR }" x-text="'₱' + Number(revenue).toLocaleString('en-US', { minimumFractionDigits: 2 })"></div>
                    </div>
                </div>
                <x-dashboard.trend-pill :value="$this->revenueTodayTrend" class="!bg-white/15 !text-white" suffix="vs yday" />
            </div>
            <div class="flex items-center justify-between gap-3 px-5 py-4 flex-1">
                <div class="flex items-center gap-3">
                    <flux:icon.banknotes class="w-5 h-5 text-white/70 shrink-0" />
                    <div>
                        <div class="font-secondary text-nav-label font-semibold uppercase tracking-wide text-white/80">Total Revenue</div>
                        <div class="font-primary text-3xl font-extrabold tabular-nums">
                            ₱{{ number_format($this->totalRevenue, 2) }}
                        </div>
                    </div>
                </div>
                <x-button href=" {{ route('withdraw') }} " variant="primary" color="yellow">Withdraw</x-button>
            </div>
        </div>
    </div>

    {{-- ===================== ZONE: OVERVIEW (3 KPI cards) ===================== --}}
    <div class="flex items-center gap-2.5 text-light-txt-primary dark:text-dark-txt-primary">
        <span class="zone-bar bg-primary dark:bg-dark-txt-primary"></span>
        <span class="font-secondary text-nav-label font-bold uppercase tracking-widest">Overview</span>
    </div>
    <hr class="zone-rule border-light-bd-default dark:border-dark-bd-default">

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 sm:gap-3 mb-4">
        <flux:card class="p-3 sm:p-4">
            <div class="flex items-center justify-between gap-2">
                <div class="flex items-center justify-center w-8 h-8 sm:w-10 sm:h-10 rounded-xl bg-primary/10 dark:bg-primary/20 shrink-0">
                    <flux:icon.users class="w-4 h-4 sm:w-5 sm:h-5 text-primary dark:text-dark-txt-primary" />
                </div>
                <x-dashboard.trend-pill :value="$this->totalUsersTrend" suffix="vs 7d ago" />
            </div>
            <x-text class="font-secondary text-xs sm:text-stat-label font-medium text-light-txt-body dark:text-dark-txt-body block mt-2.5 sm:mt-3">Total users</x-text>
            <x-text class="font-primary text-xl sm:text-stat-value font-bold tabular-nums text-light-txt-primary dark:text-dark-txt-primary block mt-0.5">
                {{ $this->totalUsers }}
            </x-text>
        </flux:card>

        <flux:card class="p-3 sm:p-4">
            <div class="flex items-center justify-between gap-2">
                <div class="flex items-center justify-center w-8 h-8 sm:w-10 sm:h-10 rounded-xl bg-info/10 dark:bg-dark-info/20 shrink-0">
                    <flux:icon.truck class="w-4 h-4 sm:w-5 sm:h-5 text-info dark:text-dark-info" />
                </div>
                <x-dashboard.trend-pill :value="$this->totalVehiclesTrend" suffix="vs 7d ago" />
            </div>
            <x-text class="font-secondary text-xs sm:text-stat-label font-medium text-light-txt-body dark:text-dark-txt-body block mt-2.5 sm:mt-3">Total vehicles</x-text>
            <x-text class="font-primary text-xl sm:text-stat-value font-bold tabular-nums text-info dark:text-dark-info block mt-0.5">
                {{ $this->totalVehicles }}
            </x-text>
        </flux:card>

        <flux:card class="p-3 sm:p-4">
            <div class="flex items-center justify-between gap-2">
                <div class="flex items-center justify-center w-8 h-8 sm:w-10 sm:h-10 rounded-xl bg-success/10 dark:bg-dark-success/20 shrink-0">
                    <flux:icon.calendar-days class="w-4 h-4 sm:w-5 sm:h-5 text-success dark:text-dark-success" />
                </div>
                <x-dashboard.trend-pill :value="$this->registeredTodayTrend" suffix="vs yday" />
            </div>
            <x-text class="font-secondary text-xs sm:text-stat-label font-medium text-light-txt-body dark:text-dark-txt-body block mt-2.5 sm:mt-3">Registered today</x-text>
            <x-text class="font-primary text-xl sm:text-stat-value font-bold tabular-nums text-success dark:text-dark-success block mt-0.5">
                {{ $this->registeredToday }}
            </x-text>
        </flux:card>
    </div>

    {{-- ===================== CHARTS ROW ===================== --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-3 mb-8 items-start">
        {{-- Registrations (span 2) --}}
        <flux:card class="p-4 lg:col-span-2">
            <div class="flex items-center justify-between gap-2 mb-2">
                <x-text class="font-secondary text-sm sm:text-card-title font-semibold text-light-txt-primary dark:text-dark-txt-primary">
                    Registrations over time
                </x-text>
                <x-dashboard.trend-pill :value="$this->registrationsTrend" :suffix="$this->range . 'd'" />
            </div>
            <div
                wire:ignore
                x-data="lineChart(@js($this->registrationsChart))"
                @registrations-chart-updated.window="update($event.detail.chart)"
            >
                <div class="relative h-68 sm:h-76">
                    <canvas x-ref="canvas" x-show="!empty"></canvas>
                    <div x-show="empty" class="absolute inset-0 flex flex-col items-center justify-center gap-1.5 text-center px-4">
                        <flux:icon.chart-bar class="w-6 h-6 text-light-txt-muted dark:text-dark-txt-muted" />
                        <span class="font-secondary text-xs sm:text-sm text-light-txt-muted dark:text-dark-txt-muted">No data yet</span>
                    </div>
                </div>
            </div>
        </flux:card>

        {{-- Combined role + commuter charts in one card (span 1) --}}
        <flux:card class="p-4 lg:col-span-1">
            <div class="space-y-4">
                {{-- Role split --}}
                <div>
                    <div class="flex items-center justify-between gap-2 mb-1">
                        <x-text class="font-secondary text-sm font-semibold text-light-txt-primary dark:text-dark-txt-primary">
                            User role split
                        </x-text>
                        <span class="font-secondary text-xs font-medium text-light-txt-muted dark:text-dark-txt-muted">{{ array_sum($this->roleSplit['data']) }} total</span>
                    </div>
                    <div
                        wire:ignore
                        x-data="donutChart(@js($this->roleSplit))"
                        @role-chart-updated.window="update($event.detail.chart)"
                    >
                        <div class="relative h-28 sm:h-32">
                            <canvas x-ref="canvas" x-show="!empty"></canvas>
                            <div x-show="empty" class="absolute inset-0 flex items-center justify-center text-light-txt-muted dark:text-dark-txt-muted text-xs">No data</div>
                        </div>
                    </div>
                </div>

                {{-- Divider --}}
                <hr class="border-light-bd-default dark:border-dark-bd-default">

                {{-- Commuter type --}}
                <div>
                    <div class="flex items-center justify-between gap-2 mb-1">
                        <x-text class="font-secondary text-sm font-semibold text-light-txt-primary dark:text-dark-txt-primary">
                            Commuter type
                        </x-text>
                        <span class="font-secondary text-xs font-medium text-light-txt-muted dark:text-dark-txt-muted">{{ array_sum($this->commuterTypeSplit['data']) }} total</span>
                    </div>
                    <div
                        wire:ignore
                        x-data="donutChart(@js($this->commuterTypeSplit))"
                        @commuter-type-chart-updated.window="update($event.detail.chart)"
                    >
                        <div class="relative h-28 sm:h-32">
                            <canvas x-ref="canvas" x-show="!empty"></canvas>
                            <div x-show="empty" class="absolute inset-0 flex items-center justify-center text-light-txt-muted dark:text-dark-txt-muted text-xs">No data</div>
                        </div>
                    </div>
                </div>
            </div>
        </flux:card>
    </div>

    {{-- ===================== ZONE: PEAK QUEUEING TIME & COMPLIANCE ===================== --}}
    <div class="flex items-center gap-2.5 text-light-txt-primary dark:text-dark-txt-primary">
        <span class="zone-bar bg-warning dark:bg-dark-warning"></span>
        <span class="font-secondary text-nav-label font-bold uppercase tracking-widest">Peak Times &amp; Compliance</span>
    </div>
    <hr class="zone-rule border-light-bd-default dark:border-dark-bd-default">

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-3 mb-8">
        {{-- Peak queueing time by vehicle type --}}
        <flux:card class="p-4 lg:col-span-2">
            <div class="flex items-start justify-between gap-2 mb-2 flex-wrap">
                <div>
                    <x-text class="font-secondary text-sm sm:text-card-title font-semibold text-light-txt-primary dark:text-dark-txt-primary">
                        Peak queueing time
                    </x-text>
                    @if ($this->peakHour)
                        <x-text class="font-secondary text-xs text-light-txt-muted dark:text-dark-txt-muted block mt-0.5">
                            Busiest at <span class="font-semibold text-light-txt-primary dark:text-dark-txt-primary">{{ $this->peakHour['time'] }}</span>
                            &middot; {{ $this->peakHour['count'] }} vehicles queued
                        </x-text>
                    @endif
                </div>
                <flux:select wire:model.live="peakVehicleType" size="sm" class="w-full sm:w-36 shrink-0">
                    <flux:select.option value="all">All types</flux:select.option>
                    @foreach ($this->vehicleTypeOptions as $type)
                        <flux:select.option value="{{ $type }}">{{ ucfirst($type) }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
            <div
                wire:ignore
                x-data="barChart(@js($this->peakTimeByVehicleType), { label: 'Vehicles queued', colorKey: 'warning' })"
                @peak-time-chart-updated.window="update($event.detail.chart)"
            >
                <div class="relative h-64 sm:h-80">
                    <canvas x-ref="canvas" x-show="!empty"></canvas>
                    <div x-show="empty" class="absolute inset-0 flex flex-col items-center justify-center gap-1.5 text-center px-4">
                        <flux:icon.chart-bar class="w-6 h-6 text-light-txt-muted dark:text-dark-txt-muted" />
                        <span class="font-secondary text-xs sm:text-sm text-light-txt-muted dark:text-dark-txt-muted">No data yet</span>
                    </div>
                </div>
            </div>
        </flux:card>

        {{-- Operators with expiring franchise validity --}}
        <flux:card class="p-0 overflow-hidden">
            <div class="px-4 pt-4 flex items-center justify-between gap-2">
                <x-text class="font-secondary text-sm sm:text-card-title font-semibold text-light-txt-primary dark:text-dark-txt-primary">
                    Franchise expiry
                </x-text>
                <span class="font-secondary text-xs font-medium text-light-txt-muted dark:text-dark-txt-muted">Next 30 days</span>
            </div>
            <div class="mt-2 max-h-[22rem] overflow-y-auto divide-y divide-light-bd-default/60 dark:divide-dark-bd-default/60">
                @forelse ($this->expiringOperatorDocs as $vehicle)
                    @php
                        $docs = collect([
                            $vehicle->franchise_expiry_date ? ['label' => 'Franchise', 'date' => $vehicle->franchise_expiry_date] : null,
                        ])->filter()->filter(fn ($d) => today()->addDays(30)->gte($d['date']));
                    @endphp
                    <div class="px-4 py-2.5">
                        <div class="flex items-center justify-between gap-2">
                            <x-text class="font-secondary text-table-row font-semibold text-light-txt-primary dark:text-dark-txt-primary">
                                {{ $vehicle->user->name ?? 'Unassigned' }}
                            </x-text>
                            <span class="font-secondary text-badge text-light-txt-muted dark:text-dark-txt-muted">{{ $vehicle->plate_number }}</span>
                        </div>
                        <div class="flex flex-wrap gap-1.5 mt-1">
                            @foreach ($docs as $doc)
                                @php $daysLeft = today()->diffInDays($doc['date'], false); @endphp
                                <span @class([
                                    'inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-badge font-medium',
                                    'bg-danger/10 text-danger dark:bg-dark-danger/15 dark:text-dark-danger' => $daysLeft < 0,
                                    'bg-warning/10 text-warning dark:bg-dark-warning/15 dark:text-dark-warning' => $daysLeft >= 0,
                                ])>
                                    {{ $doc['label'] }}:
                                    {{ $daysLeft < 0 ? 'Expired ' . abs($daysLeft) . 'd ago' : ($daysLeft === 0 ? 'Expires today' : "Expires in {$daysLeft}d") }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <div class="py-8 text-center text-light-txt-muted dark:text-dark-txt-muted font-secondary text-table-row">
                        No franchise expiring soon.
                    </div>
                @endforelse
            </div>
            <div class="h-2"></div>
        </flux:card>
    </div>

    {{-- ===================== ZONE: REVENUE & QUEUEING ===================== --}}
    <div class="flex items-center gap-2.5 text-light-txt-primary dark:text-dark-txt-primary">
        <span class="zone-bar bg-success dark:bg-dark-success"></span>
        <span class="font-secondary text-nav-label font-bold uppercase tracking-widest">Revenue &amp; Queueing</span>
    </div>
    <hr class="zone-rule border-light-bd-default dark:border-dark-bd-default">

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 sm:gap-3 mb-4">
        <flux:card class="p-3 sm:p-4">
            <div class="flex items-center justify-between gap-2">
                <div class="flex items-center justify-center w-8 h-8 sm:w-10 sm:h-10 rounded-xl bg-success/10 dark:bg-dark-success/20 shrink-0">
                    <flux:icon.ticket class="w-4 h-4 sm:w-5 sm:h-5 text-success dark:text-dark-success" />
                </div>
                <x-dashboard.trend-pill :value="$this->queueFeeRevenueTrend" suffix="vs yday" />
            </div>
            <x-text class="font-secondary text-xs sm:text-stat-label font-medium text-light-txt-body dark:text-dark-txt-body block mt-2.5 sm:mt-3">Queue fees today</x-text>
            <x-text class="font-primary text-xl sm:text-stat-value font-bold tabular-nums text-success dark:text-dark-success block mt-0.5">
                ₱{{ number_format($this->queueFeeRevenueToday, 2) }}
            </x-text>
        </flux:card>

        <flux:card class="p-3 sm:p-4">
            <div class="flex items-center justify-between gap-2">
                <div class="flex items-center justify-center w-8 h-8 sm:w-10 sm:h-10 rounded-xl bg-danger/10 dark:bg-dark-danger/20 shrink-0">
                    <flux:icon.exclamation-triangle class="w-4 h-4 sm:w-5 sm:h-5 text-danger dark:text-dark-danger" />
                </div>
                <x-dashboard.trend-pill :value="$this->failedTransactionsTrend" suffix="vs yday" />
            </div>
            <x-text class="font-secondary text-xs sm:text-stat-label font-medium text-light-txt-body dark:text-dark-txt-body block mt-2.5 sm:mt-3">Failed transactions today</x-text>
            <x-text class="font-primary text-xl sm:text-stat-value font-bold tabular-nums text-danger dark:text-dark-danger block mt-0.5">
                {{ $this->failedTransactionsCount }}
            </x-text>
        </flux:card>

        <flux:card class="p-3 sm:p-4">
            <div class="flex items-center justify-between gap-2">
                <div class="flex items-center justify-center w-8 h-8 sm:w-10 sm:h-10 rounded-xl bg-info/10 dark:bg-dark-info/20 shrink-0">
                    <flux:icon.credit-card class="w-4 h-4 sm:w-5 sm:h-5 text-info dark:text-dark-info" />
                </div>
                <x-dashboard.trend-pill :value="$this->cardAdoptionTrend" suffix="vs 7d ago" />
            </div>
            <x-text class="font-secondary text-xs sm:text-stat-label font-medium text-light-txt-body dark:text-dark-txt-body block mt-2.5 sm:mt-3">Card adoption</x-text>
            <x-text class="font-primary text-xl sm:text-stat-value font-bold tabular-nums text-info dark:text-dark-info block mt-0.5">
                {{ $this->cardAdoptionRate }}<span class="text-base font-normal opacity-70">%</span>
            </x-text>
        </flux:card>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-3 mb-8 items-start">
        {{-- Revenue by payment method (span 2) --}}
        <flux:card class="p-4 lg:col-span-2">
            <div class="flex items-center justify-between gap-2 mb-2">
                <x-text class="font-secondary text-sm sm:text-card-title font-semibold text-light-txt-primary dark:text-dark-txt-primary">
                    Revenue by payment method
                </x-text>
                <x-dashboard.trend-pill :value="$this->revenueTrend" :suffix="$this->range . 'd'" />
            </div>
            <div
                wire:ignore
                x-data="barChart(@js($this->revenueByPaymentMethod), { label: 'Revenue (₱)', colorKey: 'success' })"
                @revenue-chart-updated.window="update($event.detail.chart)"
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

        {{-- Queue status (today) --}}
        <flux:card class="p-4">
            <div class="flex items-center justify-between gap-2 mb-2">
                <x-text class="font-secondary text-sm sm:text-card-title font-semibold text-light-txt-primary dark:text-dark-txt-primary">
                    Queue status (today)
                </x-text>
                <span class="font-secondary text-xs font-medium text-light-txt-muted dark:text-dark-txt-muted">{{ array_sum($this->queueStatusBreakdown['data']) }} today</span>
            </div>
            <div
                wire:ignore
                x-data="donutChart(@js($this->queueStatusBreakdown))"
                @queue-status-chart-updated.window="update($event.detail.chart)"
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

        {{-- NEW: Queue payment mode split --}}
        <flux:card class="p-4">
            <div class="flex items-center justify-between gap-2 mb-2">
                <x-text class="font-secondary text-sm sm:text-card-title font-semibold text-light-txt-primary dark:text-dark-txt-primary">
                    Queue payment mode
                </x-text>
                <span class="font-secondary text-xs font-medium text-light-txt-muted dark:text-dark-txt-muted">Today</span>
            </div>
            <div
                wire:ignore
                x-data="donutChart(@js($this->queuePaymentModeSplit))"
                @queue-payment-mode-updated.window="update($event.detail.chart)"
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

        {{-- Queue volume by vehicle type (span 2, sits beside Queue payment mode) --}}
        <flux:card class="p-4 lg:col-span-2">
            <div class="flex items-center justify-between gap-2 mb-2">
                <x-text class="font-secondary text-sm sm:text-card-title font-semibold text-light-txt-primary dark:text-dark-txt-primary">
                    Queue volume by vehicle type
                </x-text>
                <x-dashboard.trend-pill :value="$this->queueVolumeTrend" :suffix="$this->range . 'd'" />
            </div>
            <div
                wire:ignore
                x-data="barChart(@js($this->queueVolumeByVehicleType), { label: 'Vehicles queued', colorKey: 'primary' })"
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
    </div>

    {{-- ===================== ZONE: RENTING & FEED ===================== --}}
    <div class="flex items-center gap-2.5 text-light-txt-primary dark:text-dark-txt-primary">
        <span class="zone-bar bg-secondary"></span>
        <span class="font-secondary text-nav-label font-bold uppercase tracking-widest">Renting &amp; Feed Activity</span>
    </div>
    <hr class="zone-rule border-light-bd-default dark:border-dark-bd-default">

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-3 mb-8">
        <flux:card class="p-4">
            <div class="flex items-center justify-between gap-2 mb-2">
                <x-text class="font-secondary text-sm sm:text-card-title font-semibold text-light-txt-primary dark:text-dark-txt-primary">
                    Posts by role <span class="opacity-60">(rentals)</span>
                </x-text>
                <x-dashboard.trend-pill :value="$this->postsTrend" :suffix="$this->range . 'd'" />
            </div>
            <div
                wire:ignore
                x-data="donutChart(@js($this->postsByRole))"
                @posts-by-role-chart-updated.window="update($event.detail.chart)"
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
            <div class="flex items-center justify-between gap-2 mb-2">
                <x-text class="font-secondary text-sm sm:text-card-title font-semibold text-light-txt-primary dark:text-dark-txt-primary">
                    Rent transaction funnel
                </x-text>
                <span class="font-secondary text-xs font-medium text-light-txt-muted dark:text-dark-txt-muted">{{ array_sum($this->rentTransactionFunnel['data']) }} total</span>
            </div>
            <div
                wire:ignore
                x-data="donutChart(@js($this->rentTransactionFunnel))"
                @rent-funnel-chart-updated.window="update($event.detail.chart)"
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
            <div class="flex items-center justify-between gap-2 mb-2">
                <x-text class="font-secondary text-sm sm:text-card-title font-semibold text-light-txt-primary dark:text-dark-txt-primary">
                    Trip request status
                </x-text>
                <span class="font-secondary text-xs font-medium text-light-txt-muted dark:text-dark-txt-muted">{{ array_sum($this->tripRequestStatusSplit['data']) }} total</span>
            </div>
            <div
                wire:ignore
                x-data="donutChart(@js($this->tripRequestStatusSplit))"
                @trip-request-chart-updated.window="update($event.detail.chart)"
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

    {{-- ===================== ZONE: CARDS & AUDIT ===================== --}}
    <div class="flex items-center gap-2.5 text-light-txt-primary dark:text-dark-txt-primary">
        <span class="zone-bar bg-info dark:bg-dark-info"></span>
        <span class="font-secondary text-nav-label font-bold uppercase tracking-widest">Cards &amp; Audit</span>
    </div>
    <hr class="zone-rule border-light-bd-default dark:border-dark-bd-default">

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-3">
        <flux:card class="p-4">
            <div class="flex items-center justify-between gap-2 mb-2">
                <x-text class="font-secondary text-sm sm:text-card-title font-semibold text-light-txt-primary dark:text-dark-txt-primary">
                    Cards by status
                </x-text>
                <span class="font-secondary text-xs font-medium text-light-txt-muted dark:text-dark-txt-muted">{{ array_sum($this->cardsByStatus['data']) }} total</span>
            </div>
            <div
                wire:ignore
                x-data="donutChart(@js($this->cardsByStatus))"
                @cards-status-chart-updated.window="update($event.detail.chart)"
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

        <flux:card class="p-4 lg:col-span-2">
            <div class="flex items-center justify-between gap-2 mb-2">
                <x-text class="font-secondary text-sm sm:text-card-title font-semibold text-light-txt-primary dark:text-dark-txt-primary">
                    Audit action volume
                </x-text>
                <x-dashboard.trend-pill :value="$this->auditTrend" :suffix="$this->range . 'd'" />
            </div>
            <div
                wire:ignore
                x-data="barChart(@js($this->auditActionVolume), { label: 'Actions', colorKey: 'secondary' })"
                @audit-action-chart-updated.window="update($event.detail.chart)"
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

        {{-- Recent audit entries table (latest 5) --}}
        <flux:card class="p-0 lg:col-span-3 overflow-hidden">
            <div class="px-4 pt-4">
                <x-text class="font-secondary text-sm sm:text-card-title font-semibold text-light-txt-primary dark:text-dark-txt-primary">
                    Recent audit entries
                </x-text>
            </div>
            <div class="overflow-x-auto mt-2">
                <table class="w-full min-w-[640px] font-secondary text-table-row">
                    <thead>
                        <tr class="text-left text-light-txt-body dark:text-dark-txt-body border-b border-light-bd-default dark:border-dark-bd-default">
                            <th class="py-2 px-4 font-semibold">User</th>
                            <th class="py-2 px-4 font-semibold">Action</th>
                            <th class="py-2 px-4 font-semibold">Subject</th>
                            <th class="py-2 px-4 font-semibold">Channel</th>
                            <th class="py-2 px-4 font-semibold text-right">When</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->recentAuditEntries as $entry)
                            <tr class="border-b border-light-bd-default/50 dark:border-dark-bd-default/50 last:border-0">
                                <td class="py-2.5 px-4 text-light-txt-body dark:text-dark-txt-body">{{ $entry->user->name ?? 'System' }}</td>
                                <td class="py-2.5 px-4">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-badge font-medium bg-light-subtle dark:bg-dark-subtle text-light-txt-primary dark:text-dark-txt-primary">
                                        {{ $entry->action }}
                                    </span>
                                </td>
                                <td class="py-2.5 px-4 text-light-txt-muted dark:text-dark-txt-muted">{{ $entry->subject }}</td>
                                <td class="py-2.5 px-4 text-light-txt-muted dark:text-dark-txt-muted">{{ $entry->channel }}</td>
                                <td class="py-2.5 px-4 text-right font-secondary text-timestamp tabular-nums text-light-txt-muted dark:text-dark-txt-muted whitespace-nowrap">
                                    {{ $entry->created_at->diffForHumans() }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-8 text-center text-light-txt-muted dark:text-dark-txt-muted">
                                    No recent audit entries.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="h-1"></div>
        </flux:card>
    </div>
</div>
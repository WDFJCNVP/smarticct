<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use App\Models\Vehicle;
use App\Models\Queue;
use App\Models\Card;
use App\Models\CardTransaction;
use App\Models\PostInterest;
use App\Models\OperatorTicketRate;
use Illuminate\Support\Facades\Auth;

use App\Services\OperatorDisbursementService;
use App\Services\PaymongoDisbursementService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

new #[Layout('layouts.operator-layout')] class extends Component
{
    public string $range = '7'; // days: 7, 14, 30
    public string $vehicleTypeFilter = '';
    public string $routeFilter = '';
    public string $paymentMethodFilter = '';

    public bool $showWithdrawModal = false;
    public string $withdrawAmount = '';
    public string $provider = 'instapay';
    public string $accountNumber = '';
    public string $accountName = '';
    public string $bic = '';

    public string $selectedBic = '';

    public string $institutionCategory = 'ewallet'; // 'ewallet' | 'bank'

    public function setInstitutionCategory(string $category)
    {
        $this->institutionCategory = $category;
        $this->selectedBic = ''; // clear previous pick, avoid stale selection across tabs
    }

    #[Computed]
    public function categorizedInstitutions()
    {
        $ewalletNames = collect(config('paymongo.ewallets'))->map(fn ($n) => strtolower($n));

        return collect($this->receivingInstitutions)->filter(function ($institution) use ($ewalletNames) {
            $name = strtolower($institution['attributes']['name'] ?? '');
            $isEwallet = $ewalletNames->contains($name);

            return $this->institutionCategory === 'ewallet' ? $isEwallet : ! $isEwallet;
        })->values();
    }

    #[Computed]
    public function receivingInstitutions()
    {
        return Cache::remember(
            "paymongo:receiving_institutions:{$this->provider}",
            now()->addHours(6),
            fn () => app(PaymongoDisbursementService::class)->listReceivingInstitutions($this->provider)
        );
        
    }

    public function submitWithdrawal()
    {
        $validated = $this->validate([
            'withdrawAmount' => 'required|numeric|min:1',
            'provider'       => 'required|in:instapay,pesonet',
            'accountNumber'  => 'required|string',
            'accountName'    => 'required|string',
            'selectedBic'    => 'required|string',
        ]);

        $card = $this->userCard;

        if (!$card) {
            $this->addError('withdrawAmount', 'No card found.');
            return;
        }

        $fee = $validated['provider'] === 'instapay' ? 10.00 : 0.00;
        $totalDeduction = $validated['withdrawAmount'] + $fee;

        if ($totalDeduction > $card->balance) {
            $this->addError('withdrawAmount', 'Insufficient balance to cover this withdrawal plus the transfer fee.');
            return;
        }

        $succeeded = DB::transaction(function () use ($card, $validated, $totalDeduction) {
            $balanceBefore = $card->balance;

            $card->decrement('balance', $totalDeduction);
            $card->refresh();

            $result = app(OperatorDisbursementService::class)->createWithdrawal([
                'provider'       => $validated['provider'],
                'amount'         => $validated['withdrawAmount'],
                'account_number' => $validated['accountNumber'],
                'account_name'   => $validated['accountName'],
                'bic'            => $validated['selectedBic'],
                'operator_id'    => auth()->id(),
            ]);

            \Log::info('PayMongo disbursement response', $result['response']); // TEMP — remove after debugging

            CardTransaction::create([
                'card_id'          => $card->id,
                'transaction_type' => 'withdrawal',
                'reference_no'     => $result['reference_number'],
                'amount'           => $totalDeduction,
                'balance_before'   => $balanceBefore,
                'balance_after'    => $card->balance,
                'status'           => $result['successful'] ? 'pending' : 'failed',
                'transaction_time' => now(),
                'source'           => 'operator_dashboard',
                'message'          => "Withdrawal via {$validated['provider']} to {$validated['accountNumber']}",
                'metadata'         => $result['response'],
            ]);

            if (!$result['successful']) {
                $card->increment('balance', $totalDeduction); // roll back
            }

            return $result['successful'];
        });

        if (!$succeeded) {
            $this->addError('withdrawAmount', 'Withdrawal request failed. Please check your details and try again.');
            return; // don't reset the form or close the modal
        }

        $this->reset(['withdrawAmount', 'accountNumber', 'accountName', 'bic']);
        $this->showWithdrawModal = false;
        unset($this->cardBalance);
        $this->dispatch('status-strip-updated', queueing: $this->currentlyQueueing, balance: $this->cardBalance);
    }

    #[Computed]
    public function todayEarnings()
    {
        return $this->userCard
            ?->cardTransactions()
            ->where('transaction_type', 'fare_earning')
            ->where('status', 'success')
            ->whereDate('transaction_time', today())
            ->sum('amount') ?? 0;
    }

    #[Computed]
    public function userCard(): ?Card
    {
        return Card::with(['user', 'cardTransactions' => function ($query) {
            $query->latest('transaction_time')->limit(10);
        }])->where('user_id', auth()->id())->first();
    }

    private function applyQueueFilters($query)
    {
        return $query
            ->when($this->vehicleTypeFilter, fn ($q, $v) => $q->where('queues.vehicle_type', $v))
            ->when($this->routeFilter, fn ($q, $v) => $q->where('queues.destination', $v));
    }

    #[Computed]
    public function activeFilterCount()
    {
        return collect([$this->vehicleTypeFilter, $this->routeFilter, $this->paymentMethodFilter])
            ->filter(fn ($v) => filled($v))
            ->count();
    }

    #[Computed]
    public function availableRoutes()
    {
        return Queue::where('user_id', Auth::id())
            ->whereNotNull('destination')
            ->distinct()
            ->orderBy('destination')
            ->pluck('destination');
    }

    #[Computed]
    public function availableVehicleTypes()
    {
        return Vehicle::where('user_id', Auth::id())
            ->whereNotNull('vehicle_type')
            ->distinct()
            ->orderBy('vehicle_type')
            ->pluck('vehicle_type');
    }

    // ===================== KPI CARDS =====================

    #[Computed]
    public function vehiclesRegistered()
    {
        return Vehicle::where('user_id', Auth::id())
            ->when($this->vehicleTypeFilter, fn ($q, $v) => $q->where('vehicle_type', $v))
            ->count();
    }

    #[Computed]
    public function currentlyQueueing()
    {
        return $this->applyQueueFilters(
            Queue::where('user_id', Auth::id())->whereIn('status', ['staging', 'loading'])
        )->count();
    }

    #[Computed]
    public function queuesThisWeek()
    {
        return $this->applyQueueFilters(
            Queue::where('user_id', Auth::id())
                ->whereBetween('time_queued', [now()->startOfWeek(), now()->endOfWeek()])
        )->count();
    }

    #[Computed]
    public function queueFeePaidToday()
    {
        return $this->applyQueueFilters(
            Queue::where('queues.user_id', Auth::id())->whereDate('time_queued', today())
        )
            ->join('operator_ticket_rates', 'queues.vehicle_type', '=', 'operator_ticket_rates.vehicle_type')
            ->sum('operator_ticket_rates.queueing_fee');
    }

    #[Computed]
    public function cardBalance()
    {
        $card = Card::where('user_id', Auth::id())->first();
        return $card ? $card->balance : null;
    }

    #[Computed]
    public function rentalEngagement()
    {
        return PostInterest::whereHas('post', function ($q) {
            $q->where('user_id', Auth::id())->where('type', 'rental');
        })->count();
    }

    // ===================== CHARTS =====================

    #[Computed]
    public function queuesOverTime()
    {
        $days = max(1, (int) $this->range);
        $start = today()->subDays($days - 1);
        $end = today();

        $counts = $this->applyQueueFilters(
            Queue::where('user_id', Auth::id())
                ->whereBetween('time_queued', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
        )
            ->selectRaw('DATE(time_queued) as day, COUNT(*) as total')
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
    public function vehicleCountByType()
    {
        $counts = Vehicle::where('user_id', Auth::id())
            ->whereNotNull('vehicle_type')
            ->when($this->vehicleTypeFilter, fn ($q, $v) => $q->where('vehicle_type', $v))
            ->selectRaw('vehicle_type, COUNT(*) as total')
            ->groupBy('vehicle_type')
            ->pluck('total', 'vehicle_type');

        return [
            'labels' => $counts->keys()->map(fn ($t) => ucfirst(str_replace('_', ' ', $t)))->values()->toArray(),
            'data' => $counts->values()->toArray(),
        ];
    }

    #[Computed]
    public function rentalInterestStatusSplit()
    {
        $counts = PostInterest::whereHas('post', function ($q) {
            $q->where('user_id', Auth::id())->where('type', 'rental');
        })
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'labels' => $counts->keys()->map(fn ($s) => ucfirst($s))->values()->toArray(),
            'data' => $counts->values()->toArray(),
        ];
    }

    #[Computed]
    public function vehicleDocumentExpiry()
    {
        return Vehicle::where('user_id', Auth::id())
            ->when($this->vehicleTypeFilter, fn ($q, $v) => $q->where('vehicle_type', $v))
            ->orderBy('plate_number')
            ->get(['id', 'plate_number', 'has_or_cr', 'or_cr_expiry_date', 'has_franchise', 'franchise_expiry_date']);
    }

    /**
     * Buckets a single document's expiry into a status label + badge color
     * so the blade can just print $status['label'] and use $status['class'].
     */
    public function documentStatus(bool $has, $expiry): array
    {
        if (! $has || ! $expiry) {
            return ['label' => 'No record', 'class' => 'bg-light-subtle text-light-txt-muted dark:bg-dark-subtle dark:text-dark-txt-muted'];
        }
        if ($expiry->lt(today())) {
            return ['label' => $expiry->format('m/d/Y'), 'class' => 'bg-danger/10 text-danger dark:bg-dark-danger/20 dark:text-dark-danger'];
        }
        if ($expiry->lte(today()->addDays(30))) {
            return ['label' => $expiry->format('m/d/Y'), 'class' => 'bg-warning/10 text-warning dark:bg-dark-warning/20 dark:text-dark-warning'];
        }
        return ['label' => $expiry->format('m/d/Y'), 'class' => 'bg-success/10 text-success dark:bg-dark-success/20 dark:text-dark-success'];
    }

    // ===================== TABLES / LISTS =====================

    #[Computed]
    public function vehicleRoster()
    {
        return Vehicle::where('user_id', Auth::id())
            ->when($this->vehicleTypeFilter, fn ($q, $v) => $q->where('vehicle_type', $v))
            ->latest()
            ->limit(5)
            ->get(['id', 'plate_number', 'vehicle_type', 'created_at']);
    }

    #[Computed]
    public function myQueueFeeRates()
    {
        $ownedTypes = $this->availableVehicleTypes;

        $types = ($this->vehicleTypeFilter && $ownedTypes->contains($this->vehicleTypeFilter))
            ? collect([$this->vehicleTypeFilter])
            : $ownedTypes;

        if ($types->isEmpty()) {
            return collect();
        }

        return OperatorTicketRate::whereIn('vehicle_type', $types)
            ->orderBy('vehicle_type')
            ->get();
    }

    #[Computed]
    public function recentQueueEntries()
    {
        return $this->applyQueueFilters(Queue::where('user_id', Auth::id()))
            ->latest('time_queued')
            ->limit(5)
            ->get(['id', 'vehicle_type', 'destination', 'status', 'time_queued', 'time_departed']);
    }

    // #[Computed]
    // public function recentCardTransactions()
    // {
    //     $card = Card::where('user_id', Auth::id())->first();
    //     if (!$card) return collect();

    //     return CardTransaction::where('card_id', $card->id)
    //         ->when($this->paymentMethodFilter, fn ($q, $v) => $q->where('payment_method', $v))
    //         ->latest()
    //         ->limit(5)
    //         ->get(['id', 'transaction_type', 'amount', 'payment_method', 'created_at']);
    // }

    #[Computed]
    public function recentRentalInquiries()
    {
        return PostInterest::whereHas('post', function ($q) {
            $q->where('user_id', Auth::id())->where('type', 'rental');
        })
            ->with('user:id,name')
            ->latest()
            ->limit(5)
            ->get(['id', 'post_id', 'user_id', 'status', 'created_at']);
    }

    // ===================== FILTER UPDATES =====================

    // ===================== TREND (REAL DATA — REPLACES "LIVE") =====================

    #[Computed]
    public function queuesTrend()
    {
        $days = max(1, (int) $this->range);
        $currentStart = today()->subDays($days - 1)->startOfDay();
        $currentEnd = today()->endOfDay();
        $previousStart = today()->subDays(($days * 2) - 1)->startOfDay();
        $previousEnd = today()->subDays($days)->endOfDay();

        $current = $this->applyQueueFilters(
            Queue::where('user_id', Auth::id())->whereBetween('time_queued', [$currentStart, $currentEnd])
        )->count();
        $previous = $this->applyQueueFilters(
            Queue::where('user_id', Auth::id())->whereBetween('time_queued', [$previousStart, $previousEnd])
        )->count();

        if ($previous == 0) {
            return $current > 0 ? 100.0 : null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    public function updatedRange()
    {
        $this->pushChartUpdates();
    }

    public function updatedVehicleTypeFilter()
    {
        $this->pushChartUpdates();
    }

    public function updatedRouteFilter()
    {
        $this->pushChartUpdates();
    }

    public function updatedPaymentMethodFilter()
    {
        $this->pushChartUpdates();
    }

    public function resetFilters()
    {
        $this->reset(['range', 'vehicleTypeFilter', 'routeFilter', 'paymentMethodFilter']);
        $this->range = '7';
        $this->pushChartUpdates();
    }

    private function pushChartUpdates()
    {
        $this->dispatch('queues-chart-updated', chart: $this->queuesOverTime);
        $this->dispatch('vehicle-count-chart-updated', chart: $this->vehicleCountByType);
    }

    // ===================== LIVE REFRESH =====================

    #[On('echo:vehicle-queue,.QueuedVehicleEvent')]
    #[On('echo:card-transaction-created,.CardTransactionCreated')]
    #[On('echo:post-interest-created,.PostInterestCreated')]
    public function refreshDashboard()
    {
        unset(
            $this->vehiclesRegistered,
            $this->currentlyQueueing,
            $this->queuesThisWeek,
            $this->queueFeePaidToday,
            $this->cardBalance,
            $this->rentalEngagement,
            $this->queuesOverTime,
            $this->vehicleCountByType,
            $this->rentalInterestStatusSplit,
            $this->vehicleDocumentExpiry,
            $this->vehicleRoster,
            $this->myQueueFeeRates,
            $this->recentQueueEntries,
            // $this->recentCardTransactions,  // ← commented out (property not defined)
            $this->recentRentalInquiries,
            $this->queuesTrend,
        );

        $this->dispatch('queues-chart-updated', chart: $this->queuesOverTime);
        $this->dispatch('vehicle-count-chart-updated', chart: $this->vehicleCountByType);
        $this->dispatch('rental-status-chart-updated', chart: $this->rentalInterestStatusSplit);
        $this->dispatch('status-strip-updated', queueing: $this->currentlyQueueing, balance: $this->cardBalance);
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
                heading="Dashboard"
                description="Your vehicle, queueing, and rental overview."
                class="text-xl sm:text-2xl font-extrabold"
            />

            {{-- Right side: date + clock (desktop), bell, filter --}}
            <div class="flex items-center gap-2 sm:gap-3 shrink-0">
                {{-- Date + Clock container (hidden on mobile) --}}
                <div class="hidden sm:flex flex-col items-end">
                    {{-- Date --}}
                    <span
                        class="text-xs text-light-txt-muted dark:text-dark-txt-muted font-secondary leading-none mb-0.5"
                        x-data="{ date: '' }"
                        x-init="date = new Date().toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })"
                        x-text="date"
                    ></span>

                    {{-- Clock with live dot --}}
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

                {{-- Notifications --}}
                <button
                    type="button"
                    class="relative flex items-center justify-center w-8 h-8 sm:w-9 sm:h-9 rounded-lg border border-light-bd-default dark:border-dark-bd-default text-light-txt-muted dark:text-dark-txt-muted hover:bg-light-subtle dark:hover:bg-dark-subtle transition shrink-0"
                    aria-label="Notifications"
                >
                    <flux:icon.bell class="w-3.5 h-3.5 sm:w-4 sm:h-4" />
                    <span class="absolute top-1.5 right-1.5 w-1.5 h-1.5 rounded-full bg-danger dark:bg-dark-danger"></span>
                </button>

                <flux:modal.trigger name="dashboard-filters">
                    <button
                        type="button"
                        class="relative flex items-center gap-1.5 sm:gap-2 px-2.5 sm:px-3.5 h-8 sm:h-9 rounded-lg border border-light-bd-default dark:border-dark-bd-default text-light-txt-body dark:text-dark-txt-body hover:bg-light-subtle dark:hover:bg-dark-subtle transition font-secondary text-xs sm:text-table-row shrink-0"
                    >
                        <flux:icon.funnel class="w-3 h-3 sm:w-3.5 sm:h-3.5 text-light-txt-muted dark:text-dark-txt-muted" />
                        <span class="hidden sm:inline">Filters</span>
                        @if ($this->activeFilterCount > 0)
                            <span class="flex items-center justify-center w-4 h-4 rounded-full bg-primary dark:bg-dark-txt-primary text-white dark:text-primary text-[10px] font-bold">
                                {{ $this->activeFilterCount }}
                            </span>
                        @endif
                    </button>
                </flux:modal.trigger>
            </div>
        </div>

        <hr class="zone-rule border-light-bd-default dark:border-dark-bd-default mt-4 mb-0">
    </div>

    {{-- ===================== FILTERS MODAL ===================== --}}
    <flux:modal name="dashboard-filters" class="md:w-96">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Filters</flux:heading>
                <flux:subheading>Narrow down what this dashboard shows.</flux:subheading>
            </div>

            <div class="space-y-4">
                <flux:select wire:model.live="range" label="Date range">
                    <flux:select.option value="7">Last 7 days</flux:select.option>
                    <flux:select.option value="14">Last 14 days</flux:select.option>
                    <flux:select.option value="30">Last 30 days</flux:select.option>
                </flux:select>

                <flux:select wire:model.live="vehicleTypeFilter" label="Vehicle type">
                    <flux:select.option value="">All vehicle types</flux:select.option>
                    @foreach ($this->availableVehicleTypes as $type)
                        <flux:select.option value="{{ $type }}">{{ ucfirst(str_replace('_', ' ', $type)) }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model.live="routeFilter" label="Route">
                    <flux:select.option value="">All routes</flux:select.option>
                    @foreach ($this->availableRoutes as $route)
                        <flux:select.option value="{{ $route }}">{{ $route }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model.live="paymentMethodFilter" label="Payment method">
                    <flux:select.option value="">All methods</flux:select.option>
                    <flux:select.option value="cash">Cash</flux:select.option>
                    <flux:select.option value="gcash">GCash</flux:select.option>
                    <flux:select.option value="paymaya">PayMaya</flux:select.option>
                    <flux:select.option value="card">Smart card</flux:select.option>
                </flux:select>
            </div>

            <div class="flex items-center justify-between pt-4 border-t border-light-bd-default dark:border-dark-bd-default">
                <button
                    type="button"
                    wire:click="resetFilters"
                    class="font-secondary text-table-row text-light-txt-muted dark:text-dark-txt-muted hover:text-light-txt-primary dark:hover:text-dark-txt-primary transition"
                >
                    Reset all
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
            queueing: @js($this->currentlyQueueing),
            balance: @js($this->cardBalance),
            todayBalance: @js($this->todayEarnings),
            flipQ: false, flipB: false,
        }"
        @status-strip-updated.window="
            if ($event.detail.queueing !== queueing) { queueing = $event.detail.queueing; flipQ = true; setTimeout(() => flipQ = false, 500); }
            if ($event.detail.balance !== balance) { balance = $event.detail.balance; flipB = true; setTimeout(() => flipB = false, 500); }
        "
    >
        <div class="flex flex-col sm:flex-row divide-y sm:divide-y-0 sm:divide-x divide-white/15">
            <div class="flex items-center gap-3 px-5 py-4 flex-1">
                <flux:icon.truck class="w-5 h-5 text-white/70 shrink-0" />
                <div>
                    <div class="font-secondary text-nav-label font-semibold uppercase tracking-wide text-white/80">My Vehicle Queueing</div>
                    <div class="font-primary text-3xl font-extrabold tabular-nums" :class="{ 'flap-flip': flipQ }" x-text="queueing"></div>
                </div>
            </div>
            <div class="flex items-center gap-3 px-5 py-4 flex-1">
                <flux:icon.credit-card class="w-5 h-5 text-white/70 shrink-0" />
                <div>
                    <div class="font-secondary text-nav-label font-semibold uppercase tracking-wide text-white/80">Total Earnings Today</div>
                    <div class="font-primary text-3xl font-extrabold tabular-nums" :class="{ 'flap-flip': flipB }">
                        <span x-show="balance !== null" x-text="'₱' + Number(todayBalance).toFixed(0)"></span>
                        <span x-show="balance === null" class="text-base font-normal opacity-80">No card</span>
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-3 px-5 py-4 flex-1 justify-between">
                <div class="flex items-center gap-3">
                    <flux:icon.credit-card class="w-5 h-5 text-white/70 shrink-0" />
                    <div>
                        <div class="font-secondary text-nav-label font-semibold uppercase tracking-wide text-white/80">Total Balance</div>
                        <div class="font-primary text-3xl font-extrabold tabular-nums" :class="{ 'flap-flip': flipB }">
                            <span x-show="balance !== null" x-text="'₱' + Number(balance).toFixed(0)"></span>
                            <span x-show="balance === null" class="text-base font-normal opacity-80">No card</span>
                        </div>
                    </div>
                </div>
               <x-button variant="primary" color="yellow" href="{{ route('withdraw') }}">Withdraw</x-button>
            </div>
        </div>
    </div>

    {{-- ===================== ZONE: OVERVIEW ===================== --}}
    <div class="flex items-center gap-2.5 text-light-txt-primary dark:text-dark-txt-primary">
        <span class="zone-bar bg-primary dark:bg-dark-txt-primary"></span>
        <span class="font-secondary text-nav-label font-bold uppercase tracking-widest">Overview</span>
    </div>
    <hr class="zone-rule border-light-bd-default dark:border-dark-bd-default">

    <div class="grid grid-cols-2 lg:grid-cols-3 gap-2 sm:gap-3 mb-4">

        <flux:card class="p-3 sm:p-4">
            <div class="flex items-center justify-center w-8 h-8 sm:w-10 sm:h-10 rounded-xl bg-primary/10 dark:bg-primary/20 shrink-0">
                <flux:icon.truck class="w-4 h-4 sm:w-5 sm:h-5 text-primary dark:text-dark-txt-primary" />
            </div>
            <x-text class="font-secondary text-xs sm:text-stat-label font-medium text-light-txt-body dark:text-dark-txt-body block mt-2.5 sm:mt-3">
                Registered vehicles
            </x-text>
            <x-text class="font-primary text-xl sm:text-stat-value font-bold tabular-nums text-light-txt-primary dark:text-dark-txt-primary block mt-0.5">
                {{ $this->vehiclesRegistered }}
            </x-text>
        </flux:card>

        <flux:card class="p-3 sm:p-4">
            <div class="flex items-center justify-between gap-2">
                <div class="flex items-center justify-center w-8 h-8 sm:w-10 sm:h-10 rounded-xl bg-info/10 dark:bg-dark-info/20 shrink-0">
                    <flux:icon.queue-list class="w-4 h-4 sm:w-5 sm:h-5 text-info dark:text-dark-info" />
                </div>
                <x-dashboard.trend-pill :value="$this->queuesTrend" :suffix="$this->range . 'd'" />
            </div>
            <x-text class="font-secondary text-xs sm:text-stat-label font-medium text-light-txt-body dark:text-dark-txt-body block mt-2.5 sm:mt-3">
                Queues this week
            </x-text>
            <x-text class="font-primary text-xl sm:text-stat-value font-bold tabular-nums text-info dark:text-dark-info block mt-0.5">
                {{ $this->queuesThisWeek }}
            </x-text>
        </flux:card>

        <flux:card class="p-3 sm:p-4 col-span-2 lg:col-span-1">
            <div class="flex items-center justify-center w-8 h-8 sm:w-10 sm:h-10 rounded-xl bg-success/10 dark:bg-dark-success/20 shrink-0">
                <flux:icon.ticket class="w-4 h-4 sm:w-5 sm:h-5 text-success dark:text-dark-success" />
            </div>
            <x-text class="font-secondary text-xs sm:text-stat-label font-medium text-light-txt-body dark:text-dark-txt-body block mt-2.5 sm:mt-3">
                Queue fee paid today
            </x-text>
            <x-text class="font-primary text-xl sm:text-stat-value font-bold tabular-nums text-success dark:text-dark-success block mt-0.5">
                ₱{{ number_format($this->queueFeePaidToday, 2) }}
            </x-text>
        </flux:card>

    </div>

    @if ($this->myQueueFeeRates->isNotEmpty())
        <flux:card class="p-4 mb-8">
            <x-text class="font-secondary text-sm sm:text-card-title font-semibold text-light-txt-primary dark:text-dark-txt-primary block mb-3">
                My queue fee rate{{ $this->myQueueFeeRates->count() > 1 ? 's' : '' }}
            </x-text>
            <div class="flex flex-wrap gap-3">
                @foreach ($this->myQueueFeeRates as $rate)
                    <div class="flex items-center justify-between gap-3 rounded-lg border border-light-bd-default dark:border-dark-bd-default px-4 py-3 flex-1 min-w-[200px]">
                        <div>
                            <x-text class="font-secondary text-stat-label font-medium text-light-txt-body dark:text-dark-txt-body block">
                                {{ ucfirst(str_replace('_', ' ', $rate->vehicle_type)) }}
                            </x-text>
                            <x-text class="font-primary text-lg font-bold text-light-txt-primary dark:text-dark-txt-primary">
                                ₱{{ number_format($rate->queueing_fee, 2) }}<span class="text-sm font-normal opacity-70"> / queue</span>
                            </x-text>
                        </div>
                        <flux:icon.currency-dollar class="w-5 h-5 text-light-txt-muted dark:text-dark-txt-muted shrink-0" />
                    </div>
                @endforeach
            </div>
        </flux:card>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-3 mb-8">
        <flux:card class="p-4 lg:col-span-2">
            <div class="flex items-center justify-between gap-2 mb-2">
                <x-text class="font-secondary text-sm sm:text-card-title font-semibold text-light-txt-primary dark:text-dark-txt-primary">
                    My queues over time
                </x-text>
                <x-dashboard.trend-pill :value="$this->queuesTrend" :suffix="$this->range . 'd'" />
            </div>
            <div
                wire:ignore
                x-data="lineChart(@js($this->queuesOverTime))"
                @queues-chart-updated.window="update($event.detail.chart)"
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
                    Vehicle count by type
                </x-text>
                <span class="font-secondary text-xs font-medium text-light-txt-muted dark:text-dark-txt-muted">{{ array_sum($this->vehicleCountByType['data']) }} total</span>
            </div>
            <div
                wire:ignore
                x-data="barChart(@js($this->vehicleCountByType), { label: 'Vehicles', colorKey: 'info' })"
                @vehicle-count-chart-updated.window="update($event.detail.chart)"
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
        <span class="font-secondary text-nav-label font-bold uppercase tracking-widest">Renting &amp; Feed</span>
    </div>
    <hr class="zone-rule border-light-bd-default dark:border-dark-bd-default">

    <div class="flex flex-wrap gap-2 sm:gap-3 mb-4">
        <flux:card class="p-3 sm:p-4 flex-1 min-w-[200px]">
            <div class="flex items-center gap-1.5 sm:gap-2.5">
                <div class="flex items-center justify-center w-6 h-6 sm:w-8 sm:h-8 rounded-lg bg-secondary/10 shrink-0">
                    <flux:icon.chat-bubble-left-right class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-secondary" />
                </div>
                <x-text class="font-secondary text-xs sm:text-stat-label font-medium text-light-txt-body dark:text-dark-txt-body">
                    Inquiries on my rental posts
                </x-text>
            </div>
            <x-text class="font-primary text-xl sm:text-stat-value font-bold tabular-nums text-light-txt-primary dark:text-dark-txt-primary block mt-2 sm:mt-3">
                {{ $this->rentalEngagement }}
            </x-text>
        </flux:card>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-3 mb-8">
        <flux:card class="p-4">
            <div class="flex items-center justify-between gap-2 mb-2">
                <x-text class="font-secondary text-sm sm:text-card-title font-semibold text-light-txt-primary dark:text-dark-txt-primary">
                    Inquiry status
                </x-text>
                <span class="font-secondary text-xs font-medium text-light-txt-muted dark:text-dark-txt-muted">{{ array_sum($this->rentalInterestStatusSplit['data']) }} total</span>
            </div>
            <div
                wire:ignore
                x-data="donutChart(@js($this->rentalInterestStatusSplit))"
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
                Recent rental inquiries
            </x-text>
            <div class="mt-2 space-y-2">
                @forelse ($this->recentRentalInquiries as $inquiry)
                    <div class="flex justify-between items-center border-b border-light-bd-default/50 dark:border-dark-bd-default/50 pb-2">
                        <div>
                            <span class="font-secondary text-sm text-light-txt-body dark:text-dark-txt-body">
                                {{ $inquiry->user->name ?? 'Commuter' }}
                            </span>
                            <span class="block font-secondary text-timestamp text-light-txt-muted dark:text-dark-txt-muted">
                                {{ $inquiry->created_at->diffForHumans() }}
                            </span>
                        </div>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-badge font-medium
                            @if($inquiry->status === 'pending') bg-warning/10 text-warning dark:bg-dark-warning/20 dark:text-dark-warning
                            @elseif($inquiry->status === 'accepted') bg-success/10 text-success dark:bg-dark-success/20 dark:text-dark-success
                            @elseif($inquiry->status === 'declined') bg-danger/10 text-danger dark:bg-dark-danger/20 dark:text-dark-danger
                            @else bg-light-subtle text-light-txt-muted dark:bg-dark-subtle dark:text-dark-txt-muted
                            @endif">
                            {{ ucfirst($inquiry->status) }}
                        </span>
                    </div>
                @empty
                    <p class="text-light-txt-muted dark:text-dark-txt-muted py-4 text-center">No inquiries yet.</p>
                @endforelse
            </div>
        </flux:card>
    </div>

    {{-- ===================== ZONE: VEHICLE ROSTER, ACTIVITY & COMPLIANCE ===================== --}}
    <div class="flex items-center gap-2.5 text-light-txt-primary dark:text-dark-txt-primary">
        <span class="zone-bar bg-info dark:bg-dark-info"></span>
        <span class="font-secondary text-nav-label font-bold uppercase tracking-widest">Vehicle Roster, Activity &amp; Compliance</span>
    </div>
    <hr class="zone-rule border-light-bd-default dark:border-dark-bd-default">

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-3">
        <flux:card class="p-0 overflow-hidden">
            <div class="px-4 pt-4">
                <x-text class="font-secondary text-sm sm:text-card-title font-semibold text-light-txt-primary dark:text-dark-txt-primary">
                    5 latest registered vehicles
                </x-text>
            </div>
            <div class="overflow-x-auto mt-2">
                <table class="w-full font-secondary text-table-row">
                    <thead>
                        <tr class="text-left text-light-txt-body dark:text-dark-txt-body border-b border-light-bd-default dark:border-dark-bd-default">
                            <th class="py-2 px-4 font-semibold">Plate</th>
                            <th class="py-2 px-4 font-semibold text-right">Type</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->vehicleRoster as $vehicle)
                            <tr class="border-b border-light-bd-default/50 dark:border-dark-bd-default/50 last:border-0">
                                <td class="py-2.5 px-4 text-light-txt-body dark:text-dark-txt-body">{{ $vehicle->plate_number }}</td>
                                <td class="py-2.5 px-4 text-right">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-badge font-medium bg-light-subtle dark:bg-dark-subtle text-light-txt-primary dark:text-dark-txt-primary">
                                        {{ ucfirst($vehicle->vehicle_type) }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" class="py-8 text-center text-light-txt-muted dark:text-dark-txt-muted">
                                    No vehicles registered. Additional vehicles must go through admin.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="h-1"></div>
        </flux:card>

        <flux:card class="p-4">
            <x-text class="font-secondary text-sm sm:text-card-title font-semibold text-light-txt-primary dark:text-dark-txt-primary">
                5 latest queue entries
            </x-text>
            <div class="mt-2 space-y-2">
                @forelse ($this->recentQueueEntries as $entry)
                    <div class="flex justify-between items-center border-b border-light-bd-default/50 dark:border-dark-bd-default/50 pb-2">
                        <div>
                            <span class="font-secondary text-sm text-light-txt-body dark:text-dark-txt-body">
                                {{ ucfirst($entry->vehicle_type) }} → {{ $entry->destination }}
                            </span>
                            <span class="block font-secondary text-timestamp text-light-txt-muted dark:text-dark-txt-muted">
                                {{ $entry->time_queued?->diffForHumans() }}
                            </span>
                        </div>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-badge font-medium bg-light-subtle dark:bg-dark-subtle text-light-txt-primary dark:text-dark-txt-primary">
                            {{ ucfirst($entry->status) }}
                        </span>
                    </div>
                @empty
                    <p class="text-light-txt-muted dark:text-dark-txt-muted py-4 text-center">No queue entries yet.</p>
                @endforelse
            </div>
        </flux:card>

        {{-- ===================== OR/CR & FRANCHISE EXPIRY ===================== --}}
        <flux:card class="p-0 overflow-hidden">
            <div class="px-4 pt-4">
                <x-text class="font-secondary text-sm sm:text-card-title font-semibold text-light-txt-primary dark:text-dark-txt-primary">
                    OR/CR &amp; franchise expiry
                </x-text>
            </div>
            <div class="overflow-x-auto mt-2">
                <table class="w-full font-secondary text-table-row">
                    <thead>
                        <tr class="text-left text-light-txt-body dark:text-dark-txt-body border-b border-light-bd-default dark:border-dark-bd-default">
                            <th class="py-2 px-4 font-semibold">Plate</th>
                            <th class="py-2 px-4 font-semibold text-right">OR/CR</th>
                            <th class="py-2 px-4 font-semibold text-right">Franchise</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->vehicleDocumentExpiry as $vehicle)
                            @php
                                $orCr = $this->documentStatus((bool) $vehicle->has_or_cr, $vehicle->or_cr_expiry_date);
                                $franchise = $this->documentStatus((bool) $vehicle->has_franchise, $vehicle->franchise_expiry_date);
                            @endphp
                            <tr class="border-b border-light-bd-default/50 dark:border-dark-bd-default/50 last:border-0">
                                <td class="py-2.5 px-4 text-light-txt-body dark:text-dark-txt-body">{{ $vehicle->plate_number }}</td>
                                <td class="py-2.5 px-4 text-right">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-badge font-medium whitespace-nowrap {{ $orCr['class'] }}">
                                        {{ $orCr['label'] }}
                                    </span>
                                </td>
                                <td class="py-2.5 px-4 text-right">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-badge font-medium whitespace-nowrap {{ $franchise['class'] }}">
                                        {{ $franchise['label'] }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="py-8 text-center text-light-txt-muted dark:text-dark-txt-muted">
                                    No vehicles registered. Additional vehicles must go through admin.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="h-1"></div>
        </flux:card>
    </div>

    <flux:modal name="withdraw-modal" class="max-w-md">
        <form wire:submit="submitWithdrawal" class="space-y-4">
            <div>
                <flux:heading size="lg">Withdraw balance</flux:heading>
                <flux:subheading>Send your card balance to a bank account or e-wallet.</flux:subheading>
            </div>

            <flux:input wire:model="withdrawAmount" label="Amount (₱)" type="number" step="0.01" />

            <flux:select wire:model.live="provider" label="Send via">
                <flux:select.option value="instapay">InstaPay (instant, ₱10 fee)</flux:select.option>
                <flux:select.option value="pesonet">PESONet (free, next banking day)</flux:select.option>
            </flux:select>

            <flux:input wire:model="accountName" label="Account name" />
            <flux:input wire:model="accountNumber" label="Account number" />

            <div>
                <flux:text size="sm" class="mb-1.5">Send to</flux:text>
                <div class="grid grid-cols-2 gap-2">
                    <flux:button
                        type="button"
                        wire:click="setInstitutionCategory('ewallet')"
                        variant="{{ $institutionCategory === 'ewallet' ? 'primary' : 'outline' }}"
                        class="w-full"
                    >
                        📱 E-Wallet
                    </flux:button>
                    <flux:button
                        type="button"
                        wire:click="setInstitutionCategory('bank')"
                        variant="{{ $institutionCategory === 'bank' ? 'primary' : 'outline' }}"
                        class="w-full"
                    >
                        🏦 Bank
                    </flux:button>
                </div>
            </div>

            <flux:select wire:model="selectedBic" label="{{ $institutionCategory === 'ewallet' ? 'Choose your e-wallet' : 'Choose your bank' }}">
                <flux:select.option value="">Select one</flux:select.option>
                @foreach ($this->categorizedInstitutions as $institution)
                    <flux:select.option value="{{ $institution['attributes']['provider_code'] }}">
                        {{ $institution['attributes']['name'] }}
                    </flux:select.option>
                @endforeach
            </flux:select>

            <flux:button type="submit" variant="primary" class="w-full">Confirm Withdrawal</flux:button>
        </form>
    </flux:modal>

</div>
<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use App\Models\CardTransaction;
use App\Models\CashTransaction;
use App\Models\TopUpTransaction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;

new #[Layout('layouts.cashier-layout')] class extends Component
{
    use WithPagination;

    #[Url(as: 'from')]
    public string $filterFrom = '';

    #[Url(as: 'to')]
    public string $filterTo = '';

    #[Url]
    public string $sortBy = 'desc'; // 'desc' = latest first, 'asc' = oldest first

    #[Url]
    public string $type = ''; // '', queue_fees, fare_payments, topups

    #[Url]
    public string $search = '';

    public int $perPage = 15;

    // ===================== EXPORT MODAL =====================
    // Reuses the same cashier.transactions.export endpoint the admin Card
    // Top-Ups page uses — it already scopes to "own transactions only" for
    // a cashier and "all cashiers" for an admin, so no extra wiring needed.

    public string $exportDateFrom = '';
    public string $exportDateTo = '';
    public string $exportType = ''; // '', queue_fees, fare_payments, topups
    public string $exportPaper = 'legal';
    public string $exportOrientation = 'portrait';

    public function mount()
    {
        $this->exportDateFrom = today()->toDateString();
        $this->exportDateTo = today()->toDateString();
    }

    // Reset the export dialog back to "today / all" each time it opens, so
    // it never quietly carries a narrowed filter over to the next export.
    public function prepareExportModal()
    {
        $this->exportDateFrom = today()->toDateString();
        $this->exportDateTo = today()->toDateString();
        $this->exportType = '';
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
        return route('cashier.transactions.export', array_filter([
            'from'        => $this->exportDateFrom,
            'to'          => $this->exportDateTo,
            'type'        => $this->exportType,
            'paper'       => $this->exportPaper,
            'orientation' => $this->exportOrientation,
        ]));
    }

    // Same params as exportUrl, plus preview=1 so the controller streams the
    // PDF inline instead of forcing a download or logging it as an export.
    #[Computed]
    public function exportPreviewUrl(): string
    {
        return route('cashier.transactions.export', array_filter([
            'from'        => $this->exportDateFrom,
            'to'          => $this->exportDateTo,
            'type'        => $this->exportType,
            'paper'       => $this->exportPaper,
            'orientation' => $this->exportOrientation,
            'preview'     => 1,
        ]));
    }

    // ===================== FILTERS =====================

    public function updatedFilterFrom(): void { $this->resetPage(); }
    public function updatedFilterTo(): void { $this->resetPage(); }
    public function updatedType(): void { $this->resetPage(); }
    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedSortBy(): void { $this->resetPage(); }

    public function clearFilters(): void
    {
        $this->reset(['filterFrom', 'filterTo', 'type', 'search']);
        $this->sortBy = 'desc';
        $this->resetPage();
    }

    // Called from inside the filter modal's "Clear" button.
    public function clearDateFilters(): void
    {
        $this->reset(['filterFrom', 'filterTo']);
        $this->resetPage();
    }


    #[Computed]
    public function summary()
    {
        $rows = $this->allTransactions;

        return [
            'total'         => $rows->sum('amount'),
            'count'         => $rows->count(),
            'queue_fees'    => $rows->where('type', 'Queue fee')->sum('amount'),
            'fare_payments' => $rows->where('type', 'Fare payment')->sum('amount'),
            'topups'        => $rows->where('type', 'Card top-up')->sum('amount'),
        ];
    }

    // All of this cashier's transactions matching the current filters,
    // merged from four sources and sorted newest first. Pagination is done
    // in-memory below since there's no single query to paginate across
    // heterogeneous tables — fine at the scale of one cashier's own history.
    #[Computed]
    public function allTransactions()
    {
        $from = $this->filterFrom ? Carbon::parse($this->filterFrom)->startOfDay() : null;
        $to = $this->filterTo ? Carbon::parse($this->filterTo)->endOfDay() : null;
        $search = trim($this->search);
        $cashierId = Auth::id();

        $rows = collect();

        if (in_array($this->type, ['', 'queue_fees'])) {
            $rows = $rows->concat(
                CardTransaction::with('card.user')
                    ->where('processed_by', $cashierId)
                    ->where('transaction_type', 'queueing_fee')
                    ->where('status', 'success')
                    ->when($from, fn ($q) => $q->where('transaction_time', '>=', $from))
                    ->when($to, fn ($q) => $q->where('transaction_time', '<=', $to))
                    ->get()
                    ->map(fn ($t) => (object) [
                        'id' => 'card-queue-' . $t->id,
                        'type' => 'Queue fee',
                        'mode' => 'Card',
                        'party' => $t->card?->user?->name,
                        'reference' => $t->card?->user?->user_code,
                        'amount' => (float) $t->amount,
                        'occurred_at' => $t->transaction_time,
                    ])
            );

            // Cash queue fees and cash fare payments both live in
            // CashTransaction — the reference_no prefix ('CASH-' vs
            // 'FARECASH-') is the only thing that tells them apart, since
            // there's no dedicated type column on that table.
            $rows = $rows->concat(
                CashTransaction::with('operator')
                    ->where('processed_by', $cashierId)
                    ->where('status', 'success')
                    ->where('reference_no', 'like', 'CASH-%')
                    ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
                    ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
                    ->get()
                    ->map(fn ($t) => (object) [
                        'id' => 'cash-queue-' . $t->id,
                        'type' => 'Queue fee',
                        'mode' => 'Cash',
                        'party' => $t->operator?->name,
                        'reference' => $t->operator?->user_code,
                        'amount' => (float) $t->amount,
                        'occurred_at' => $t->created_at,
                    ])
            );
        }

        if (in_array($this->type, ['', 'fare_payments'])) {
            // Card fare payments: the commuter taps at the terminal and the
            // fare is deducted from their card, straight into the operator's
            // balance. Only the operator's "fare_earning" row is used here
            // (not the matching commuter-side deduction row) so each fare
            // payment is counted once. The commuter's identity comes from
            // the linked TravelRecord, since the fare_earning row itself
            // lives on the operator's card.
            $cardFarePayments = CardTransaction::where('processed_by', $cashierId)
                ->where('transaction_type', 'fare_earning')
                ->where('source', 'cashier') // excludes 'cashier_cash' (the cash counterpart, sourced from CashTransaction below)
                ->where('status', 'success')
                ->when($from, fn ($q) => $q->where('transaction_time', '>=', $from))
                ->when($to, fn ($q) => $q->where('transaction_time', '<=', $to))
                ->get();

            $travelRecords = \App\Models\TravelRecord::with('user')
                ->whereIn('id', $cardFarePayments->pluck('reference_id')->filter())
                ->get()
                ->keyBy('id');

            $rows = $rows->concat(
                $cardFarePayments->map(function ($t) use ($travelRecords) {
                    $rider = $travelRecords->get($t->reference_id)?->user;

                    return (object) [
                        'id' => 'card-fare-' . $t->id,
                        'type' => 'Fare payment',
                        'mode' => 'Card',
                        'party' => $rider?->name,
                        'reference' => $rider?->user_code,
                        'amount' => (float) $t->amount,
                        'occurred_at' => $t->transaction_time,
                    ];
                })
            );

            $rows = $rows->concat(
                CashTransaction::with('operator')
                    ->where('processed_by', $cashierId)
                    ->where('status', 'success')
                    ->where('reference_no', 'like', 'FARECASH-%')
                    ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
                    ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
                    ->get()
                    ->map(fn ($t) => (object) [
                        'id' => 'cash-fare-' . $t->id,
                        'type' => 'Fare payment',
                        'mode' => 'Cash',
                        // Fare payments are paid by the commuter, but the cash
                        // reconciliation row only stores the operator/vehicle
                        // whose trip it was for — shown here for the same
                        // reason the queue-fee rows show the operator.
                        'party' => $t->operator?->name,
                        'reference' => $t->operator?->user_code,
                        'amount' => (float) $t->amount,
                        'occurred_at' => $t->created_at,
                    ])
            );
        }

        if (in_array($this->type, ['', 'topups'])) {
            $rows = $rows->concat(
                TopUpTransaction::with('user')
                    ->where('processed_by', $cashierId)
                    ->where('status', 'paid')
                    ->where('payment_method', 'cash')
                    ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
                    ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
                    ->get()
                    ->map(fn ($t) => (object) [
                        'id' => 'topup-' . $t->id,
                        'type' => 'Card top-up',
                        'mode' => 'Cash',
                        'party' => $t->user?->name,
                        'reference' => $t->user?->user_code,
                        'amount' => (float) $t->amount_paid,
                        'occurred_at' => $t->created_at,
                    ])
            );
        }

        if ($search !== '') {
            $needle = strtolower($search);
            $rows = $rows->filter(function ($row) use ($needle) {
                return str_contains(strtolower((string) $row->party), $needle)
                    || str_contains(strtolower((string) $row->reference), $needle);
            });
        }

        return $this->sortBy === 'asc'
            ? $rows->sortBy('occurred_at')->values()
            : $rows->sortByDesc('occurred_at')->values();
    }

    #[Computed]
    public function transactions()
    {
        $all = $this->allTransactions;
        $lastPage = max(1, (int) ceil($all->count() / $this->perPage));
        $page = min($this->getPage(), $lastPage);

        $items = $all->forPage($page, $this->perPage)->values();

        return new LengthAwarePaginator(
            $items,
            $all->count(),
            $this->perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }

    // ===================== LIVE REFRESH =====================

    #[On('echo:card-transaction-created,.CardTransactionCreated')]
    #[On('echo:cash-transaction-created,.CashTransactionCreated')]
    public function refresh(): void
    {
        unset($this->allTransactions, $this->transactions, $this->summary);
    }
};
?>

<div>
    <x-page-header
        description="Transaction History"
        class="mb-4"
    >
        <div class="flex items-center gap-2">
            {{-- Filters button (opens modal) --}}
            <flux:modal.trigger name="date-filter">
                <button
                    type="button"
                    class="relative flex items-center gap-1.5 sm:gap-2 px-2.5 sm:px-3.5 h-8 sm:h-9 rounded-lg bg-primary text-white border-0 hover:bg-primary-hover dark:bg-secondary dark:text-[var(--color-dark-primary)] dark:hover:bg-secondary-hover transition font-secondary text-xs sm:text-table-row shrink-0"
                >
                    <flux:icon.funnel class="w-3 h-3 sm:w-3.5 sm:h-3.5 text-white dark:text-[var(--color-dark-primary)]" />
                    <span>Filters</span>
                    @php $dateFilterCount = ($filterFrom ? 1 : 0) + ($filterTo ? 1 : 0); @endphp
                    @if ($dateFilterCount > 0)
                        <span class="flex items-center justify-center w-4 h-4 rounded-full bg-primary dark:bg-dark-txt-primary text-white dark:text-primary text-[10px] font-bold">
                            {{ $dateFilterCount }}
                        </span>
                    @endif
                </button>
            </flux:modal.trigger>

            {{-- Export button --}}
            <flux:modal.trigger name="export-cashier-transactions" wire:click="prepareExportModal">
                <button
                    type="button"
                    class="flex items-center gap-1.5 sm:gap-2 px-3 h-9 rounded-lg bg-black text-white border-0 hover:bg-neutral-800 dark:bg-white dark:text-black dark:hover:bg-neutral-200 transition font-secondary text-xs sm:text-table-row shrink-0 justify-center"
                >
                    <flux:icon.arrow-down-tray class="w-3.5 h-3.5 text-white dark:text-black" />
                    <span>Export</span>
                </button>
            </flux:modal.trigger>
        </div>
    </x-page-header>

    {{-- Mobile-visible heading, since the page header above is desktop-only --}}
    <div class="sm:hidden flex items-start justify-between gap-4 mb-6">
        <x-heading
            size="xl"
            class="!font-primary !font-bold !text-light-txt-primary dark:!text-dark-txt-primary"
            style="font-size: var(--text-page-title)"
        >
            Transaction History
        </x-heading>

        <div class="flex items-center gap-2 shrink-0">
            {{-- Filters button (mobile) --}}
            <flux:modal.trigger name="date-filter">
                <button
                    type="button"
                    class="relative flex items-center gap-1.5 px-2.5 h-8 rounded-lg bg-primary text-white border-0 hover:bg-primary-hover dark:bg-secondary dark:text-[var(--color-dark-primary)] dark:hover:bg-secondary-hover transition font-secondary text-xs shrink-0"
                >
                    <flux:icon.funnel class="w-3 h-3" />
                    <span>Filters</span>
                    @php $dateFilterCountMobile = ($filterFrom ? 1 : 0) + ($filterTo ? 1 : 0); @endphp
                    @if ($dateFilterCountMobile > 0)
                        <span class="flex items-center justify-center w-4 h-4 rounded-full bg-primary dark:bg-dark-txt-primary text-white dark:text-primary text-[10px] font-bold">
                            {{ $dateFilterCountMobile }}
                        </span>
                    @endif
                </button>
            </flux:modal.trigger>

            {{-- Export button (mobile) --}}
            <flux:modal.trigger name="export-cashier-transactions" wire:click="prepareExportModal">
                <button
                    type="button"
                    class="flex items-center gap-1.5 px-3 h-9 rounded-lg bg-black text-white border-0 hover:bg-neutral-800 dark:bg-white dark:text-black dark:hover:bg-neutral-200 transition font-secondary text-xs shrink-0 justify-center"
                >
                    <flux:icon.arrow-down-tray class="w-3.5 h-3.5 text-white dark:text-black" />
                    <span>Export</span>
                </button>
            </flux:modal.trigger>
        </div>
    </div>

    {{-- ===================== FILTER MODAL ===================== --}}
    <flux:modal
        name="date-filter"
        :closable="false"
        class="w-[calc(100%-2rem)] sm:max-w-md mx-auto rounded-xl overflow-hidden"
    >
        <div class="flex flex-col p-4 sm:p-6 !pr-4 sm:!pr-6 space-y-5">
            <div class="flex items-start justify-between">
                <div>
                    <flux:heading size="xl" class="!font-primary !font-bold text-light-txt-primary dark:text-dark-txt-primary">
                        Filters
                    </flux:heading>
                    <flux:text class="mt-1 font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">
                        Narrow the transaction history to a specific range.
                    </flux:text>
                </div>
                <flux:modal.close>
                    <button type="button" class="p-1 rounded-full hover:bg-light-subtle dark:hover:bg-dark-subtle text-light-txt-muted dark:text-dark-txt-muted -mt-1">
                        <flux:icon name="x-mark" class="w-5 h-5" />
                    </button>
                </flux:modal.close>
            </div>

            <flux:field>
                <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">From</flux:label>
                <flux:input
                    type="date"
                    wire:model.live="filterFrom"
                    size="sm"
                    class="font-secondary text-table-row"
                />
            </flux:field>

            <flux:field>
                <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">To</flux:label>
                <flux:input
                    type="date"
                    wire:model.live="filterTo"
                    size="sm"
                    class="font-secondary text-table-row"
                />
            </flux:field>

            <div class="flex flex-col-reverse sm:flex-row justify-between items-stretch sm:items-center gap-2 pt-2 border-t border-light-bd-default dark:border-dark-bd-default">
                <flux:button
                    type="button"
                    wire:click="clearDateFilters"
                    variant="ghost"
                    class="w-full sm:w-auto justify-center font-secondary"
                    :disabled="!$filterFrom && !$filterTo"
                >
                    Clear
                </flux:button>
                <flux:modal.close class="w-full sm:w-auto">
                    <flux:button
                        type="button"
                        variant="primary"
                        class="w-full sm:w-auto justify-center font-secondary"
                    >
                        Done
                    </flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>

    {{-- ===================== EXPORT MODAL ===================== --}}
    <flux:modal
        name="export-cashier-transactions"
        :closable="false"
        class="w-[calc(100%-2rem)] sm:max-w-lg mx-auto rounded-xl overflow-hidden"
    >
        <div class="flex flex-col p-4 sm:p-6 !pr-4 sm:!pr-6 space-y-5 overflow-y-auto max-h-[70vh]">
            <div class="flex items-start justify-between">
                <div>
                    <flux:heading size="xl" class="!font-primary !font-bold text-light-txt-primary dark:text-dark-txt-primary">
                        Export transactions
                    </flux:heading>
                    <flux:text class="mt-1 font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">
                        Choose what to include in the PDF. Defaults to today, covering queue fees, fare payments, and top-ups.
                    </flux:text>
                </div>
                <flux:modal.close>
                    <button type="button" class="p-1 rounded-full hover:bg-light-subtle dark:hover:bg-dark-subtle text-light-txt-muted dark:text-dark-txt-muted -mt-1">
                        <flux:icon name="x-mark" class="w-5 h-5" />
                    </button>
                </flux:modal.close>
            </div>

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

                <div class="flex items-center gap-2 mt-3">
                    <flux:input
                        type="date"
                        wire:model.live="exportDateFrom"
                        size="sm"
                        class="font-secondary text-table-row bg-light-primary dark:bg-dark-surface text-light-txt-body dark:text-dark-txt-primary border-light-bd-default dark:border-dark-bd-default"
                    />
                    <span class="text-light-txt-muted dark:text-dark-txt-muted text-sm shrink-0">to</span>
                    <flux:input
                        type="date"
                        wire:model.live="exportDateTo"
                        size="sm"
                        class="font-secondary text-table-row bg-light-primary dark:bg-dark-surface text-light-txt-body dark:text-dark-txt-primary border-light-bd-default dark:border-dark-bd-default"
                    />
                </div>
                <flux:text class="mt-1.5 font-secondary text-xs text-light-txt-muted dark:text-dark-txt-muted">
                    Or pick a custom range above.
                </flux:text>
            </flux:field>

            <flux:field>
                <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">Transaction type</flux:label>
                <flux:select
                    wire:model.live="exportType"
                    size="sm"
                    placeholder="All (queue fees & top-ups)"
                    class="font-secondary text-table-row bg-light-primary dark:bg-dark-surface text-light-txt-body dark:text-dark-txt-primary border-light-bd-default dark:border-dark-bd-default"
                >
                    <flux:select.option value="">All (queue fees, fare payments &amp; top-ups)</flux:select.option>
                    <flux:select.option value="queue_fees">Queue fees only</flux:select.option>
                    <flux:select.option value="fare_payments">Fare payments only</flux:select.option>
                    <flux:select.option value="topups">Top-ups only</flux:select.option>
                </flux:select>
            </flux:field>

            <div class="flex gap-2">
                <flux:field class="flex-1">
                    <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">Paper size</flux:label>
                    <flux:select wire:model.live="exportPaper" size="sm" class="font-secondary text-table-row">
                        <flux:select.option value="letter">Letter</flux:select.option>
                        <flux:select.option value="legal">Legal</flux:select.option>
                        <flux:select.option value="a4">A4</flux:select.option>
                    </flux:select>
                </flux:field>
                <flux:field class="flex-1">
                    <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">Orientation</flux:label>
                    <flux:select wire:model.live="exportOrientation" size="sm" class="font-secondary text-table-row">
                        <flux:select.option value="portrait">Portrait</flux:select.option>
                        <flux:select.option value="landscape">Landscape</flux:select.option>
                    </flux:select>
                </flux:field>
            </div>

            <div class="flex flex-col-reverse sm:flex-row justify-end items-stretch sm:items-center gap-2 pt-2 border-t border-light-bd-default dark:border-dark-bd-default">
                <flux:modal.close class="w-full sm:w-auto">
                    <flux:button type="button" variant="ghost" class="w-full sm:w-auto justify-center font-secondary">
                        Cancel
                    </flux:button>
                </flux:modal.close>
                <flux:button
                    type="button"
                    x-on:click="Flux.modal('export-cashier-transactions').close(); Flux.modal('preview-cashier-transactions').show()"
                    icon="eye"
                    variant="primary"
                    class="font-secondary w-full sm:w-auto justify-center"
                >
                    Preview
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- ===================== PREVIEW MODAL ===================== --}}
    <flux:modal
        name="preview-cashier-transactions"
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
                    x-on:click="Flux.modal('preview-cashier-transactions').close()"
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
                    x-on:click="Flux.modal('preview-cashier-transactions').close(); Flux.modal('export-cashier-transactions').show()"
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

    {{-- ===================== SUMMARY ===================== --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 sm:gap-3 mb-6">
        <flux:card class="p-3 sm:p-4">
            <div class="flex items-center gap-1.5 sm:gap-2 mb-1.5">
                <div class="flex items-center justify-center w-6 h-6 sm:w-7 sm:h-7 rounded-lg bg-primary/10 dark:bg-primary/20 shrink-0">
                    <flux:icon.banknotes class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-primary dark:text-dark-txt-primary" />
                </div>
                <x-text class="font-secondary text-xs sm:text-stat-label text-light-txt-muted dark:text-dark-txt-muted">
                    Total collected
                </x-text>
            </div>
            <x-text class="font-primary text-stat-value font-bold text-light-txt-primary dark:text-dark-txt-primary block">
                ₱{{ number_format($this->summary['total'], 2) }}
            </x-text>
            <x-text class="font-secondary text-xs text-light-txt-muted dark:text-dark-txt-muted">
                {{ $this->summary['count'] }} transaction{{ $this->summary['count'] === 1 ? '' : 's' }} · matches current filters
            </x-text>
        </flux:card>

        <flux:card class="p-3 sm:p-4">
            <div class="flex items-center gap-1.5 sm:gap-2 mb-1.5">
                <div class="flex items-center justify-center w-6 h-6 sm:w-7 sm:h-7 rounded-lg bg-success/10 dark:bg-dark-success/20 shrink-0">
                    <flux:icon.ticket class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-success dark:text-dark-success" />
                </div>
                <x-text class="font-secondary text-xs sm:text-stat-label text-light-txt-muted dark:text-dark-txt-muted">
                    Queue fees
                </x-text>
            </div>
            <x-text class="font-primary text-stat-value font-bold text-success dark:text-dark-success block">
                ₱{{ number_format($this->summary['queue_fees'], 2) }}
            </x-text>
        </flux:card>

        <flux:card class="p-3 sm:p-4">
            <div class="flex items-center gap-1.5 sm:gap-2 mb-1.5">
                <div class="flex items-center justify-center w-6 h-6 sm:w-7 sm:h-7 rounded-lg bg-warning/10 dark:bg-dark-warning/20 shrink-0">
                    <flux:icon.wallet class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-warning dark:text-dark-warning" />
                </div>
                <x-text class="font-secondary text-xs sm:text-stat-label text-light-txt-muted dark:text-dark-txt-muted">
                    Fare payments
                </x-text>
            </div>
            <x-text class="font-primary text-stat-value font-bold text-warning dark:text-dark-warning block">
                ₱{{ number_format($this->summary['fare_payments'], 2) }}
            </x-text>
        </flux:card>

        <flux:card class="p-3 sm:p-4">
            <div class="flex items-center gap-1.5 sm:gap-2 mb-1.5">
                <div class="flex items-center justify-center w-6 h-6 sm:w-7 sm:h-7 rounded-lg bg-info/10 dark:bg-dark-info/20 shrink-0">
                    <flux:icon.credit-card class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-info dark:text-dark-info" />
                </div>
                <x-text class="font-secondary text-xs sm:text-stat-label text-light-txt-muted dark:text-dark-txt-muted">
                    Card top-ups
                </x-text>
            </div>
            <x-text class="font-primary text-stat-value font-bold text-info dark:text-dark-info block">
                ₱{{ number_format($this->summary['topups'], 2) }}
            </x-text>
        </flux:card>
    </div>

    {{-- Filters --}}
    <div class="flex flex-col sm:flex-row sm:items-center gap-2 mb-4">
        <flux:input
            class="w-full flex-1 font-secondary text-table-row dark:bg-dark-secondary dark:border-dark-bd-default dark:text-dark-txt-primary"
            icon="magnifying-glass"
            placeholder="Search name or ID…"
            wire:model.live.debounce.300ms="search"
        />

        <flux:select wire:model.live="type" placeholder="Type" class="w-full sm:w-44 font-secondary text-table-row dark:bg-dark-secondary dark:border-dark-bd-default dark:text-dark-txt-primary">
            <flux:select.option value="">All types</flux:select.option>
            <flux:select.option value="queue_fees">Queue fees</flux:select.option>
            <flux:select.option value="fare_payments">Fare payments</flux:select.option>
            <flux:select.option value="topups">Top-ups</flux:select.option>
        </flux:select>

        <flux:select wire:model.live="sortBy" class="w-full sm:w-40 font-secondary text-table-row dark:bg-dark-secondary dark:border-dark-bd-default dark:text-dark-txt-primary">
            <flux:select.option value="desc">Latest first</flux:select.option>
            <flux:select.option value="asc">Oldest first</flux:select.option>
        </flux:select>

        @if ($filterFrom || $filterTo || $type || $search || $sortBy !== 'desc')
            <button
                type="button"
                wire:click="clearFilters"
                class="font-secondary text-xs sm:text-table-row text-light-txt-muted dark:text-dark-txt-muted hover:text-light-txt-primary dark:hover:text-dark-txt-primary transition shrink-0"
            >
                Clear filters
            </button>
        @endif
    </div>

    {{-- Log table --}}
    <flux:card class="overflow-hidden p-0">
        <div class="overflow-x-auto">
            <flux:table container:class="max-h-160">
                <flux:table.columns sticky class="bg-light-subtle/50 dark:bg-dark-secondary/50">
                    <flux:table.column align="center" class="px-1! sm:px-2! md:px-4! py-2">Type</flux:table.column>
                    <flux:table.column align="center" class="px-1 sm:px-2 md:px-4 py-2">Mode</flux:table.column>
                    <flux:table.column align="center" class="hidden md:table-cell px-1 sm:px-2 md:px-4 py-2">Paid by</flux:table.column>
                    <flux:table.column align="center" class="px-1 sm:px-2 md:px-4 py-2">Amount</flux:table.column>
                    <flux:table.column align="center" class="hidden md:table-cell px-1 sm:px-2 md:px-4 py-2">Date</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($this->transactions as $index => $txn)
                        <flux:table.row :key="$txn->id">

                            <flux:table.cell align="center" class="px-1 sm:px-2 md:px-4 py-1.5 md:py-2">
                                @if ($txn->type === 'Card top-up')
                                    <flux:badge size="sm" color="blue" icon="credit-card">Top-up</flux:badge>
                                @elseif ($txn->type === 'Fare payment')
                                    <flux:badge size="sm" color="amber" icon="wallet">Fare</flux:badge>
                                @else
                                    <flux:badge size="sm" color="green" icon="ticket">Queue fee</flux:badge>
                                @endif
                            </flux:table.cell>

                            <flux:table.cell align="center" class="px-1 sm:px-2 md:px-4 py-1.5 md:py-2">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-badge font-medium bg-light-subtle dark:bg-dark-subtle text-light-txt-primary dark:text-dark-txt-primary">
                                    {{ $txn->mode }}
                                </span>
                            </flux:table.cell>

                            <flux:table.cell align="center" class="hidden md:table-cell px-1 sm:px-2 md:px-4 py-1.5 md:py-2">
                                <div class="flex flex-col items-center">
                                    <span class="font-secondary text-xs md:text-table-row text-light-txt-body dark:text-dark-txt-primary">
                                        {{ $txn->party ?? 'Unknown' }}
                                    </span>
                                    @if ($txn->reference)
                                        <span class="font-secondary text-xs text-light-txt-muted dark:text-dark-txt-muted">
                                            {{ $txn->reference }}
                                        </span>
                                    @endif
                                </div>
                            </flux:table.cell>

                            <flux:table.cell align="center" class="px-1 sm:px-2 md:px-4 py-1.5 md:py-2 font-secondary text-xs md:text-table-row tabular-nums font-medium text-light-txt-primary dark:text-dark-txt-primary">
                                ₱{{ number_format($txn->amount, 2) }}
                            </flux:table.cell>

                            <flux:table.cell align="center" class="hidden md:table-cell px-1 sm:px-2 md:px-4 py-1.5 md:py-2 font-secondary text-xs md:text-table-row text-light-txt-muted dark:text-dark-txt-muted tabular-nums">
                                {{ \Illuminate\Support\Carbon::parse($txn->occurred_at)->format('Y-m-d H:i') }}
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="5">
                                <div class="flex flex-col items-center justify-center py-12 gap-2">
                                    <flux:icon.banknotes class="w-8 h-8 text-light-txt-muted dark:text-dark-txt-muted" />
                                    <p class="font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">No transactions found.</p>
                                    @if ($search || $filterFrom || $filterTo || $type)
                                        <p class="font-secondary text-xs text-light-txt-muted dark:text-dark-txt-muted">Try adjusting your search or filters.</p>
                                    @endif
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>

        @if ($this->transactions->hasPages())
            <div class="flex flex-wrap items-center justify-end gap-2 px-3 sm:px-4 py-2 border-t border-light-bd-default dark:border-dark-bd-default bg-light-secondary dark:bg-dark-secondary">
                {{ $this->transactions->links() }}
            </div>
        @endif
    </flux:card>
</div>
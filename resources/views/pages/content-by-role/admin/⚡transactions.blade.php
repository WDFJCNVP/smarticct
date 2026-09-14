<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Livewire\WithPagination;

use Illuminate\Support\Facades\DB;
use App\Models\Card;
use App\Models\CardTransaction;
use App\Models\CashTransaction;
use App\Models\TopUpTransaction;
use App\Services\TransactionLedgerService;

new #[Layout('layouts.admin-layout')] class extends Component
{
    use WithPagination;

    public string $search = '';
    public string $sortBy = 'latest';   // latest, oldest

    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedSortBy(): void { $this->resetPage(); }

    public function clearFilters(): void
    {
        $this->reset(['search']);
        $this->sortBy = 'latest';
        $this->resetPage();
    }

    // ===================== EXPORT MODAL =====================

    public string $exportDateFrom   = '';
    public string $exportDateTo     = '';
    public string $exportCategory   = ''; // '', topup, queue_fee, fare_payment, card_issuance
    public string $exportMode       = ''; // '', cash, card, online
    public string $exportStatus     = ''; // '', completed, pending, failed
    public string $exportPaper      = 'legal';
    public string $exportOrientation = 'portrait';

    public function mount(): void
    {
        $this->exportDateFrom = today()->toDateString();
        $this->exportDateTo = today()->toDateString();
    }

    // Reset the export dialog back to "today / all categories, modes & statuses"
    // every time it opens, so a narrowed export never quietly carries over.
    public function prepareExportModal(): void
    {
        $this->exportDateFrom = today()->toDateString();
        $this->exportDateTo = today()->toDateString();
        $this->exportCategory = '';
        $this->exportMode = '';
        $this->exportStatus = '';
    }

    public function setExportRangeAllTime(): void
    {
        $this->exportDateFrom = '';
        $this->exportDateTo = '';
    }

    public function setExportRangeToday(): void
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
        return route('admin.transactions.export', array_filter([
            'from'        => $this->exportDateFrom,
            'to'          => $this->exportDateTo,
            'category'    => $this->exportCategory,
            'mode'        => $this->exportMode,
            'status'      => $this->exportStatus,
            'paper'       => $this->exportPaper,
            'orientation' => $this->exportOrientation,
        ]));
    }

    // Same params as exportUrl, plus preview=1 so the controller streams the
    // PDF inline instead of forcing a download or logging it as an export.
    #[Computed]
    public function exportPreviewUrl(): string
    {
        return route('admin.transactions.export', array_filter([
            'from'        => $this->exportDateFrom,
            'to'          => $this->exportDateTo,
            'category'    => $this->exportCategory,
            'mode'        => $this->exportMode,
            'status'      => $this->exportStatus,
            'paper'       => $this->exportPaper,
            'orientation' => $this->exportOrientation,
            'preview'     => 1,
        ]));
    }

    /**
     * Revenue summary. Computed with direct, per-source aggregate queries
     * (rather than off the unioned ledger below) — cheaper, and mirrors the
     * pattern already used on the Top-Ups page.
     *
     * A note on what counts as "revenue" here: top-ups, queueing fees, and
     * card issuance are money the terminal actually keeps. Fare payments —
     * whether paid in cash or by tapping a card — are always credited
     * onward to the operator's own card as withdrawable points, so they're
     * never the terminal's money and are deliberately excluded from every
     * revenue figure below. They're still tracked (fare_payment_total) so
     * the table/breakdown can show them for visibility, just not counted
     * as revenue.
     */
    #[Computed]
    public function stats(): array
    {
        $topupPaid = TopUpTransaction::where('status', 'paid');
        $topupTotal = (clone $topupPaid)->sum('amount_paid');
        $topupCashTotal = (clone $topupPaid)->where('payment_method', 'cash')->sum('amount_paid');
        $topupOnlineTotal = $topupTotal - $topupCashTotal;
        $topupTotalToday = (clone $topupPaid)->whereDate('created_at', today())->sum('amount_paid');

        // Queue fees — currently always paid by RFID card tap and kept
        // permanently by the terminal.
        $queueFeeQuery = CardTransaction::where('transaction_type', 'queueing_fee')->where('status', 'success');
        $queueFeeTotal = (clone $queueFeeQuery)->sum('amount');
        $queueFeeTotalToday = (clone $queueFeeQuery)->whereDate('transaction_time', today())->sum('amount');

        // Fare payments — tracked for the table/breakdown only. Excluded
        // from revenue: this money is credited onward to the operator's
        // card as withdrawable points, never kept by the terminal.
        $fareCashTotal = CashTransaction::where('reference_no', 'like', 'FARECASH-%')->where('status', 'success')->sum('amount');

        // Card-tap fare payments (kiosk). The rider's card is the one
        // actually deducted — that's the real payment event; the matching
        // "fare_earning" credit to the operator's card is just the internal
        // settlement leg, so it's excluded here to avoid double-counting.
        $fareCardTotal = CardTransaction::where('transaction_type', 'queue_deduction')->where('source', 'kiosk_tap_in')->where('status', 'success')->sum('amount');

        $farePaymentTotal = $fareCashTotal + $fareCardTotal;

        // Card issuance has no fee configured yet — tracked here (count) so
        // it's ready to carry real revenue the moment pricing is decided.
        $cardIssuanceCount = Card::count();
        $cardIssuanceTotal = 0.0;

        // Cash/card split reflects terminal-kept revenue only — fare
        // payments are deliberately left out of both.
        $cashTotal   = $topupCashTotal;
        $cardTotal   = $queueFeeTotal;
        $onlineTotal = $topupOnlineTotal;

        $totalRevenue      = $topupTotal + $queueFeeTotal + $cardIssuanceTotal;
        $totalRevenueToday = $topupTotalToday + $queueFeeTotalToday;

        return [
            'total_revenue'       => $totalRevenue,
            'total_revenue_today' => $totalRevenueToday,
            'cash_total'          => $cashTotal,
            'card_total'          => $cardTotal,
            'online_total'        => $onlineTotal,
            'topup_total'         => $topupTotal,
            'queue_fee_total'     => $queueFeeTotal,
            'fare_payment_total'  => $farePaymentTotal,
            'card_issuance_count' => $cardIssuanceCount,
            'card_issuance_total' => $cardIssuanceTotal,
        ];
    }

    /**
     * Builds the unified transaction ledger via the shared
     * TransactionLedgerService, so this table and the PDF export always
     * agree on exactly what counts and how it's categorized.
     */
    #[Computed]
    public function getTransactions()
    {
        $ledger = app(TransactionLedgerService::class);

        $query = $ledger->applyFilters($ledger->query(), [
            'search' => $this->search,
        ]);

        return $this->sortBy === 'oldest'
            ? $query->orderBy('occurred_at')->paginate(15)
            : $query->orderByDesc('occurred_at')->paginate(15);
    }
};
?>

<div>
    <x-page-header description="Transactions" class="mb-4">
        <flux:modal.trigger name="export-transactions" wire:click="prepareExportModal">
            <flux:button
                icon="arrow-down-tray"
                size="sm"
                class="font-secondary shrink-0 w-full sm:w-auto justify-center !bg-black !text-white !border-0 hover:!bg-neutral-800 dark:!bg-white dark:!text-black dark:hover:!bg-neutral-200"
            >
                Export Report
            </flux:button>
        </flux:modal.trigger>
    </x-page-header>

    {{-- Mobile-visible heading, since the page header above is desktop-only --}}
    <div class="sm:hidden flex items-center justify-between gap-4 mb-6">
        <x-heading
            size="xl"
            class="!font-primary !font-bold !text-light-txt-primary dark:!text-dark-txt-primary"
            style="font-size: var(--text-page-title)"
        >
            Transactions
        </x-heading>

        <flux:modal.trigger name="export-transactions" wire:click="prepareExportModal">
            <button
                type="button"
                class="flex items-center gap-1.5 px-3 h-9 rounded-lg bg-black text-white border-0 hover:bg-neutral-800 dark:bg-white dark:text-black dark:hover:bg-neutral-200 transition font-secondary text-xs shrink-0"
            >
                <flux:icon.arrow-down-tray class="w-3.5 h-3.5 text-white dark:text-black" />
                <span>Export</span>
            </button>
        </flux:modal.trigger>
    </div>

    {{-- ===================== EXPORT MODAL ===================== --}}
    <flux:modal
        name="export-transactions"
        :closable="false"
        class="w-[calc(100%-2rem)] sm:max-w-lg mx-auto rounded-xl overflow-hidden"
    >
        <div class="flex flex-col p-4 sm:p-6 !pr-4 sm:!pr-6 space-y-5 overflow-y-auto max-h-[70vh]">
            <div class="flex items-start justify-between">
                <div>
                    <flux:heading size="xl" class="!font-primary !font-bold text-light-txt-primary dark:text-dark-txt-primary">
                        Export transactions report
                    </flux:heading>
                    <flux:text class="mt-1 font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">
                        Choose what to include in the PDF. Starts from whatever's currently filtered on screen.
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

            <div class="flex gap-2">
                <flux:field class="flex-1">
                    <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">Category</flux:label>
                    <flux:select
                        wire:model.live="exportCategory"
                        size="sm"
                        placeholder="All categories"
                        class="font-secondary text-table-row bg-light-primary dark:bg-dark-surface text-light-txt-body dark:text-dark-txt-primary border-light-bd-default dark:border-dark-bd-default"
                    >
                        <flux:select.option value="">All categories</flux:select.option>
                        <flux:select.option value="topup">Top-ups</flux:select.option>
                        <flux:select.option value="queue_fee">Queue fees</flux:select.option>
                        <flux:select.option value="fare_payment">Fare payments</flux:select.option>
                        <flux:select.option value="card_issuance">Card issuance</flux:select.option>
                    </flux:select>
                </flux:field>
                <flux:field class="flex-1">
                    <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">Payment type</flux:label>
                    <flux:select
                        wire:model.live="exportMode"
                        size="sm"
                        placeholder="All modes"
                        class="font-secondary text-table-row bg-light-primary dark:bg-dark-surface text-light-txt-body dark:text-dark-txt-primary border-light-bd-default dark:border-dark-bd-default"
                    >
                        <flux:select.option value="">All modes</flux:select.option>
                        <flux:select.option value="cash">Cash</flux:select.option>
                        <flux:select.option value="card">Card</flux:select.option>
                        <flux:select.option value="online">Online</flux:select.option>
                    </flux:select>
                </flux:field>
            </div>

            <flux:field>
                <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">Status</flux:label>
                <flux:select
                    wire:model.live="exportStatus"
                    size="sm"
                    placeholder="All statuses"
                    class="font-secondary text-table-row bg-light-primary dark:bg-dark-surface text-light-txt-body dark:text-dark-txt-primary border-light-bd-default dark:border-dark-bd-default"
                >
                    <flux:select.option value="">All statuses</flux:select.option>
                    <flux:select.option value="completed">Completed</flux:select.option>
                    <flux:select.option value="pending">Pending</flux:select.option>
                    <flux:select.option value="failed">Failed</flux:select.option>
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
                    x-on:click="Flux.modal('export-transactions').close(); Flux.modal('preview-transactions').show()"
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
        name="preview-transactions"
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
                    x-on:click="Flux.modal('preview-transactions').close()"
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
                    x-on:click="Flux.modal('preview-transactions').close(); Flux.modal('export-transactions').show()"
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


    {{-- Revenue summary --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2 sm:gap-3 mb-3">
        <flux:card class="p-3 sm:p-4">
            <div class="flex items-center gap-1.5 sm:gap-2 mb-1.5">
                <div class="flex items-center justify-center w-6 h-6 sm:w-7 sm:h-7 rounded-lg bg-primary/10 dark:bg-primary/20 shrink-0">
                    <flux:icon.currency-dollar class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-primary dark:text-dark-txt-primary" />
                </div>
                <x-text class="font-secondary text-xs sm:text-stat-label text-light-txt-muted dark:text-dark-txt-muted">
                    Total revenue
                </x-text>
            </div>
            <x-text class="font-primary text-stat-value font-bold text-light-txt-primary dark:text-dark-txt-primary block">
                ₱{{ number_format($this->stats['total_revenue'], 2) }}
            </x-text>
            <x-text class="font-secondary text-xs text-light-txt-muted dark:text-dark-txt-muted">
                Top-ups, queue fees &amp; card issuance
            </x-text>
        </flux:card>

        <flux:card class="p-3 sm:p-4">
            <div class="flex items-center gap-1.5 sm:gap-2 mb-1.5">
                <div class="flex items-center justify-center w-6 h-6 sm:w-7 sm:h-7 rounded-lg bg-success/10 dark:bg-dark-success/20 shrink-0">
                    <flux:icon.banknotes class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-success dark:text-dark-success" />
                </div>
                <x-text class="font-secondary text-xs sm:text-stat-label text-light-txt-muted dark:text-dark-txt-muted">
                    Today's revenue
                </x-text>
            </div>
            <x-text class="font-primary text-stat-value font-bold text-success dark:text-dark-success block">
                ₱{{ number_format($this->stats['total_revenue_today'], 2) }}
            </x-text>
        </flux:card>

        <flux:card class="p-3 sm:p-4">
            <div class="flex items-center gap-1.5 sm:gap-2 mb-1.5">
                <div class="flex items-center justify-center w-6 h-6 sm:w-7 sm:h-7 rounded-lg bg-emerald-500/10 dark:bg-emerald-400/20 shrink-0">
                    <flux:icon.wallet class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-emerald-600 dark:text-emerald-400" />
                </div>
                <x-text class="font-secondary text-xs sm:text-stat-label text-light-txt-muted dark:text-dark-txt-muted">
                    Cash collected
                </x-text>
            </div>
            <x-text class="font-primary text-stat-value font-bold text-emerald-600 dark:text-emerald-400 block">
                ₱{{ number_format($this->stats['cash_total'], 2) }}
            </x-text>
            <x-text class="font-secondary text-xs text-light-txt-muted dark:text-dark-txt-muted">
                Already in hand
            </x-text>
        </flux:card>

        <flux:card class="p-3 sm:p-4">
            <div class="flex items-center gap-1.5 sm:gap-2 mb-1.5">
                <div class="flex items-center justify-center w-6 h-6 sm:w-7 sm:h-7 rounded-lg bg-info/10 dark:bg-dark-info/20 shrink-0">
                    <flux:icon.credit-card class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-info dark:text-dark-info" />
                </div>
                <x-text class="font-secondary text-xs sm:text-stat-label text-light-txt-muted dark:text-dark-txt-muted">
                    Card-based fees
                </x-text>
            </div>
            <x-text class="font-primary text-stat-value font-bold text-info dark:text-dark-info block">
                ₱{{ number_format($this->stats['card_total'], 2) }}
            </x-text>
            <x-text class="font-secondary text-xs text-light-txt-muted dark:text-dark-txt-muted">
                Queue fees paid by card tap
            </x-text>
        </flux:card>
    </div>

    {{-- Category breakdown --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-2 sm:gap-3 mb-6">
        <flux:card class="p-3 sm:p-4">
            <x-text class="font-secondary text-xs sm:text-stat-label text-light-txt-muted dark:text-dark-txt-muted block mb-1">Top-ups</x-text>
            <x-text class="font-primary text-lg font-bold text-light-txt-primary dark:text-dark-txt-primary block">
                ₱{{ number_format($this->stats['topup_total'], 2) }}
            </x-text>
        </flux:card>
        <flux:card class="p-3 sm:p-4">
            <x-text class="font-secondary text-xs sm:text-stat-label text-light-txt-muted dark:text-dark-txt-muted block mb-1">Queue fees</x-text>
            <x-text class="font-primary text-lg font-bold text-light-txt-primary dark:text-dark-txt-primary block">
                ₱{{ number_format($this->stats['queue_fee_total'], 2) }}
            </x-text>
        </flux:card>
        <flux:card class="p-3 sm:p-4">
            <x-text class="font-secondary text-xs sm:text-stat-label text-light-txt-muted dark:text-dark-txt-muted block mb-1">Fare payments</x-text>
            <x-text class="font-primary text-lg font-bold text-light-txt-primary dark:text-dark-txt-primary block">
                ₱{{ number_format($this->stats['fare_payment_total'], 2) }}
            </x-text>
            <x-text class="font-secondary text-xs text-light-txt-muted dark:text-dark-txt-muted">
                Goes to operators, not revenue
            </x-text>
        </flux:card>
        <flux:card class="p-3 sm:p-4">
            <x-text class="font-secondary text-xs sm:text-stat-label text-light-txt-muted dark:text-dark-txt-muted block mb-1">Card issuance</x-text>
            <x-text class="font-primary text-lg font-bold text-light-txt-primary dark:text-dark-txt-primary block">
                {{ $this->stats['card_issuance_count'] }} issued
            </x-text>
            <x-text class="font-secondary text-xs text-light-txt-muted dark:text-dark-txt-muted">
                No fee configured yet
            </x-text>
        </flux:card>
    </div>

    {{-- ====== SEARCH & SORT (inline) ====== --}}
    <div class="flex flex-col sm:flex-row sm:items-center gap-3 mb-4">
        <div class="flex-1">
            <flux:input
                type="text"
                wire:model.live.debounce.300ms="search"
                placeholder="Search name, reference, or processed by…"
                class="w-full font-secondary text-table-row dark:bg-dark-secondary dark:border-dark-bd-default dark:text-dark-txt-primary"
                icon="magnifying-glass"
            />
        </div>

        <div class="flex flex-wrap sm:flex-nowrap items-stretch sm:items-center gap-2 w-full sm:w-auto">
            <flux:select
                wire:model.live="sortBy"
                size="sm"
                class="w-full sm:w-48 font-secondary text-table-row dark:bg-dark-secondary dark:border-dark-bd-default dark:text-dark-txt-primary"
            >
                <flux:select.option value="latest">Latest first</flux:select.option>
                <flux:select.option value="oldest">Oldest first</flux:select.option>
            </flux:select>

            @if ($search || $sortBy !== 'latest')
                <flux:button type="button" variant="ghost" size="sm" wire:click="clearFilters" class="font-secondary shrink-0">
                    Clear
                </flux:button>
            @endif
        </div>
    </div>

    {{-- Ledger table --}}
    <flux:card class="overflow-hidden p-0">
        <div class="overflow-x-auto">
            <flux:table container:class="max-h-160">
                <flux:table.columns sticky class="bg-light-subtle/50 dark:bg-dark-secondary/50">
                    <flux:table.column align="center" class="px-2 sm:px-4 py-2 text-center">Category</flux:table.column>
                    <flux:table.column align="center" class="px-2 sm:px-4 py-2 text-center">Name</flux:table.column>
                    <flux:table.column align="center" class="px-2 sm:px-4 py-2 text-center">Amount</flux:table.column>
                    <flux:table.column align="center" class="hidden sm:table-cell px-2 sm:px-4 py-2 text-center">Mode</flux:table.column>
                    <flux:table.column align="center" class="px-2 sm:px-4 py-2 text-center">Status</flux:table.column>
                    <flux:table.column align="center" class="hidden md:table-cell px-2 sm:px-4 py-2 text-center">Date</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($this->getTransactions as $row)
                        <flux:table.row :key="$row->row_id">
                            <flux:table.cell align="center" class="px-2 sm:px-4 py-2">
                                @php
                                    $categoryLabel = match ($row->category) {
                                        'topup' => 'Top-up',
                                        'queue_fee' => 'Queue fee',
                                        'fare_payment' => 'Fare payment',
                                        'card_issuance' => 'Card issuance',
                                        default => ucfirst($row->category),
                                    };
                                    $categoryColor = match ($row->category) {
                                        'topup' => 'violet',
                                        'queue_fee' => 'blue',
                                        'fare_payment' => 'green',
                                        'card_issuance' => 'zinc',
                                        default => 'zinc',
                                    };
                                @endphp
                                <flux:badge size="sm" color="{{ $categoryColor }}">{{ $categoryLabel }}</flux:badge>
                            </flux:table.cell>

                            <flux:table.cell align="center" class="px-2 sm:px-4 py-2 font-secondary text-xs md:text-table-row text-light-txt-body dark:text-dark-txt-primary truncate max-w-40">
                                {{ $row->user_name ?? 'Unknown' }}
                            </flux:table.cell>

                            <flux:table.cell align="center" class="px-2 sm:px-4 py-2 font-secondary text-xs md:text-table-row tabular-nums font-medium text-light-txt-primary dark:text-dark-txt-primary">
                                ₱{{ number_format((float) $row->amount, 2) }}
                            </flux:table.cell>

                            <flux:table.cell align="center" class="hidden sm:table-cell px-2 sm:px-4 py-2">
                                @if ($row->mode === 'cash')
                                    <flux:badge size="sm" color="emerald" icon="banknotes">Cash</flux:badge>
                                @elseif ($row->mode === 'card')
                                    <flux:badge size="sm" color="blue" icon="credit-card">Card</flux:badge>
                                @elseif ($row->mode === 'online')
                                    <flux:badge size="sm" color="violet" icon="device-phone-mobile">Online</flux:badge>
                                @else
                                    <flux:badge size="sm" color="zinc">—</flux:badge>
                                @endif
                            </flux:table.cell>

                            <flux:table.cell align="center" class="px-2 sm:px-4 py-2">
                                @if ($row->status === 'completed')
                                    <flux:badge color="green" size="sm" icon="check-circle">Completed</flux:badge>
                                @elseif ($row->status === 'pending')
                                    <flux:badge color="yellow" size="sm" icon="clock">Pending</flux:badge>
                                @else
                                    <flux:badge color="red" size="sm" icon="x-circle">Failed</flux:badge>
                                @endif
                            </flux:table.cell>

                            <flux:table.cell align="center" class="hidden md:table-cell px-2 sm:px-4 py-2 font-secondary text-xs text-light-txt-muted dark:text-dark-txt-muted tabular-nums">
                                {{ \Illuminate\Support\Carbon::parse($row->occurred_at)->format('Y-m-d H:i') }}
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="6">
                                <div class="flex flex-col items-center justify-center py-12 gap-2">
                                    <flux:icon.currency-dollar class="w-8 h-8 text-light-txt-muted dark:text-dark-txt-muted" />
                                    <p class="font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">No transactions found.</p>
                                    @if ($search || $sortBy !== 'latest')
                                        <p class="font-secondary text-xs text-light-txt-muted dark:text-dark-txt-muted">Try adjusting your search.</p>
                                    @endif
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>

        @if ($this->getTransactions->hasPages())
            <div class="flex flex-wrap items-center justify-end gap-2 px-3 sm:px-4 py-2 border-t border-light-bd-default dark:border-dark-bd-default bg-light-secondary dark:bg-dark-secondary">
                {{ $this->getTransactions->links() }}
            </div>
        @endif
    </flux:card>
</div>
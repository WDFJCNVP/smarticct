<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Livewire\WithPagination;

use App\Models\TopUpTransaction;

new #[Layout('layouts.admin-layout')] class extends Component
{
    use WithPagination;

    public string $search = '';
    public string $filterStatus = '';   // '', paid, pending, failed, needs_attention
    public string $filterSource = '';   // '', cashier, online

    // ===================== EXPORT MODAL =====================

    public string $exportDateFrom = '';
    public string $exportDateTo = '';
    public string $exportType = ''; // '', queue_fees, topups
    public string $exportPaper = 'legal';
    public string $exportOrientation = 'portrait';

    public function mount()
    {
        $this->exportDateFrom = today()->toDateString();
        $this->exportDateTo = today()->toDateString();
    }

    // Reset the export dialog back to "today / all cashiers / all types"
    // each time it opens, so a narrowed export never quietly carries over.
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

    // ─── Key stats ──────────────────────────────────────────────────────────
    #[Computed]
    public function topUpStats()
    {
        $paid = TopUpTransaction::where('status', 'paid');

        $paidToday = (clone $paid)->whereDate('created_at', today());

        return [
            'today_amount'    => (clone $paidToday)->sum('amount_paid'),
            'today_count'     => (clone $paidToday)->count(),
            'week_amount'     => (clone $paid)->where('created_at', '>=', now()->subDays(7))->sum('amount_paid'),
            'cashier_today'   => (clone $paidToday)->whereNotNull('processed_by')->count(),
            'online_today'    => (clone $paidToday)->whereNull('processed_by')->count(),
            'needs_attention' => TopUpTransaction::whereIn('status', ['pending', 'failed'])->count(),
        ];
    }

    // ─── Log table ──────────────────────────────────────────────────────────
    #[Computed]
    public function getTopUps()
    {
        return TopUpTransaction::with(['user', 'card', 'processedBy'])
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->whereHas('user', fn ($u) =>
                        $u->where('name', 'like', '%' . $this->search . '%')
                          ->orWhere('user_code', 'like', '%' . $this->search . '%')
                    )->orWhereHas('card', fn ($c) =>
                        $c->where('card_number', 'like', '%' . $this->search . '%')
                    );
                });
            })
            ->when($this->filterStatus, function ($query) {
                if ($this->filterStatus === 'needs_attention') {
                    $query->whereIn('status', ['pending', 'failed']);
                } else {
                    $query->where('status', $this->filterStatus);
                }
            })
            ->when($this->filterSource, function ($query) {
                $this->filterSource === 'cashier'
                    ? $query->whereNotNull('processed_by')
                    : $query->whereNull('processed_by');
            })
            ->latest()
            ->paginate(10);
    }

    public function showAlertsOnly(): void
    {
        $this->filterStatus = 'needs_attention';
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    public function updatedFilterSource(): void
    {
        $this->resetPage();
    }
};
?>

<div>
    {{-- ====== PAGE HEADER (mini-navbar: heading left, notifications right) ====== --}}
    <x-page-header
        class="mb-4"
    >
        <flux:modal.trigger name="export-cashier-transactions" wire:click="prepareExportModal">
            <button
                type="button"
                class="flex items-center gap-1.5 sm:gap-2 px-3 h-9 rounded-lg bg-black text-white border-0 hover:bg-neutral-800 dark:bg-white dark:text-black dark:hover:bg-neutral-200 transition font-secondary text-xs sm:text-table-row shrink-0 w-full sm:w-auto justify-center"
            >
                <flux:icon.arrow-down-tray class="w-3.5 h-3.5 text-white dark:text-black" />
                <span>Export Cash Report</span>
            </button>
        </flux:modal.trigger>
    </x-page-header>

    {{-- ====== PAGE ACTIONS (desktop only — mobile keeps its own copy below, inline with the heading) ====== --}}
    <div class="hidden sm:flex sm:items-center sm:justify-between gap-3 mb-6">
        <x-heading
            size="xl"
            class="!font-primary !font-bold !text-light-txt-primary dark:!text-dark-txt-primary"
            style="font-size: var(--text-page-title)"
        >
            Card Top-Ups
        </x-heading>

        <flux:link href="{{ route('admin.topups.new') }}" wire:navigate class="w-full sm:w-auto">
            <flux:button
                variant="primary"
                icon="credit-card"
                class="font-secondary w-full sm:w-auto justify-center"
            >
                New Cash Top-Up
            </flux:button>
        </flux:link>
    </div>

    {{-- Mobile-visible heading, since the page header above is desktop-only --}}
    <div class="sm:hidden flex items-start justify-between gap-4 mb-6">
        <div>
            <x-heading
                size="xl"
                class="!font-primary !font-bold !text-light-txt-primary dark:!text-dark-txt-primary"
                style="font-size: var(--text-page-title)"
            >
                Card Top-Ups
            </x-heading>
        </div>

        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 w-full sm:w-auto shrink-0">
            <flux:link href="{{ route('admin.topups.new') }}" wire:navigate class="w-full sm:w-auto">
                <flux:button
                    variant="primary"
                    icon="credit-card"
                    class="font-secondary w-full sm:w-auto justify-center"
                >
                    New Cash Top-Up
                </flux:button>
            </flux:link>

            <flux:modal.trigger name="export-cashier-transactions" wire:click="prepareExportModal">
                <button
                    type="button"
                    class="flex items-center gap-1.5 sm:gap-2 px-3 h-9 rounded-lg bg-black text-white border-0 hover:bg-neutral-800 dark:bg-white dark:text-black dark:hover:bg-neutral-200 transition font-secondary text-xs sm:text-table-row shrink-0 w-full sm:w-auto justify-center"
                >
                    <flux:icon.arrow-down-tray class="w-3.5 h-3.5 text-white dark:text-black" />
                    <span>Export Cash Report</span>
                </button>
            </flux:modal.trigger>
        </div>
    </div>

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
                        Export cashier transactions
                    </flux:heading>
                    <flux:text class="mt-1 font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">
                        Choose what to include in the PDF. Defaults to today, covering all cashiers and both queue fees and top-ups.
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
                    <flux:select.option value="">All (queue fees &amp; top-ups)</flux:select.option>
                    <flux:select.option value="queue_fees">Queue fees only</flux:select.option>
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
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-2 sm:gap-3 mb-6">
        <flux:card class="p-3 sm:p-4">
            <div class="flex items-center gap-1.5 sm:gap-2 mb-1.5">
                <div class="flex items-center justify-center w-6 h-6 sm:w-7 sm:h-7 rounded-lg bg-primary/10 dark:bg-primary/20 shrink-0">
                    <flux:icon.banknotes class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-primary dark:text-dark-txt-primary" />
                </div>
                <x-text class="font-secondary text-xs sm:text-stat-label text-light-txt-muted dark:text-dark-txt-muted">
                    Today's revenue
                </x-text>
            </div>
            <x-text class="font-primary text-stat-value font-bold text-light-txt-primary dark:text-dark-txt-primary block">
                ₱{{ number_format($this->topUpStats['today_amount'], 2) }}
            </x-text>
            <x-text class="font-secondary text-xs text-light-txt-muted dark:text-dark-txt-muted">
                {{ $this->topUpStats['today_count'] }} transaction{{ $this->topUpStats['today_count'] === 1 ? '' : 's' }}
            </x-text>
        </flux:card>

        <flux:card class="p-3 sm:p-4">
            <div class="flex items-center gap-1.5 sm:gap-2 mb-1.5">
                <div class="flex items-center justify-center w-6 h-6 sm:w-7 sm:h-7 rounded-lg bg-success/10 dark:bg-dark-success/20 shrink-0">
                    <flux:icon.chart-bar class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-success dark:text-dark-success" />
                </div>
                <x-text class="font-secondary text-xs sm:text-stat-label text-light-txt-muted dark:text-dark-txt-muted">
                    Last 7 days
                </x-text>
            </div>
            <x-text class="font-primary text-stat-value font-bold text-success dark:text-dark-success block">
                ₱{{ number_format($this->topUpStats['week_amount'], 2) }}
            </x-text>
        </flux:card>

        <flux:card class="p-3 sm:p-4">
            <div class="flex items-center gap-1.5 sm:gap-2 mb-1.5">
                <div class="flex items-center justify-center w-6 h-6 sm:w-7 sm:h-7 rounded-lg bg-info/10 dark:bg-dark-info/20 shrink-0">
                    <flux:icon.user class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-info dark:text-dark-info" />
                </div>
                <x-text class="font-secondary text-xs sm:text-stat-label text-light-txt-muted dark:text-dark-txt-muted">
                    Cashier top-ups (today)
                </x-text>
            </div>
            <x-text class="font-primary text-stat-value font-bold text-info dark:text-dark-info block">
                {{ $this->topUpStats['cashier_today'] }}
            </x-text>
        </flux:card>

        <flux:card class="p-3 sm:p-4">
            <div class="flex items-center gap-1.5 sm:gap-2 mb-1.5">
                <div class="flex items-center justify-center w-6 h-6 sm:w-7 sm:h-7 rounded-lg bg-violet-500/10 dark:bg-violet-400/20 shrink-0">
                    <flux:icon.device-phone-mobile class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-violet-600 dark:text-violet-400" />
                </div>
                <x-text class="font-secondary text-xs sm:text-stat-label text-light-txt-muted dark:text-dark-txt-muted">
                    Online top-ups (today)
                </x-text>
            </div>
            <x-text class="font-primary text-stat-value font-bold text-violet-600 dark:text-violet-400 block">
                {{ $this->topUpStats['online_today'] }}
            </x-text>
        </flux:card>

        <flux:card class="p-3 sm:p-4 cursor-pointer hover:ring-2 hover:ring-danger/30 transition" wire:click="showAlertsOnly">
            <div class="flex items-center gap-1.5 sm:gap-2 mb-1.5">
                <div class="flex items-center justify-center w-6 h-6 sm:w-7 sm:h-7 rounded-lg bg-danger/10 dark:bg-dark-danger/20 shrink-0">
                    <flux:icon.exclamation-triangle class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-danger dark:text-dark-danger" />
                </div>
                <x-text class="font-secondary text-xs sm:text-stat-label text-light-txt-muted dark:text-dark-txt-muted">
                    Needs attention
                </x-text>
            </div>
            <x-text class="font-primary text-stat-value font-bold text-danger dark:text-dark-danger block">
                {{ $this->topUpStats['needs_attention'] }}
            </x-text>
            <x-text class="font-secondary text-xs text-light-txt-muted dark:text-dark-txt-muted">
                Pending / failed — tap to view
            </x-text>
        </flux:card>
    </div>

    {{-- Filters --}}
    <div class="flex flex-col sm:flex-row sm:items-center gap-2 mb-4">
        <flux:input
            class="flex-1 font-secondary text-table-row"
            size="sm"
            icon="magnifying-glass"
            placeholder="Search name, ID, or card no…"
            wire:model.live.debounce.300ms="search"
        />

        <flux:select wire:model.live="filterStatus" size="sm" placeholder="Status" class="w-full sm:w-44 font-secondary text-table-row">
            <flux:select.option value="">All statuses</flux:select.option>
            <flux:select.option value="paid">Paid</flux:select.option>
            <flux:select.option value="pending">Pending</flux:select.option>
            <flux:select.option value="failed">Failed</flux:select.option>
            <flux:select.option value="needs_attention">Needs attention</flux:select.option>
        </flux:select>

        <flux:select wire:model.live="filterSource" size="sm" placeholder="Source" class="w-full sm:w-40 font-secondary text-table-row">
            <flux:select.option value="">All sources</flux:select.option>
            <flux:select.option value="cashier">Cashier</flux:select.option>
            <flux:select.option value="online">Online</flux:select.option>
        </flux:select>
    </div>

    {{-- Log table --}}
    <flux:card class="overflow-hidden p-0">
        <div class="overflow-x-auto">
            <flux:table container:class="max-h-160">
                <flux:table.columns sticky class="bg-light-subtle/50 dark:bg-dark-secondary/50">
                    <flux:table.column align="center" class="px-1! sm:px-2! md:px-4! py-2">#</flux:table.column>
                    <flux:table.column align="center" class="px-1 sm:px-2 md:px-4 py-2">Cardholder</flux:table.column>
                    <flux:table.column align="center" class="px-1 sm:px-2 md:px-4 py-2">Card no.</flux:table.column>
                    <flux:table.column align="center" class="px-1 sm:px-2 md:px-4 py-2">Points loaded</flux:table.column>
                    <flux:table.column align="center" class="px-1 sm:px-2 md:px-4 py-2">Amount paid</flux:table.column>
                    <flux:table.column align="center" class="hidden md:table-cell px-1 sm:px-2 md:px-4 py-2">Source</flux:table.column>
                    <flux:table.column align="center" class="hidden md:table-cell px-1 sm:px-2 md:px-4 py-2">Payment method</flux:table.column>
                    <flux:table.column align="center" class="px-1 sm:px-2 md:px-4 py-2">Status</flux:table.column>
                    <flux:table.column align="center" class="hidden md:table-cell px-1 sm:px-2 md:px-4 py-2">Date</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($this->getTopUps as $index => $topUp)
                        <flux:table.row :key="$topUp->id">
                            <flux:table.cell align="center" class="px-1! sm:px-2! md:px-4! py-1.5 md:py-2 font-secondary text-xs md:text-table-row text-light-txt-muted dark:text-dark-txt-muted">
                                {{ ($this->getTopUps->currentPage() - 1) * $this->getTopUps->perPage() + $index + 1 }}
                            </flux:table.cell>

                            <flux:table.cell align="center" class="px-1 sm:px-2 md:px-4 py-1.5 md:py-2">
                                <div class="flex flex-col items-center">
                                    <span class="font-secondary text-xs md:text-table-row text-light-txt-body dark:text-dark-txt-primary">
                                        {{ $topUp->user?->name ?? 'Unknown' }}
                                    </span>
                                    <span class="font-secondary text-xs text-light-txt-muted dark:text-dark-txt-muted">
                                        {{ $topUp->user?->user_code ?? '-' }}
                                    </span>
                                </div>
                            </flux:table.cell>

                            <flux:table.cell align="center" class="px-1 sm:px-2 md:px-4 py-1.5 md:py-2">
                                <span class="font-mono text-xs md:text-table-row tracking-widest text-light-txt-muted dark:text-dark-txt-muted">
                                    **** {{ $topUp->card ? substr($topUp->card->card_number, -4) : '----' }}
                                </span>
                            </flux:table.cell>

                            <flux:table.cell align="center" class="px-1 sm:px-2 md:px-4 py-1.5 md:py-2 font-secondary text-xs md:text-table-row tabular-nums text-light-txt-muted dark:text-dark-txt-muted">
                                {{ number_format($topUp->points_to_load) }}
                            </flux:table.cell>

                            <flux:table.cell align="center" class="px-1 sm:px-2 md:px-4 py-1.5 md:py-2 font-secondary text-xs md:text-table-row tabular-nums font-medium text-light-txt-primary dark:text-dark-txt-primary">
                                ₱{{ number_format($topUp->amount_paid, 2) }}
                            </flux:table.cell>

                            <flux:table.cell align="center" class="hidden md:table-cell px-1 sm:px-2 md:px-4 py-1.5 md:py-2">
                                @if ($topUp->processed_by)
                                    <flux:badge size="sm" color="blue" icon="user">Cashier</flux:badge>
                                @else
                                    <flux:badge size="sm" color="violet" icon="device-phone-mobile">Online</flux:badge>
                                @endif
                            </flux:table.cell>

                            <flux:table.cell align="center" class="hidden md:table-cell px-1 sm:px-2 md:px-4 py-1.5 md:py-2 font-secondary text-xs md:text-table-row text-light-txt-muted dark:text-dark-txt-muted">
                                {{ $topUp->payment_method ? ucfirst($topUp->payment_method) : '—' }}
                            </flux:table.cell>

                            <flux:table.cell align="center" class="px-1 sm:px-2 md:px-4 py-1.5 md:py-2">
                                @if ($topUp->status === 'paid')
                                    <flux:badge color="green" size="sm" icon="check-circle">Paid</flux:badge>
                                @elseif ($topUp->status === 'failed')
                                    <flux:badge color="red" size="sm" icon="x-circle">Failed</flux:badge>
                                @else
                                    <flux:badge color="yellow" size="sm" icon="clock">Pending</flux:badge>
                                @endif
                            </flux:table.cell>

                            <flux:table.cell align="center" class="hidden md:table-cell px-1 sm:px-2 md:px-4 py-1.5 md:py-2 font-secondary text-xs md:text-table-row text-light-txt-muted dark:text-dark-txt-muted tabular-nums">
                                {{ $topUp->created_at->format('Y-m-d H:i') }}
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="9">
                                <div class="flex flex-col items-center justify-center py-12 gap-2">
                                    <flux:icon.banknotes class="w-8 h-8 text-light-txt-muted dark:text-dark-txt-muted" />
                                    <p class="font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">No top-up records found.</p>
                                    @if ($search || $filterStatus || $filterSource)
                                        <p class="font-secondary text-xs text-light-txt-muted dark:text-dark-txt-muted">Try adjusting your search or filters.</p>
                                    @endif
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>

        @if ($this->getTopUps->hasPages())
            <div class="flex flex-wrap items-center justify-end gap-2 px-3 sm:px-4 py-2 border-t border-light-bd-default dark:border-dark-bd-default bg-light-secondary dark:bg-dark-secondary">
                {{ $this->getTopUps->links() }}
            </div>
        @endif
    </flux:card>

</div>
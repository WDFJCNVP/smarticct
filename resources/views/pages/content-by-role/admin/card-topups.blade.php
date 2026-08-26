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
    {{-- Heading --}}
    <div class="flex items-start justify-between gap-4 mb-6">
        <div>
            <x-heading
                size="xl"
                class="!font-primary !font-bold !text-light-txt-primary dark:!text-dark-txt-primary"
                style="font-size: var(--text-page-title)"
            >
                Card Top-Ups
            </x-heading>
            <x-text variant="subtle" class="!font-secondary mt-1 block" style="font-size: var(--text-helper)">
                Oversight of all cashier and online top-up activity — revenue, sources, and pending/failed alerts.
            </x-text>
        </div>
    </div>

    {{-- Key stats --}}
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
                <div class="flex items-center justify-center w-6 h-6 sm:w-7 sm:h-7 rounded-lg bg-blue-500/10 dark:bg-blue-400/20 shrink-0">
                    <flux:icon.user class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-blue-600 dark:text-blue-400" />
                </div>
                <x-text class="font-secondary text-xs sm:text-stat-label text-light-txt-muted dark:text-dark-txt-muted">
                    Cashier top-ups (today)
                </x-text>
            </div>
            <x-text class="font-primary text-stat-value font-bold text-blue-600 dark:text-blue-400 block">
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
                <flux:table.columns sticky class="bg-white dark:bg-zinc-900">
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
                                    <flux:icon.banknotes class="w-8 h-8 text-zinc-300" />
                                    <p class="text-sm text-zinc-400">No top-up records found.</p>
                                    @if ($search || $filterStatus || $filterSource)
                                        <p class="text-xs text-zinc-400">Try adjusting your search or filters.</p>
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
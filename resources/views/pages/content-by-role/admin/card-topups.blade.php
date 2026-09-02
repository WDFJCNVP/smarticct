<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Livewire\WithPagination;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

use App\Models\TopUpTransaction;
use App\Models\Card;
use App\Models\User;
use App\Models\CardTransaction;
use App\Models\Notification;
use App\Models\UserNotification;
use App\Events\NotificationEvent;
use App\Services\AuditLogsService;

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

    // ===================== CASH TOP-UP (inline, no redirect) =====================

    public string $cashCardUid    = '';
    public string $cashCardState  = 'ready'; // ready | success | warn
    public string $cashUserSearch = '';
    public bool   $cashSearchMode = false;
    public ?int   $cashSelectedUserId = null;

    public ?int   $cashSelectedAmount  = null;
    public ?int   $cashCustomAmount    = null;
    public ?float $cashAmountReceived  = null;

    public bool $cashShowInsufficientAlert = false;

    public array $cashPresets = [50, 100, 200, 500, 1000];

    public function mount()
    {
        $this->exportDateFrom = today()->toDateString();
        $this->exportDateTo = today()->toDateString();
    }

    // ─── Open / reset the cash top-up modal ────────────────────────────────
    public function openCashTopUpModal(): void
    {
        $this->resetCashTopUpForm();
        $this->dispatch('open-cash-topup-modal');
    }

    public function resetCashTopUpForm(): void
    {
        $this->cashCardUid           = '';
        $this->cashCardState         = 'ready';
        $this->cashUserSearch        = '';
        $this->cashSearchMode        = false;
        $this->cashSelectedUserId    = null;
        $this->cashSelectedAmount    = null;
        $this->cashCustomAmount      = null;
        $this->cashAmountReceived    = null;
    }

    // ─── Computed: card record from tapped UID ─────────────────────────────
    #[Computed]
    public function cashCardRecord(): ?Card
    {
        if ($this->cashSearchMode || empty($this->cashCardUid)) return null;
        return Card::with('user')->where('uid', $this->cashCardUid)->first();
    }

    // ─── Computed: search results for "search by name" mode ───────────────
    #[Computed]
    public function cashSearchResults()
    {
        if (!$this->cashSearchMode || strlen($this->cashUserSearch) < 2) return collect();

        return User::with('card')
            ->whereIn('role', ['commuter', 'operator'])
            ->whereHas('card', fn ($q) => $q->where('status', 'active'))
            ->where(function ($q) {
                $q->where('name', 'like', '%' . $this->cashUserSearch . '%')
                  ->orWhere('user_code', 'like', '%' . $this->cashUserSearch . '%');
            })
            ->limit(8)
            ->get();
    }

    #[Computed]
    public function cashResolvedUser(): ?User
    {
        if ($this->cashSelectedUserId) {
            return User::with('card')->find($this->cashSelectedUserId);
        }
        return $this->cashCardRecord?->user;
    }

    #[Computed]
    public function cashResolvedCard(): ?Card
    {
        return $this->cashResolvedUser?->card;
    }

    #[Computed]
    public function cashTopUpAmount(): int
    {
        if ($this->cashSelectedAmount === -1) {
            return (int) ($this->cashCustomAmount ?? 0);
        }
        return (int) ($this->cashSelectedAmount ?? 0);
    }

    #[Computed]
    public function cashChange(): float
    {
        if ($this->cashAmountReceived && $this->cashTopUpAmount > 0) {
            return max(0, (float) $this->cashAmountReceived - $this->cashTopUpAmount);
        }
        return 0;
    }

    public function updatedCashCardUid(): void
    {
        $this->cashSelectedAmount   = null;
        $this->cashCustomAmount     = null;
        $this->cashAmountReceived   = null;
        $this->cashSelectedUserId   = null;

        if (empty($this->cashCardUid)) {
            $this->cashCardState = 'ready';
            return;
        }

        $card = $this->cashCardRecord;

        if (!$card || $card->status !== 'active') {
            $this->cashCardState = 'warn';
            return;
        }

        $this->cashCardState      = 'success';
        $this->cashSelectedUserId = $card->user_id;
    }

    public function enableCashSearchMode(): void
    {
        $this->cashSearchMode      = true;
        $this->cashCardUid         = '';
        $this->cashCardState       = 'ready';
        $this->cashSelectedUserId  = null;
        $this->cashSelectedAmount  = null;
        $this->cashCustomAmount    = null;
        $this->cashAmountReceived  = null;
        $this->cashUserSearch      = '';
    }

    public function disableCashSearchMode(): void
    {
        $this->cashSearchMode      = false;
        $this->cashUserSearch      = '';
        $this->cashSelectedUserId  = null;
        $this->cashSelectedAmount  = null;
        $this->cashCustomAmount    = null;
        $this->cashAmountReceived  = null;
    }

    public function selectCashUser(int $userId): void
    {
        $this->cashSelectedUserId  = $userId;
        $user                      = User::find($userId);
        $this->cashUserSearch      = $user?->name ?? '';
        $this->cashSelectedAmount  = null;
        $this->cashCustomAmount    = null;
        $this->cashAmountReceived  = null;
    }

    public function clearCashUser(): void
    {
        $this->cashSelectedUserId  = null;
        $this->cashUserSearch      = '';
        $this->cashCardUid         = '';
        $this->cashCardState       = 'ready';
        $this->cashSelectedAmount  = null;
        $this->cashCustomAmount    = null;
        $this->cashAmountReceived  = null;
    }

    public function selectCashPreset(int $amount): void
    {
        $this->cashSelectedAmount  = $amount;
        $this->cashCustomAmount    = null;
        $this->cashAmountReceived  = null;
    }

    public function selectCashCustom(): void
    {
        $this->cashSelectedAmount = -1; // sentinel for custom
        $this->cashAmountReceived = null;
    }

    public function processCashTopUp(): void
    {
        $card = $this->cashResolvedCard;
        $user = $this->cashResolvedUser;

        if (!$card || !$user) {
            Flux::toast(variant: 'warning', duration: 4000, heading: 'No user selected.', text: 'Tap a card or search for a user first.');
            return;
        }

        if ($card->status !== 'active') {
            Flux::toast(variant: 'danger', duration: 4000, heading: 'Card inactive.', text: 'This card is ' . $card->status . ' and cannot be topped up.');
            return;
        }

        $amount = $this->cashTopUpAmount;

        if ($amount <= 0) {
            Flux::toast(variant: 'warning', duration: 4000, heading: 'Invalid amount.', text: 'Please select or enter a top-up amount.');
            return;
        }

        if (empty($this->cashAmountReceived) || (float) $this->cashAmountReceived < $amount) {
            $this->cashShowInsufficientAlert = true;
            return;
        }

        try {
            DB::transaction(function () use ($card, $user, $amount) {
                $card = Card::where('id', $card->id)->lockForUpdate()->first();

                $balanceBefore = (float) $card->balance;
                $balanceAfter  = $balanceBefore + $amount;

                $card->update(['balance' => $balanceAfter]);

                $topUp = TopUpTransaction::create([
                    'processed_by'         => auth()->id(),
                    'user_id'              => $user->id,
                    'card_id'              => $card->id,
                    'checkout_session_id'  => 'CASH-' . now()->format('YmdHis') . '-' . Str::random(8),
                    'points_credited'      => $amount,
                    'amount_paid'          => $amount,
                    'payment_method'       => 'cash',
                    'status'               => 'paid',
                ]);

                CardTransaction::create([
                    'card_id'          => $card->id,
                    'processed_by'     => auth()->id(),
                    'source'           => auth()->user()->role,
                    'reference_no'     => 'TOPUP-' . now()->format('YmdHis') . '-' . Str::random(5),
                    'transaction_type' => 'top-up',
                    'amount'           => $amount,
                    'points_deducted'  => 0,
                    'balance_before'   => $balanceBefore,
                    'balance_after'    => $balanceAfter,
                    'status'           => 'success',
                    'message'          => "Cash top-up of ₱{$amount} processed by admin.",
                    'transaction_time' => now(),
                    'metadata'         => [
                        'payment_method'  => 'cash',
                        'amount_received' => $this->cashAmountReceived,
                        'change'          => $this->cashChange,
                        'top_up_id'       => $topUp->id,
                    ],
                ]);

                $notification = Notification::create([
                    'type'    => 'TopUp',
                    'title'   => 'Card Topped Up',
                    'message' => "₱{$amount} has been added to your card. New balance: ₱" . number_format($balanceAfter, 2) . ".",
                    'metadata' => ['amount' => $amount, 'balance_after' => $balanceAfter],
                ]);

                UserNotification::create([
                    'notification_id' => $notification->id,
                    'user_id'         => $user->id,
                ]);

                broadcast(new NotificationEvent());

                app(AuditLogsService::class)->create([
                    'user_id'  => auth()->id(),
                    'action'   => 'Cash Top Up',
                    'subject'  => 'Card topped up via cash',
                    'channel'  => 'Web',
                    'metadata' => [
                        'ip_address'      => request()->ip(),
                        'message'         => "Topped up ₱{$amount} to {$user->name}'s card (Card: {$card->card_number}).",
                        'amount'          => $amount,
                        'amount_received' => $this->cashAmountReceived,
                        'change'          => $this->cashChange,
                    ],
                ]);
            });

            $change = $this->cashChange;

            $this->resetCashTopUpForm();
            $this->dispatch('close-cash-topup-modal');

            Flux::toast(
                variant: 'success',
                duration: 4000,
                heading: 'Top-up successful!',
                text: "₱{$amount} added. Change: ₱" . number_format($change, 2),
            );

        } catch (\Exception $e) {
            Log::error('Cash top-up failed', ['error' => $e->getMessage(), 'user_id' => $user->id ?? null]);
            Flux::toast(variant: 'danger', duration: 4000, heading: 'Top-up failed.', text: 'Something went wrong while processing this top-up. Please try again.');
        }
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

        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 w-full sm:w-auto shrink-0">
            <flux:button
                variant="primary"
                icon="credit-card"
                wire:click="openCashTopUpModal"
                class="font-secondary w-full sm:w-auto justify-center"
            >
                New Cash Top-Up
            </flux:button>

            <flux:modal.trigger name="export-cashier-transactions" wire:click="prepareExportModal">
                <button
                    type="button"
                    class="flex items-center gap-1.5 sm:gap-2 px-3 h-9 rounded-lg border border-light-bd-default dark:border-dark-bd-default text-light-txt-body dark:text-dark-txt-body hover:bg-light-subtle dark:hover:bg-dark-subtle transition font-secondary text-xs sm:text-table-row shrink-0 w-full sm:w-auto justify-center"
                >
                    <flux:icon.arrow-down-tray class="w-3.5 h-3.5 text-light-txt-muted dark:text-dark-txt-muted" />
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

    {{-- ===================== CASH TOP-UP MODAL (inline, no redirect) ===================== --}}
    <flux:modal
        name="cash-topup-modal"
        :closable="false"
        class="w-[calc(100%-2rem)] sm:max-w-2xl mx-auto rounded-xl overflow-hidden"
        x-on:open-cash-topup-modal.window="$flux.modal('cash-topup-modal').show()"
        x-on:close-cash-topup-modal.window="$flux.modal('cash-topup-modal').close()"
    >
        <div class="flex flex-col p-4 sm:p-6 space-y-5 overflow-y-auto max-h-[80vh]">
            <div class="flex items-start justify-between">
                <div>
                    <flux:heading size="xl" class="!font-primary !font-bold text-light-txt-primary dark:text-dark-txt-primary">
                        New Cash Top-Up
                    </flux:heading>
                    <flux:text class="mt-1 font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">
                        Load balance onto a commuter or operator card via cash payment.
                    </flux:text>
                </div>
                <flux:modal.close>
                    <button type="button" wire:click="resetCashTopUpForm" class="p-1 rounded-full hover:bg-light-subtle dark:hover:bg-dark-subtle text-light-txt-muted dark:text-dark-txt-muted -mt-1">
                        <flux:icon name="x-mark" class="w-5 h-5" />
                    </button>
                </flux:modal.close>
            </div>

            {{-- Card tap status bar --}}
            @if (!$cashSearchMode)
                <div @class([
                    'flex items-center gap-3 p-4 rounded-xl border',
                    'bg-primary/5 dark:bg-primary/10 border-primary/10 dark:border-primary/20'           => $cashCardState === 'ready',
                    'bg-success/10 dark:bg-dark-success/10 border-success/20 dark:border-dark-success/20' => $cashCardState === 'success',
                    'bg-danger/10 dark:bg-dark-danger/10 border-danger/20 dark:border-dark-danger/20'     => $cashCardState === 'warn',
                ])>
                    <flux:icon
                        :name="$cashCardState === 'success' ? 'check-circle' : ($cashCardState === 'warn' ? 'exclamation-triangle' : 'credit-card')"
                        @class([
                            'w-5 h-5 shrink-0',
                            'text-primary dark:text-dark-txt-primary' => $cashCardState === 'ready',
                            'text-success dark:text-dark-success'     => $cashCardState === 'success',
                            'text-danger dark:text-dark-danger'       => $cashCardState === 'warn',
                        ])
                    />
                    <div class="flex-1 min-w-0">
                        <p @class([
                            'font-secondary text-sm font-medium',
                            'text-light-txt-primary dark:text-dark-txt-primary' => $cashCardState === 'ready',
                            'text-success dark:text-dark-success'                => $cashCardState === 'success',
                            'text-danger dark:text-dark-danger'                  => $cashCardState === 'warn',
                        ])>
                            @if ($cashCardState === 'ready') Waiting for card tap
                            @elseif ($cashCardState === 'success') Card recognised
                            @else Card not recognised or inactive
                            @endif
                        </p>
                        <p @class([
                            'font-secondary text-xs',
                            'text-light-txt-muted dark:text-dark-txt-muted' => $cashCardState === 'ready',
                            'text-success/80 dark:text-dark-success/80'     => $cashCardState === 'success',
                            'text-danger/80 dark:text-dark-danger/80'       => $cashCardState === 'warn',
                        ])>
                            @if ($cashCardState === 'ready') Hold the card near the reader — the field fills automatically
                            @elseif ($cashCardState === 'success') UID {{ $cashCardUid }} · {{ $this->cashResolvedUser?->name }}
                            @else Unregistered card or card is suspended/terminated
                            @endif
                        </p>
                    </div>
                    @if ($cashCardState === 'success')
                        <button wire:click="clearCashUser"
                            class="text-light-txt-muted hover:text-light-txt-primary dark:text-dark-txt-muted dark:hover:text-dark-txt-primary transition shrink-0">
                            <flux:icon name="x-mark" class="w-5 h-5" />
                        </button>
                    @endif
                </div>
            @endif

            <div class="flex flex-col sm:flex-row sm:items-end gap-4">
                {{-- Card UID input --}}
                @if (!$cashSearchMode)
                    <div class="flex-1">
                        <flux:field>
                            <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary flex items-center gap-1.5">
                                <flux:icon name="credit-card" class="w-3.5 h-3.5" />
                                Card UID
                            </flux:label>
                            <flux:input
                                wire:model.live.debounce.300ms="cashCardUid"
                                placeholder="Tap card on reader…"
                                autocomplete="off"
                                class="font-mono tracking-widest mt-1"
                            />
                        </flux:field>
                    </div>
                @endif

                {{-- Search by name / user code --}}
                @if ($cashSearchMode)
                    <div class="flex-1">
                        <flux:field>
                            <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary flex items-center gap-1.5">
                                <flux:icon name="magnifying-glass" class="w-3.5 h-3.5" />
                                Search by name or user code
                            </flux:label>
                            <div class="relative mt-1">
                                <flux:input
                                    wire:model.live.debounce.300ms="cashUserSearch"
                                    placeholder="e.g. Juan dela Cruz or USR-0001"
                                    autocomplete="off"
                                    class="w-full"
                                />
                                @if (strlen($cashUserSearch) >= 2 && !$cashSelectedUserId)
                                    <div class="absolute z-20 w-full mt-1 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-lg shadow-xl max-h-52 overflow-y-auto">
                                        @forelse ($this->cashSearchResults as $u)
                                            <div
                                                wire:click="selectCashUser({{ $u->id }})"
                                                class="flex items-center gap-3 px-3 py-2.5 hover:bg-zinc-50 dark:hover:bg-zinc-800 cursor-pointer"
                                            >
                                                <flux:avatar size="xs" src="{{ $u->avatar_url }}" name="{{ $u->name }}" />
                                                <div class="min-w-0">
                                                    <p class="text-sm font-medium text-zinc-800 dark:text-zinc-100 truncate">{{ $u->name }}</p>
                                                    <p class="text-xs text-zinc-400">{{ $u->user_code }} · {{ ucfirst($u->role) }}</p>
                                                </div>
                                                <div class="ml-auto text-right shrink-0">
                                                    <p class="text-xs text-zinc-400">Balance</p>
                                                    <p class="text-sm font-medium text-zinc-700 dark:text-zinc-200">₱{{ number_format($u->card->balance, 2) }}</p>
                                                </div>
                                            </div>
                                        @empty
                                            <div class="px-3 py-2 text-sm text-zinc-400">No users found.</div>
                                        @endforelse
                                    </div>
                                @endif
                            </div>
                        </flux:field>
                    </div>
                @endif

                {{-- Mode toggle buttons --}}
                <div class="shrink-0 flex items-center gap-2 mb-1">
                    @if (!$cashSearchMode)
                        <flux:button wire:click="enableCashSearchMode" variant="ghost" size="sm" class="font-secondary">
                            Search by name
                        </flux:button>
                    @else
                        <flux:button wire:click="disableCashSearchMode" variant="ghost" size="sm" class="font-secondary">
                            Use card tap
                        </flux:button>
                        @if ($cashSelectedUserId)
                            <flux:button wire:click="clearCashUser" variant="danger" size="sm" class="font-secondary">
                                Clear
                            </flux:button>
                        @endif
                    @endif
                </div>
            </div>

            {{-- Top-up form (shown once a user is resolved) --}}
            @if ($this->cashResolvedUser && $this->cashResolvedCard)
                @php $cashUser = $this->cashResolvedUser; $cashCard = $this->cashResolvedCard; @endphp

                <div class="rounded-lg bg-light-subtle dark:bg-dark-secondary border border-light-bd-default dark:border-dark-bd-default p-3 flex items-center gap-3">
                    <flux:avatar src="{{ $cashUser->avatar_url }}" name="{{ $cashUser->name }}" size="sm" />
                    <div class="flex-1 min-w-0">
                        <p class="font-secondary font-semibold text-sm text-light-txt-primary dark:text-dark-txt-primary truncate">{{ $cashUser->name }}</p>
                        <p class="font-secondary text-xs text-light-txt-muted dark:text-dark-txt-muted">
                            {{ $cashUser->user_code }} · **** {{ substr($cashCard->card_number, -4) }} · Balance ₱{{ number_format($cashCard->balance, 2) }}
                        </p>
                    </div>
                    @if ($cashCard->status !== 'active')
                        <flux:badge color="red" size="sm">{{ ucfirst($cashCard->status) }}</flux:badge>
                    @else
                        <flux:badge color="green" size="sm">Active</flux:badge>
                    @endif
                </div>

                {{-- Preset amount buttons --}}
                <div class="grid grid-cols-3 sm:grid-cols-5 gap-2">
                    @foreach ($cashPresets as $preset)
                        <button
                            wire:click="selectCashPreset({{ $preset }})"
                            @class([
                                'rounded-lg border py-3 text-sm font-semibold transition',
                                'border-primary bg-primary/10 text-primary dark:text-primary'       => $cashSelectedAmount === $preset,
                                'border-zinc-200 dark:border-zinc-700 text-zinc-700 dark:text-zinc-300 hover:border-zinc-400 dark:hover:border-zinc-500' => $cashSelectedAmount !== $preset,
                            ])
                        >
                            ₱{{ number_format($preset) }}
                        </button>
                    @endforeach
                </div>

                {{-- Custom amount --}}
                <div>
                    <button
                        wire:click="selectCashCustom"
                        @class([
                            'w-full rounded-lg border py-2.5 text-sm font-medium transition mb-2',
                            'border-primary bg-primary/10 text-primary'                            => $cashSelectedAmount === -1,
                            'border-dashed border-zinc-300 dark:border-zinc-600 text-zinc-500 hover:border-zinc-400' => $cashSelectedAmount !== -1,
                        ])
                    >
                        {{ $cashSelectedAmount === -1 ? 'Custom amount selected' : '+ Enter custom amount' }}
                    </button>

                    @if ($cashSelectedAmount === -1)
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-zinc-400">₱</span>
                            <flux:input
                                wire:model.live.debounce.300ms="cashCustomAmount"
                                type="number"
                                min="1"
                                step="1"
                                placeholder="0"
                                class="pl-7 w-full"
                            />
                        </div>
                    @endif
                </div>

                {{-- Amount received --}}
                @if ($this->cashTopUpAmount > 0)
                    <div class="pt-3 border-t border-zinc-100 dark:border-zinc-800">
                        <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary mb-1 block">
                            Amount Received
                        </flux:label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-zinc-400">₱</span>
                            <flux:input
                                wire:model.live.debounce.300ms="cashAmountReceived"
                                type="number"
                                step="0.01"
                                min="{{ $this->cashTopUpAmount }}"
                                placeholder="{{ $this->cashTopUpAmount }}.00"
                                class="pl-7 w-full"
                            />
                        </div>
                        @if ($cashAmountReceived)
                            <p class="font-secondary text-xs text-light-txt-muted dark:text-dark-txt-muted mt-2">
                                New balance: <span class="font-semibold text-success dark:text-dark-success">₱{{ number_format($cashCard->balance + $this->cashTopUpAmount, 2) }}</span>
                                · Change: <span class="font-semibold text-success dark:text-dark-success">₱{{ number_format($this->cashChange, 2) }}</span>
                            </p>
                        @endif
                    </div>
                @endif
            @else
                <div class="rounded-xl border border-dashed border-light-bd-strong dark:border-dark-bd-strong bg-light-secondary dark:bg-dark-secondary text-center p-6">
                    <flux:icon name="credit-card" class="w-6 h-6 mx-auto text-light-txt-muted dark:text-dark-txt-muted mb-2" />
                    <p class="font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">
                        Tap a card on the reader, or use "Search by name" to find a user.
                    </p>
                </div>
            @endif

            <div class="flex flex-col-reverse sm:flex-row justify-end items-stretch sm:items-center gap-2 pt-2 border-t border-light-bd-default dark:border-dark-bd-default">
                <flux:modal.close class="w-full sm:w-auto">
                    <flux:button type="button" variant="ghost" wire:click="resetCashTopUpForm" class="w-full sm:w-auto justify-center font-secondary">
                        Cancel
                    </flux:button>
                </flux:modal.close>
                <flux:button
                    type="button"
                    variant="primary"
                    icon="banknotes"
                    wire:click="processCashTopUp"
                    wire:loading.attr="disabled"
                    wire:target="processCashTopUp"
                    :disabled="!$this->cashResolvedUser || !$this->cashResolvedCard || $this->cashTopUpAmount <= 0 || !$cashAmountReceived"
                    class="w-full sm:w-auto justify-center font-secondary"
                >
                    <span wire:loading.remove wire:target="processCashTopUp">Confirm Top-Up</span>
                    <span wire:loading wire:target="processCashTopUp">Processing…</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Insufficient amount alert --}}
    <flux:modal wire:model.live="cashShowInsufficientAlert" class="max-w-sm">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg" class="!text-danger dark:!text-dark-danger">Insufficient Amount</flux:heading>
                <flux:subheading>Amount received is less than the top-up amount.</flux:subheading>
            </div>
            <x-text variant="subtle" class="!font-secondary block" style="font-size: var(--text-table-row)">
                The top-up amount is <strong class="text-zinc-800 dark:text-zinc-100">₱{{ number_format($this->cashTopUpAmount, 2) }}</strong>.
                Please enter an amount equal to or greater than this.
            </x-text>
            <div class="flex justify-end">
                <flux:button wire:click="$set('cashShowInsufficientAlert', false)" variant="primary">Got it</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
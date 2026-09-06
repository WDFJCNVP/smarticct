<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

use App\Models\Card;
use App\Models\User;
use App\Models\TopUpTransaction;
use App\Models\CardTransaction;
use App\Models\Notification;
use App\Models\UserNotification;
use App\Events\NotificationEvent;
use App\Services\AuditLogsService;

new #[Layout('layouts.cashier-layout')] class extends Component
{
    // ===================== TOP-UP CONFIRMATION =====================
    public bool $show_topup_modal = false;

    // --- Card tap / search state ---
    public string $card_uid    = '';
    public string $card_state  = 'ready'; // ready | success | warn
    public string $userSearch  = '';
    public bool   $searchMode  = false;

    // --- Top-up form ---
    public ?int   $selectedAmount  = null;
    public ?int   $customAmount    = null;
    public ?float $amount_received = null;

    // --- Alert flags ---
    public bool $showInsufficientAlert = false;

    // ─── Preset amounts ─────────────────────────────────────────────────────
    public array $presets = [50, 100, 200, 500, 1000];

    // Latest cash top-ups processed at this terminal — quick "what just
    // happened here" history strip for the cashier.
    #[Computed]
    public function recentTopUps()
    {
        return TopUpTransaction::with('user', 'card')
            ->where('payment_method', 'cash')
            ->latest()
            ->take(5)
            ->get();
    }

    // ─── Computed: card record from UID ─────────────────────────────────────
    #[Computed]
    public function cardRecord(): ?Card
    {
        if ($this->searchMode || empty($this->card_uid)) return null;
        return Card::with('user')->where('uid', $this->card_uid)->first();
    }

    // ─── Computed: user from search ─────────────────────────────────────────
    #[Computed]
    public function searchResults()
    {
        if (!$this->searchMode || strlen($this->userSearch) < 2) return collect();

        return User::with('card')
            ->whereIn('role', ['commuter', 'operator'])
            ->whereHas('card', fn($q) => $q->where('status', 'active'))
            ->where(function ($q) {
                $q->where('name', 'like', '%' . $this->userSearch . '%')
                  ->orWhere('user_code', 'like', '%' . $this->userSearch . '%');
            })
            ->limit(8)
            ->get();
    }

    // ─── Computed: resolved user (from either tap or search) ─────────────────
    #[Computed]
    public function selectedUser(): ?User
    {
        if (!$this->searchMode) {
            return $this->cardRecord?->user;
        }
        return null; // set manually via selectUser()
    }

    // We store the manually selected user id for search mode
    public ?int $selectedUserId = null;

    #[Computed]
    public function resolvedUser(): ?User
    {
        if ($this->selectedUserId) {
            return User::with('card')->find($this->selectedUserId);
        }
        return $this->cardRecord?->user;
    }

    #[Computed]
    public function resolvedCard(): ?Card
    {
        return $this->resolvedUser?->card;
    }

    // ─── Computed: final amount to top up ────────────────────────────────────
    #[Computed]
    public function topUpAmount(): int
    {
        if ($this->selectedAmount === -1) {
            return (int) ($this->customAmount ?? 0);
        }
        return (int) ($this->selectedAmount ?? 0);
    }

    #[Computed]
    public function change(): float
    {
        if ($this->amount_received && $this->topUpAmount > 0) {
            return max(0, (float) $this->amount_received - $this->topUpAmount);
        }
        return 0;
    }

    // ─── Actions ─────────────────────────────────────────────────────────────

    public function updatedCardUid(): void
    {
        $this->selectedAmount  = null;
        $this->customAmount    = null;
        $this->amount_received = null;
        $this->selectedUserId  = null;

        if (empty($this->card_uid)) {
            $this->card_state = 'ready';
            return;
        }

        $card = $this->cardRecord;

        if (!$card) {
            $this->card_state = 'warn';
            return;
        }

        if ($card->status !== 'active') {
            $this->card_state = 'warn';
            return;
        }

        $this->card_state    = 'success';
        $this->selectedUserId = $card->user_id;
    }

    public function enableSearchMode(): void
    {
        $this->searchMode      = true;
        $this->card_uid        = '';
        $this->card_state      = 'ready';
        $this->selectedUserId  = null;
        $this->selectedAmount  = null;
        $this->customAmount    = null;
        $this->amount_received = null;
        $this->userSearch      = '';
    }

    public function disableSearchMode(): void
    {
        $this->searchMode      = false;
        $this->userSearch      = '';
        $this->selectedUserId  = null;
        $this->selectedAmount  = null;
        $this->customAmount    = null;
        $this->amount_received = null;
    }

    public function selectUser(int $userId): void
    {
        $this->selectedUserId  = $userId;
        $user                  = User::find($userId);
        $this->userSearch      = $user?->name ?? '';
        $this->selectedAmount  = null;
        $this->customAmount    = null;
        $this->amount_received = null;
    }

    public function clearUser(): void
    {
        $this->selectedUserId  = null;
        $this->userSearch      = '';
        $this->card_uid        = '';
        $this->card_state      = 'ready';
        $this->selectedAmount  = null;
        $this->customAmount    = null;
        $this->amount_received = null;
    }

    public function selectPreset(int $amount): void
    {
        $this->selectedAmount  = $amount;
        $this->customAmount    = null;
        $this->amount_received = null;
    }

    public function selectCustom(): void
    {
        $this->selectedAmount  = -1; // sentinel for custom
        $this->amount_received = null;
    }

    public function processTopUp(): void
    {
        $this->show_topup_modal = false;

        $card = $this->resolvedCard;
        $user = $this->resolvedUser;

        if (!$card || !$user) {
            Flux::toast(variant: 'warning', duration: 4000, heading: 'No user selected.', text: 'Tap a card or search for a user first.');
            return;
        }

        if ($card->status !== 'active') {
            Flux::toast(variant: 'danger', duration: 4000, heading: 'Card inactive.', text: 'This card is ' . $card->status . ' and cannot be topped up.');
            return;
        }

        $amount = $this->topUpAmount;

        if ($amount <= 0) {
            Flux::toast(variant: 'warning', duration: 4000, heading: 'Invalid amount.', text: 'Please select or enter a top-up amount.');
            return;
        }

        if (empty($this->amount_received) || (float) $this->amount_received < $amount) {
            $this->showInsufficientAlert = true;
            return;
        }

        try {
            DB::transaction(function () use ($card, $user, $amount) {
                $card = Card::where('id', $card->id)->lockForUpdate()->first();

                $balanceBefore = (float) $card->balance;
                $balanceAfter  = $balanceBefore + $amount;

                // Update card balance
                $card->update(['balance' => $balanceAfter]);

                // Record in top_up_transactions
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

                // Record in card_transactions for the commuter's activity feed
                CardTransaction::create([
                    'card_id'          => $card->id,
                    'processed_by'     => auth()->id(),
                    'source'           => 'cashier',
                    'reference_no'     => 'TOPUP-' . now()->format('YmdHis') . '-' . Str::random(5),
                    'transaction_type' => 'top-up',
                    'amount'           => $amount,
                    // 'points_deducted'  => 0,
                    'balance_before'   => $balanceBefore,
                    'balance_after'    => $balanceAfter,
                    'status'           => 'success',
                    'message'          => "Cash top-up of ₱{$amount} processed by cashier.",
                    'transaction_time' => now(),
                    'metadata'         => [
                        'payment_method'  => 'cash',
                        'amount_received' => $this->amount_received,
                        'change'          => $this->change,
                        'top_up_id'       => $topUp->id,
                    ],
                ]);

                // Notify the user
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

                // Audit log
                app(AuditLogsService::class)->create([
                    'user_id'  => auth()->id(),
                    'action'   => 'Cash Top Up',
                    'subject'  => 'Card topped up via cash',
                    'channel'  => 'Web',
                    'metadata' => [
                        'ip_address'     => request()->ip(),
                        'message'        => "Topped up ₱{$amount} to {$user->name}'s card (Card: {$card->card_number}).",
                        'amount'         => $amount,
                        'amount_received'=> $this->amount_received,
                        'change'         => $this->change,
                    ],
                ]);
            });

            $change = $this->change;

            // Reset form
            $this->clearUser();

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

    public function render(): mixed
    {
        $layout = match (auth()->user()->role) {
            'admin'   => 'layouts.admin-layout',
            default   => 'layouts.cashier-layout',
        };

        return $this->view()->layout($layout);
    }
};
?>

<div>
    {{-- ====== PAGE HEADER (mini-navbar: heading left, notifications right) ====== --}}
    <x-page-header
        heading="Card Top-Up"
        description="Load balance onto a commuter or operator card via cash payment."
        class="mb-4"
    >
        {{-- No extra controls – action button moved below, pinned to the far right --}}
    </x-page-header>

    {{-- ====== PAGE ACTIONS (desktop only — mobile gets it inline with the heading below) ====== --}}
    <div class="hidden sm:flex sm:items-center sm:justify-between gap-3 mb-6">
        <x-heading
            size="xl"
            class="!font-primary !font-bold !text-light-txt-primary dark:!text-dark-txt-primary"
            style="font-size: var(--text-page-title)"
        >
            Card Top-Up
        </x-heading>

        <flux:button
            variant="primary"
            icon="credit-card"
            href="{{ route('cashier.cards.issue') }}"
            wire:navigate
            class="font-secondary shrink-0"
        >
            Issue New Card
        </flux:button>
    </div>

    {{-- Mobile-visible heading, since the page header above is desktop-only --}}
    <div class="sm:hidden flex items-start justify-between gap-4 mb-6">
        <div>
            <x-heading
                size="xl"
                class="!font-primary !font-bold !text-light-txt-primary dark:!text-dark-txt-primary"
                style="font-size: var(--text-page-title)"
            >
                Card Top-Up
            </x-heading>
        </div>

        <flux:button
            variant="primary"
            icon="credit-card"
            href="{{ route('cashier.cards.issue') }}"
            wire:navigate
            class="font-secondary shrink-0"
        >
            Issue New Card
        </flux:button>
    </div>

    {{-- ─── Recent history of cash top-ups processed at this terminal ─────── --}}
    <flux:card class="p-0! overflow-hidden mb-6">
        <div class="px-3 sm:px-4 py-2.5 border-b border-light-bd-default dark:border-dark-bd-default">
            <p class="font-secondary font-semibold text-sm text-light-txt-primary dark:text-dark-txt-primary">Top-Up History</p>
            <p class="font-secondary text-xs text-light-txt-muted dark:text-dark-txt-muted">Latest cash top-ups processed at this terminal.</p>
        </div>
        <div class="overflow-x-auto">
            <flux:table>
                <flux:table.columns sticky class="bg-light-secondary/50 items-center bg-light-subtle/50 dark:bg-dark-secondary/50 font-secondary text-nav-label text-light-txt-muted dark:text-dark-txt-muted">
                    <flux:table.column align="center" class="px-1! sm:px-2! md:px-4! py-2">#</flux:table.column>
                    <flux:table.column align="center" class="px-1 sm:px-2 md:px-4 py-2">Cardholder</flux:table.column>
                    <flux:table.column align="center" class="px-1 sm:px-2 md:px-4 py-2">Role</flux:table.column>
                    <flux:table.column align="center" class="px-1 sm:px-2 md:px-4 py-2">Amount</flux:table.column>
                    <flux:table.column align="center" class="px-1 sm:px-2 md:px-4 py-2">Status</flux:table.column>
                    <flux:table.column align="center" class="px-1! sm:px-2! md:px-4! py-2">Processed</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($this->recentTopUps as $index => $topUp)
                        <flux:table.row :key="$topUp->id">
                            <flux:table.cell align="center" class="px-1! sm:px-2! md:px-4! py-1.5 md:py-2 font-secondary text-xs md:text-table-row text-light-txt-muted dark:text-dark-txt-muted">
                                {{ $index + 1 }}
                            </flux:table.cell>

                            <flux:table.cell align="center" class="px-1 sm:px-2 md:px-4 py-1.5 md:py-2">
                                <div class="flex items-center justify-center gap-2">
                                    <flux:avatar size="xs" src="{{ $topUp->user?->avatar_url }}" name="{{ $topUp->user?->name }}" />
                                    <span class="font-secondary text-xs md:text-table-row text-light-txt-body dark:text-dark-txt-body">{{ $topUp->user?->name ?? 'Unknown' }}</span>
                                </div>
                            </flux:table.cell>

                            <flux:table.cell align="center" class="px-1 sm:px-2 md:px-4 py-1.5 md:py-2">
                                <flux:badge size="sm" color="{{ $topUp->user?->role === 'operator' ? 'blue' : 'amber' }}" class="font-secondary text-badge text-xs">
                                    {{ ucfirst($topUp->user?->role ?? '—') }}
                                </flux:badge>
                            </flux:table.cell>

                            <flux:table.cell align="center" class="px-1 sm:px-2 md:px-4 py-1.5 md:py-2 font-primary tabular-nums font-semibold text-xs md:text-table-row text-light-txt-primary dark:text-dark-txt-primary">
                                ₱{{ number_format($topUp->amount_paid, 2) }}
                            </flux:table.cell>

                            <flux:table.cell align="center" class="px-1 sm:px-2 md:px-4 py-1.5 md:py-2">
                                @if ($topUp->status === 'paid')
                                    <flux:badge color="green" size="sm" class="font-secondary text-badge text-xs">Paid</flux:badge>
                                @else
                                    <flux:badge color="red" size="sm" class="font-secondary text-badge text-xs">{{ ucfirst($topUp->status) }}</flux:badge>
                                @endif
                            </flux:table.cell>

                            <flux:table.cell align="center" class="px-1! sm:px-2! md:px-4! py-1.5 md:py-2 font-secondary text-xs md:text-table-row text-light-txt-muted dark:text-dark-txt-muted tabular-nums">
                                {{ $topUp->created_at->format('M d, Y g:i a') }}
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="6" class="px-2 md:px-4 py-4">
                                <div class="flex flex-col items-center justify-center py-4 gap-2">
                                    <flux:icon.banknotes class="w-6 h-6 text-light-txt-muted dark:text-dark-txt-muted" />
                                    <x-text class="font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">
                                        No top-ups have been processed yet.
                                    </x-text>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
    </flux:card>

    {{-- ─── Step 1: Find User ──────────────────────────────────────────────── --}}
    <x-card class="!p-0 mb-6">

        {{-- Card tap status bar --}}
        @if (!$searchMode)
            <div @class([
                'flex items-center gap-3 p-4 rounded-t-xl border-b',
                'bg-primary/5 dark:bg-primary/10 border-primary/10 dark:border-primary/20'           => $card_state === 'ready',
                'bg-success/10 dark:bg-dark-success/10 border-success/20 dark:border-dark-success/20' => $card_state === 'success',
                'bg-danger/10 dark:bg-dark-danger/10 border-danger/20 dark:border-dark-danger/20'     => $card_state === 'warn',
            ])>
                <flux:icon
                    :name="$card_state === 'success' ? 'check-circle' : ($card_state === 'warn' ? 'exclamation-triangle' : 'credit-card')"
                    @class([
                        'w-5 h-5 shrink-0',
                        'text-primary dark:text-dark-txt-primary' => $card_state === 'ready',
                        'text-success dark:text-dark-success'     => $card_state === 'success',
                        'text-danger dark:text-dark-danger'       => $card_state === 'warn',
                    ])
                />
                <div class="flex-1 min-w-0">
                    <p @class([
                        'font-secondary text-sm font-medium',
                        'text-light-txt-primary dark:text-dark-txt-primary' => $card_state === 'ready',
                        'text-success dark:text-dark-success'                => $card_state === 'success',
                        'text-danger dark:text-dark-danger'                  => $card_state === 'warn',
                    ])>
                        @if ($card_state === 'ready') Waiting for card tap
                        @elseif ($card_state === 'success') Card recognised
                        @else Card not recognised or inactive
                        @endif
                    </p>
                    <p @class([
                        'font-secondary text-xs',
                        'text-light-txt-muted dark:text-dark-txt-muted' => $card_state === 'ready',
                        'text-success/80 dark:text-dark-success/80'     => $card_state === 'success',
                        'text-danger/80 dark:text-dark-danger/80'       => $card_state === 'warn',
                    ])>
                        @if ($card_state === 'ready') Hold the card near the reader — the field fills automatically
                        @elseif ($card_state === 'success') UID {{ $card_uid }} · {{ $this->resolvedUser?->name }}
                        @else Unregistered card or card is suspended/terminated
                        @endif
                    </p>
                </div>
                @if ($card_state === 'success')
                    <button wire:click="clearUser"
                        class="text-light-txt-muted hover:text-light-txt-primary dark:text-dark-txt-muted dark:hover:text-dark-txt-primary transition shrink-0">
                        <flux:icon name="x-mark" class="w-5 h-5" />
                    </button>
                @elseif ($card_state === 'warn' && !$this->cardRecord)
                    <flux:button
                        href="{{ route('cashier.cards.issue', ['uid' => $card_uid]) }}"
                        wire:navigate
                        variant="danger"
                        size="sm"
                        icon="credit-card"
                        class="font-secondary shrink-0"
                    >
                        Issue this card
                    </flux:button>
                @endif
            </div>
        @endif

        <div class="p-5 space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-end gap-4">

                {{-- Card UID input --}}
                @if (!$searchMode)
                    <div class="flex-1">
                        <flux:field>
                            <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary flex items-center gap-1.5">
                                <flux:icon name="credit-card" class="w-3.5 h-3.5" />
                                Card UID
                            </flux:label>
                            <x-input
                                id="topup-rfid-input"
                                wire:model.live.debounce.300ms="card_uid"
                                placeholder="Tap card on reader…"
                                autocomplete="off"
                                class="font-mono tracking-widest mt-1"
                                autofocus
                            />
                        </flux:field>
                    </div>
                @endif

                {{-- Search by name / user code --}}
                @if ($searchMode)
                    <div class="flex-1">
                        <flux:field>
                            <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary flex items-center gap-1.5">
                                <flux:icon name="magnifying-glass" class="w-3.5 h-3.5" />
                                Search by name or user code
                            </flux:label>
                            <div class="relative mt-1">
                                <x-input
                                    wire:model.live.debounce.300ms="userSearch"
                                    placeholder="e.g. Juan dela Cruz or USR-0001"
                                    autocomplete="off"
                                    class="w-full"
                                    autofocus
                                />
                                @if (strlen($userSearch) >= 2 && !$selectedUserId)
                                    <div class="absolute z-20 w-full mt-1 bg-light-primary dark:bg-dark-surface border border-light-bd-default dark:border-dark-bd-default rounded-lg shadow-xl max-h-52 overflow-y-auto">
                                        @forelse ($this->searchResults as $u)
                                            <div
                                                wire:click="selectUser({{ $u->id }})"
                                                class="flex items-center gap-3 px-3 py-2.5 hover:bg-light-subtle dark:hover:bg-dark-subtle cursor-pointer"
                                            >
                                                <flux:avatar size="xs" src="{{ $u->avatar_url }}" name="{{ $u->name }}" />
                                                <div class="min-w-0">
                                                    <p class="font-secondary text-sm font-medium text-light-txt-primary dark:text-dark-txt-primary truncate">{{ $u->name }}</p>
                                                    <p class="font-secondary text-xs text-light-txt-muted dark:text-dark-txt-muted">{{ $u->user_code }} · {{ ucfirst($u->role) }}</p>
                                                </div>
                                                <div class="ml-auto text-right shrink-0">
                                                    <p class="font-secondary text-xs text-light-txt-muted dark:text-dark-txt-muted">Balance</p>
                                                    <p class="font-secondary text-sm font-medium text-light-txt-body dark:text-dark-txt-body">₱{{ number_format($u->card->balance, 2) }}</p>
                                                </div>
                                            </div>
                                        @empty
                                            <div class="px-3 py-2 font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">No users found.</div>
                                        @endforelse
                                    </div>
                                @endif
                            </div>
                        </flux:field>
                    </div>
                @endif

                {{-- Mode toggle buttons --}}
                <div class="shrink-0 flex items-center gap-2 mb-1">
                    @if (!$searchMode)
                        <flux:button wire:click="enableSearchMode" variant="ghost" size="sm" class="font-secondary">
                            Search by name
                        </flux:button>
                    @else
                        <flux:button wire:click="disableSearchMode" variant="ghost" size="sm" class="font-secondary">
                            Use card tap
                        </flux:button>
                        @if ($selectedUserId)
                            <flux:button wire:click="clearUser" variant="danger" size="sm" class="font-secondary">
                                Clear
                            </flux:button>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    </x-card>

    {{-- ─── Step 2: Top-up form (shown once user is resolved) ─────────────── --}}
    @if ($this->resolvedUser && $this->resolvedCard)
        @php $user = $this->resolvedUser; $card = $this->resolvedCard; @endphp

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">

            {{-- Left: user info + amount picker --}}
            <div class="lg:col-span-2 space-y-6">

                {{-- User info card --}}
                <x-card class="!p-0 overflow-hidden">
                    <div class="px-4 sm:px-5 py-3 border-b border-light-bd-default dark:border-dark-bd-default bg-light-secondary/50 dark:bg-dark-secondary/50">
                        <h3 class="font-primary text-card-title font-semibold text-light-txt-primary dark:text-dark-txt-primary">
                            Cardholder
                        </h3>
                    </div>
                    <div class="flex items-center gap-4 p-4 sm:p-5">
                        <flux:avatar src="{{ $user->avatar_url }}" name="{{ $user->name }}" size="lg" class="shrink-0" />
                        <div class="flex-1 min-w-0">
                            <p class="font-secondary font-semibold text-light-txt-primary dark:text-dark-txt-primary truncate">{{ $user->name }}</p>
                            <p class="font-secondary text-xs text-light-txt-muted dark:text-dark-txt-muted mt-0.5">{{ $user->user_code }} · {{ ucfirst($user->role) }}</p>
                            <p class="font-secondary text-xs text-light-txt-muted dark:text-dark-txt-muted">Card: <span class="font-mono">**** {{ substr($card->card_number, -4) }}</span></p>
                        </div>
                        <div class="text-right shrink-0">
                            <p class="font-secondary text-xs text-light-txt-muted dark:text-dark-txt-muted mb-0.5">Current balance</p>
                            <p class="font-primary text-xl font-bold text-light-txt-primary dark:text-dark-txt-primary">₱{{ number_format($card->balance, 2) }}</p>
                            @if ($card->status !== 'active')
                                <flux:badge color="red" size="sm" class="mt-1">{{ ucfirst($card->status) }}</flux:badge>
                            @else
                                <flux:badge color="green" size="sm" class="mt-1">Active</flux:badge>
                            @endif
                        </div>
                    </div>
                </x-card>

                {{-- Amount picker --}}
                <x-card class="!p-0 overflow-hidden">
                    <div class="px-4 sm:px-5 py-3 border-b border-light-bd-default dark:border-dark-bd-default bg-light-secondary/50 dark:bg-dark-secondary/50">
                        <h3 class="font-primary text-card-title font-semibold text-light-txt-primary dark:text-dark-txt-primary">
                            Top-up Amount
                        </h3>
                    </div>
                    <div class="p-4 sm:p-5 space-y-4">

                        {{-- Preset buttons --}}
                        <div class="grid grid-cols-3 sm:grid-cols-5 gap-2">
                            @foreach ($presets as $preset)
                                <button
                                    wire:click="selectPreset({{ $preset }})"
                                    @class([
                                        'rounded-lg border py-3 text-sm font-semibold transition',
                                        'border-primary bg-primary/10 text-primary dark:text-primary'       => $selectedAmount === $preset,
                                        'border-light-bd-default dark:border-dark-bd-default text-light-txt-body dark:text-dark-txt-body hover:border-light-bd-strong dark:hover:border-dark-bd-strong' => $selectedAmount !== $preset,
                                    ])
                                >
                                    ₱{{ number_format($preset) }}
                                </button>
                            @endforeach
                        </div>

                        {{-- Custom amount --}}
                        <div>
                            <button
                                wire:click="selectCustom"
                                @class([
                                    'w-full rounded-lg border py-2.5 text-sm font-medium transition mb-2',
                                    'border-primary bg-primary/10 text-primary'                            => $selectedAmount === -1,
                                    'border-dashed border-light-bd-strong dark:border-dark-bd-strong text-light-txt-muted dark:text-dark-txt-muted hover:border-light-bd-default dark:hover:border-dark-bd-default' => $selectedAmount !== -1,
                                ])
                            >
                                {{ $selectedAmount === -1 ? 'Custom amount selected' : '+ Enter custom amount' }}
                            </button>

                            @if ($selectedAmount === -1)
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-light-txt-muted dark:text-dark-txt-muted">₱</span>
                                    <x-input
                                        wire:model.live.debounce.300ms="customAmount"
                                        type="number"
                                        min="1"
                                        step="1"
                                        placeholder="0"
                                        class="pl-7 w-full"
                                        autofocus
                                    />
                                </div>
                            @endif
                        </div>

                        {{-- Amount received --}}
                        @if ($this->topUpAmount > 0)
                            <div class="pt-3 border-t border-light-bd-default dark:border-dark-bd-default">
                                <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary mb-1 block">
                                    Amount Received
                                </flux:label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-light-txt-muted dark:text-dark-txt-muted">₱</span>
                                    <x-input
                                        wire:model.live.debounce.300ms="amount_received"
                                        type="number"
                                        step="0.01"
                                        min="{{ $this->topUpAmount }}"
                                        placeholder="{{ $this->topUpAmount }}.00"
                                        class="pl-7 w-full"
                                    />
                                </div>
                            </div>
                        @endif
                    </div>
                </x-card>
            </div>

            {{-- Right: Summary + confirm --}}
            <div class="lg:col-span-1 lg:sticky lg:top-4">
                <x-card class="!p-0 overflow-hidden">
                    <div class="px-4 sm:px-5 py-3 border-b border-light-bd-default dark:border-dark-bd-default bg-light-secondary/50 dark:bg-dark-secondary/50">
                        <h3 class="font-primary text-card-title font-semibold text-light-txt-primary dark:text-dark-txt-primary">
                            Summary
                        </h3>
                    </div>
                    <div class="p-4 sm:p-5">
                        <div class="divide-y divide-light-bd-default dark:divide-dark-bd-default font-secondary text-sm">
                            <div class="flex justify-between items-center gap-3 py-1.5">
                                <span class="text-light-txt-muted dark:text-dark-txt-muted shrink-0">Cardholder</span>
                                <span class="font-medium text-light-txt-body dark:text-dark-txt-body truncate min-w-0 text-right">{{ $user->name }}</span>
                            </div>
                            <div class="flex justify-between items-center gap-3 py-1.5">
                                <span class="text-light-txt-muted dark:text-dark-txt-muted shrink-0">Card</span>
                                <span class="font-mono text-light-txt-muted dark:text-dark-txt-muted truncate min-w-0 text-right">**** {{ substr($card->card_number, -4) }}</span>
                            </div>
                            <div class="flex justify-between items-center gap-3 py-1.5">
                                <span class="text-light-txt-muted dark:text-dark-txt-muted shrink-0">Current balance</span>
                                <span class="text-light-txt-body dark:text-dark-txt-body truncate min-w-0 text-right">₱{{ number_format($card->balance, 2) }}</span>
                            </div>
                            <div class="flex justify-between items-center gap-3 py-1.5">
                                <span class="text-light-txt-muted dark:text-dark-txt-muted shrink-0">Top-up amount</span>
                                <span class="font-semibold text-light-txt-primary dark:text-dark-txt-primary truncate min-w-0 text-right">
                                    {{ $this->topUpAmount > 0 ? '₱' . number_format($this->topUpAmount, 2) : '—' }}
                                </span>
                            </div>
                            <div class="flex justify-between items-center gap-3 py-1.5">
                                <span class="text-light-txt-muted dark:text-dark-txt-muted shrink-0">New balance</span>
                                <span class="font-semibold text-success dark:text-dark-success truncate min-w-0 text-right">
                                    {{ $this->topUpAmount > 0 ? '₱' . number_format($card->balance + $this->topUpAmount, 2) : '—' }}
                                </span>
                            </div>
                            @if ($amount_received)
                                <div class="flex justify-between items-center gap-3 py-1.5">
                                    <span class="text-light-txt-muted dark:text-dark-txt-muted shrink-0">Amount received</span>
                                    <span class="text-light-txt-body dark:text-dark-txt-body truncate min-w-0 text-right">₱{{ number_format($amount_received, 2) }}</span>
                                </div>
                                <div class="flex justify-between items-center gap-3 py-1.5">
                                    <span class="text-light-txt-muted dark:text-dark-txt-muted shrink-0">Change</span>
                                    <span class="font-semibold text-success dark:text-dark-success truncate min-w-0 text-right">₱{{ number_format($this->change, 2) }}</span>
                                </div>
                            @endif
                        </div>

                        <x-button
                            variant="primary"
                            size="sm"
                            class="mt-4 w-full !font-secondary"
                            :disabled="$this->topUpAmount <= 0 || !$amount_received || $card->status !== 'active'"
                            wire:click="$set('show_topup_modal', true)"
                        >
                            Confirm Top-Up
                        </x-button>

                        @if ($card->status !== 'active')
                            <p class="font-secondary text-xs text-danger dark:text-dark-danger text-center mt-2">Card is {{ $card->status }} — top-up unavailable.</p>
                        @endif
                    </div>
                </x-card>
            </div>

        </div>
    @else
        {{-- Empty state --}}
        <x-card class="!rounded-xl !border !border-dashed !border-light-bd-strong dark:!border-dark-bd-strong !bg-light-secondary dark:!bg-dark-secondary !text-center !p-8">
            <flux:icon name="credit-card" class="w-8 h-8 mx-auto text-light-txt-muted dark:text-dark-txt-muted mb-2" />
            <x-text variant="subtle" class="!font-secondary block" style="font-size: var(--text-table-row)">
                No cardholder selected yet.
            </x-text>
            <x-text variant="subtle" class="!font-secondary block mt-1" style="font-size: var(--text-timestamp)">
                Tap a card on the reader, or use "Search by name" to find a user.
            </x-text>
        </x-card>
    @endif

    {{-- Insufficient amount modal --}}
    <flux:modal wire:model.live="showInsufficientAlert" class="max-w-sm">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg" class="!text-danger dark:!text-dark-danger">Insufficient Amount</flux:heading>
                <flux:subheading>Amount received is less than the top-up amount.</flux:subheading>
            </div>
            <x-text variant="subtle" class="!font-secondary block" style="font-size: var(--text-table-row)">
                The top-up amount is <strong class="text-light-txt-primary dark:text-dark-txt-primary">₱{{ number_format($this->topUpAmount, 2) }}</strong>.
                Please enter an amount equal to or greater than this.
            </x-text>
            <div class="flex justify-end">
                <flux:button wire:click="$set('showInsufficientAlert', false)" variant="primary">Got it</flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Top-up confirmation --}}
    <flux:modal wire:model="show_topup_modal" :closable="false" class="w-[calc(100%-2rem)] max-w-xs sm:max-w-sm">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Confirm top-up?</flux:heading>
                <flux:text class="mt-2">
                    Process ₱{{ number_format($this->topUpAmount, 2) }} top-up for {{ $this->resolvedUser?->name }}?
                </flux:text>
            </div>
            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">Cancel</flux:button>
                </flux:modal.close>
                <flux:button wire:click="processTopUp" wire:loading.attr="disabled" wire:target="processTopUp" variant="primary">
                    <span wire:loading.remove wire:target="processTopUp">Confirm Top-Up</span>
                    <span wire:loading wire:target="processTopUp">Processing…</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
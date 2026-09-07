<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Flux\Flux;

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
    // ===================== CASH TOP-UP =====================

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

            try {
                broadcast(new NotificationEvent());
            } catch (\Exception $e) {
                // The top-up already succeeded above — a broadcast/websocket
                // hiccup (e.g. Reverb not running) should only cost real-time
                // UI refresh, not the transaction itself.
                Log::warning('Top-up succeeded but notification broadcast failed', ['error' => $e->getMessage()]);
            }

            $change = $this->cashChange;

            Flux::toast(
                variant: 'success',
                duration: 4000,
                heading: 'Top-up successful!',
                text: "₱{$amount} added. Change: ₱" . number_format($change, 2),
            );

            // Cash payment succeeded — go back to the top-up log.
            $this->redirect(route('admin.topups'), navigate: true);
            return;

        } catch (\Exception $e) {
            Log::error('Cash top-up failed', ['error' => $e->getMessage(), 'user_id' => $user->id ?? null]);
            Flux::toast(variant: 'danger', duration: 4000, heading: 'Top-up failed.', text: 'Something went wrong while processing this top-up. Please try again.');
        }
    }
};
?>

<div>
    {{-- ====== PAGE HEADER (mini-navbar: heading left, notifications right) ====== --}}
    <x-page-header
        heading="New Cash Top-Up"
        description="Load balance onto a commuter or operator card via cash payment."
        class="mb-3"
    >
        {{-- No extra controls – keep it minimal --}}
    </x-page-header>

    {{-- Breadcrumbs on top on mobile; heading + breadcrumbs side-by-side from sm up --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-6">
        <flux:breadcrumbs class="order-1 sm:order-2">
            <flux:breadcrumbs.item href="{{ route('admin.topups') }}" wire:navigate>Back to Card Top-Ups</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>New Cash Top-Up</flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div class="order-2 sm:order-1">
            <x-heading
                size="xl"
                class="!font-primary !font-bold !text-light-txt-primary dark:!text-dark-txt-primary"
                style="font-size: var(--text-page-title)"
            >
                New Cash Top-Up
            </x-heading>
        </div>
    </div>

    <x-card class="!p-0">
        <div class="flex flex-col p-4 sm:p-6 space-y-5">
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
                                autofocus
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
                                    autofocus
                                />
                                @if (strlen($cashUserSearch) >= 2 && !$cashSelectedUserId)
                                    <div class="absolute z-20 w-full mt-1 bg-light-primary dark:bg-dark-surface border border-light-bd-default dark:border-dark-bd-default rounded-lg shadow-xl max-h-52 overflow-y-auto">
                                        @forelse ($this->cashSearchResults as $u)
                                            <div
                                                wire:click="selectCashUser({{ $u->id }})"
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
                                'border-light-bd-default dark:border-dark-bd-default text-light-txt-body dark:text-dark-txt-body hover:border-light-bd-strong dark:hover:border-dark-bd-strong' => $cashSelectedAmount !== $preset,
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
                            'border-dashed border-light-bd-strong dark:border-dark-bd-strong text-light-txt-muted dark:text-dark-txt-muted hover:border-light-bd-default dark:hover:border-dark-bd-default' => $cashSelectedAmount !== -1,
                        ])
                    >
                        {{ $cashSelectedAmount === -1 ? 'Custom amount selected' : '+ Enter custom amount' }}
                    </button>

                    @if ($cashSelectedAmount === -1)
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-light-txt-muted dark:text-dark-txt-muted">₱</span>
                            <flux:input
                                wire:model.live.debounce.300ms="cashCustomAmount"
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
                @if ($this->cashTopUpAmount > 0)
                    <div class="pt-3 border-t border-light-bd-default dark:border-dark-bd-default">
                        <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary mb-1 block">
                            Amount Received
                        </flux:label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-light-txt-muted dark:text-dark-txt-muted">₱</span>
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
                <flux:link href="{{ route('admin.topups') }}" wire:navigate class="w-full sm:w-auto">
                    <flux:button type="button" variant="ghost" class="w-full sm:w-auto justify-center font-secondary">
                        Cancel
                    </flux:button>
                </flux:link>
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
    </x-card>

    {{-- Insufficient amount alert --}}
    <flux:modal wire:model.live="cashShowInsufficientAlert" class="max-w-sm">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg" class="!text-danger dark:!text-dark-danger">Insufficient Amount</flux:heading>
                <flux:subheading>Amount received is less than the top-up amount.</flux:subheading>
            </div>
            <x-text variant="subtle" class="!font-secondary block" style="font-size: var(--text-table-row)">
                The top-up amount is <strong class="text-light-txt-primary dark:text-dark-txt-primary">₱{{ number_format($this->cashTopUpAmount, 2) }}</strong>.
                Please enter an amount equal to or greater than this.
            </x-text>
            <div class="flex justify-end">
                <flux:button wire:click="$set('cashShowInsufficientAlert', false)" variant="primary">Got it</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
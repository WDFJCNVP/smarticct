<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use App\Models\Card;
use App\Services\CheckoutSessionService;
use App\Models\CardReport;
use App\Models\CardTransaction;
use Illuminate\Support\Facades\Log;

new class extends Component
{
    public $amount = 100; // Default preset amount

    #[Computed]
    public function recentActivity()
    {
        if (!$this->userCard) {
            return collect();
        }

        $type = auth()->user()->role === 'operator' ? 'queueing_fee' : 'queue_deduction';

        return $this->userCard
            ->cardTransactions()
            ->where('transaction_type', $type)
            ->latest('transaction_time')
            ->limit(10)
            ->get();
    }

    #[Computed]
    public function totalEarnings()
    {
        return $this->userCard
            ->cardTransactions()
            ->where('transaction_type', 'fare_earning')
            ->where('status', 'success')
            ->sum('amount');
    }

    #[Computed]
    public function userCard(): ?Card
    {
        return Card::with('user')
            ->where('user_id', auth()->id())
            ->first();
    }

    public function proceedToPayment(CheckoutSessionService $checkoutSession)
    {
        $this->validate([
            'amount' => 'required|numeric|min:1|max:10000',
        ]);

        $card = $this->userCard;
        if (!$card) {
            $this->addError('payment_error', 'No card linked to your account.');
            return;
        }

        try {
            $checkoutUrl = $checkoutSession->createCheckoutSession(auth()->user(), $card, (float) $this->amount);
            return redirect()->away($checkoutUrl);
        }
        catch (\Exception $e) {
            Log::error('Card top-up checkout session failed', ['error' => $e->getMessage(), 'user_id' => auth()->id()]);
            $this->addError('payment_error', 'We couldn\'t start your payment right now. Please try again in a moment.');
        }
    }

    #[Computed]
    public function existingPendingReport(): bool
    {
        if (!$this->userCard) return false;
        return CardReport::where('card_id', $this->userCard->id)
            ->where('status', 'pending')
            ->exists();
    }

    public function render()
    {
        $role = auth()->user()->role;
        return $this->view()->layout('layouts.' . $role . '-layout');
    }
};
?>

<div>
    {{-- ====== PAGE HEADER (consistent with dashboard) ====== --}}
    <x-page-header
        heading="My ICCT Card's details"
        class="mb-6"
    >
        {{-- No extra buttons/slots for now – keep it clean --}}
    </x-page-header>

    @if ($this->userCard)
        {{-- Removed sticky wrapper – cards now flow naturally --}}
        <div class="mb-5">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5 items-stretch">

                {{-- Card visual – clean background, no overlapping text --}}
                <div
                    class="relative rounded-2xl shadow-xl overflow-hidden aspect-[579/371] w-full text-white"
                    style="background-image: url('{{ asset('images/card_front.svg') }}'); background-size: cover; background-position: center;"
                >
                </div>

                {{-- Balance card – now includes card details below balance --}}
                <flux:card x-data="{ showBalance: false }" class="p-4 sm:p-5 flex flex-col">
                    <div class="flex items-center gap-1.5 sm:gap-2 mb-1.5">
                        <div class="flex items-center justify-center w-6 h-6 sm:w-7 sm:h-7 rounded-lg bg-primary/10 dark:bg-primary/20 shrink-0">
                            <flux:icon.credit-card class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-primary dark:text-dark-txt-primary" />
                        </div>
                        <x-text class="font-secondary text-xs sm:text-stat-label text-light-txt-muted dark:text-dark-txt-muted">
                            Total balance
                        </x-text>
                        <button
                            type="button"
                            @click="showBalance = !showBalance"
                            class="ml-auto text-light-txt-muted dark:text-dark-txt-muted hover:text-light-txt-body dark:hover:text-dark-txt-primary transition-colors focus:outline-none"
                            title="Toggle Balance Visibility"
                        >
                            <flux:icon name="eye" class="w-4 h-4" x-show="!showBalance" />
                            <flux:icon name="eye-slash" class="w-4 h-4" x-show="showBalance" x-cloak />
                        </button>
                    </div>

                    <x-text class="font-primary text-2xl sm:text-3xl font-bold text-light-txt-primary dark:text-dark-txt-primary block h-[40px] flex items-center">
                        <span x-show="showBalance">
                            ₱{{ number_format($this->userCard->balance, 2) }}
                        </span>
                        <span x-show="!showBalance" x-cloak class="tracking-wider">
                            ₱••••••
                        </span>
                    </x-text>

                    {{-- Enhanced Card details block – larger, more spacing, fills whitespace --}}
                    <div class="mt-4 pt-4 border-t border-light-bd-default dark:border-dark-bd-default space-y-3">
                        <div class="flex justify-between items-center">
                            <x-text class="text-sm font-medium text-light-txt-muted dark:text-dark-txt-muted">Card Number</x-text>
                            <x-text class="text-base font-mono text-light-txt-body dark:text-dark-txt-primary tracking-wider">
                                {{ $this->userCard->card_number }}
                            </x-text>
                        </div>
                        <div class="flex justify-between items-center">
                            <x-text class="text-sm font-medium text-light-txt-muted dark:text-dark-txt-muted">Cardholder</x-text>
                            <x-text class="text-base font-semibold text-light-txt-body dark:text-dark-txt-primary">
                                {{ $this->userCard->user->name }}
                            </x-text>
                        </div>
                        <div class="flex justify-between items-center">
                            <x-text class="text-sm font-medium text-light-txt-muted dark:text-dark-txt-muted">Type</x-text>
                            <x-text class="text-base font-semibold capitalize text-light-txt-body dark:text-dark-txt-primary">
                                {{ auth()->user()->role }}
                            </x-text>
                        </div>
                    </div>

                    <div class="mt-auto pt-3.5">
                        {{-- Pending report notice --}}
                        @if ($this->existingPendingReport)
                            <div class="mb-3 rounded-lg bg-warning/10 dark:bg-dark-warning/20 border border-warning/30 dark:border-dark-warning/30 px-3 py-2 flex items-center gap-2">
                                <flux:icon name="clock" class="w-4 h-4 text-warning dark:text-dark-warning shrink-0" />
                                <x-text class="text-xs text-warning dark:text-dark-warning">
                                    You have a pending lost card report. Visit the terminal to complete the replacement.
                                </x-text>
                            </div>
                        @endif

                        <div class="flex gap-2 pt-3 border-t border-light-bd-default dark:border-dark-bd-default">
                            <flux:button x-on:click="$flux.modal('top-up-modal').show()" size="sm" class="flex-1 font-secondary justify-center" icon="plus" variant="primary">
                                Top up
                            </flux:button>

                            {{-- Report Lost button --}}
                            @if ($this->existingPendingReport)
                                <flux:button
                                    href="{{ route('user.card.report') }}"
                                    wire:navigate
                                    size="sm"
                                    variant="ghost"
                                    class="flex-1 font-secondary justify-center"
                                    icon="clock"
                                >
                                    Report pending
                                </flux:button>
                            @else
                                <flux:button
                                    href="{{ route('user.card.report') }}"
                                    wire:navigate
                                    size="sm"
                                    variant="ghost"
                                    class="flex-1 font-secondary justify-center"
                                    icon="exclamation-triangle"
                                >
                                    Report lost
                                </flux:button>
                            @endif
                        </div>
                    </div>
                </flux:card>

            </div>
        </div>

        {{-- Recent activity – standard card list, zone header matches the dashboard --}}
        <div>
            <div class="flex items-center gap-2.5 text-light-txt-primary dark:text-dark-txt-primary mb-3">
                <span class="w-1 h-[1.1rem] rounded-sm bg-primary dark:bg-dark-txt-primary"></span>
                <span class="font-secondary text-nav-label font-bold uppercase tracking-widest">Recent Activity</span>
            </div>

            <div class="space-y-2 max-h-96 overflow-y-auto pr-1">
                @forelse ($this->recentActivity as $transaction)
                    @php
                        $isCredit = $transaction->transaction_type === 'top_up' || $transaction->amount > 0;
                    @endphp
                    <flux:card size="sm" class="flex items-center gap-3 justify-between !p-3 dark:bg-dark-secondary dark:border-dark-bd-default">
                        <div>
                            @if ($transaction->status !== 'failed')
                                <flux:icon name="check" class="w-4 h-4 text-success dark:text-dark-success" />
                            @else
                                <flux:icon name="exclamation-triangle" class="w-4 h-4 text-danger dark:text-dark-danger" />
                            @endif
                        </div>
                        <div class="flex-1 min-w-0">
                            <x-text class="text-sm font-medium truncate text-light-txt-body dark:text-dark-txt-primary">
                                {{ $transaction->message ?? ucfirst(str_replace('_', ' ', $transaction->transaction_type)) }}
                            </x-text>
                            <x-text class="text-[11px] text-light-txt-muted dark:text-dark-txt-muted mt-0.5">
                                {{ $transaction->location }} · {{ $transaction->transaction_time?->diffForHumans() }}
                            </x-text>
                        </div>
                        @if ($transaction->status !== 'failed')
                            <x-text size="sm" class="font-medium tabular-nums text-light-txt-body dark:text-dark-txt-primary">
                                - ₱{{ number_format(abs($transaction->amount), 2) }}
                            </x-text>
                        @endif
                    </flux:card>
                @empty
                    <flux:card class="px-6 py-10 text-center dark:bg-dark-secondary dark:border-dark-bd-default">
                        <flux:icon name="clock" class="w-8 h-8 text-light-txt-muted dark:text-dark-txt-muted mx-auto mb-2" />
                        <x-text class="text-sm text-light-txt-muted dark:text-dark-txt-muted">No recent activity yet.</x-text>
                        <x-text class="text-xs text-light-txt-muted dark:text-dark-txt-muted mt-1">Your card transactions will appear here.</x-text>
                    </flux:card>
                @endforelse
            </div>
        </div>

    @else
        {{-- No card state – matches queue empty state styling --}}
        <flux:card class="px-6 py-14 text-center dark:bg-dark-secondary dark:border-dark-bd-default">
            <flux:icon name="credit-card" class="w-10 h-10 text-light-txt-muted dark:text-dark-txt-muted mx-auto mb-3" />
            <p class="text-sm text-light-txt-muted dark:text-dark-txt-muted">No card linked to your account yet.</p>
            <p class="text-xs text-light-txt-muted dark:text-dark-txt-muted mt-1">Visit the terminal to get your RFID card issued.</p>
        </flux:card>
    @endif

    {{-- ─── Top‑up Modal – standard modal structure ────────────────────── --}}
    <flux:modal
        name="top-up-modal"
        :closable="false"
        class="w-[calc(100%-2rem)] sm:max-w-lg md:max-w-2xl mx-auto rounded-xl overflow-hidden"
    >
        <form wire:submit="proceedToPayment" class="flex flex-col p-4 sm:p-6 !pr-4 sm:!pr-6 space-y-5 overflow-y-auto max-h-[70vh]">
            <!-- Header -->
            <div class="flex items-start justify-between">
                <div>
                    <flux:heading size="xl" class="!font-primary !font-bold text-light-txt-primary dark:text-dark-txt-primary">
                        Top Up Balance
                    </flux:heading>
                    <flux:text class="mt-1 font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">
                        Enter the amount you want to load into your card.
                    </flux:text>
                </div>
                <flux:modal.close>
                    <button type="button" class="p-1 rounded-full hover:bg-light-subtle dark:hover:bg-dark-subtle text-light-txt-muted dark:text-dark-txt-muted -mt-1">
                        <flux:icon name="x-mark" class="w-5 h-5" />
                    </button>
                </flux:modal.close>
            </div>

            <!-- Fields -->
            <flux:field>
                <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">Amount (PHP)</flux:label>

                <div class="flex gap-2 mt-1.5 mb-3">
                    @foreach ([100, 200, 500, 1000] as $preset)
                        <button
                            type="button"
                            wire:click="$set('amount', {{ $preset }})"
                            class="flex-1 rounded-lg border px-2 py-2 font-secondary text-sm font-medium transition text-center
                                {{ (int) $amount === $preset
                                    ? 'bg-primary text-white border-primary'
                                    : 'bg-transparent text-light-txt-body dark:text-dark-txt-body border-light-bd-default dark:border-dark-bd-default hover:bg-light-subtle dark:hover:bg-dark-subtle' }}"
                        >
                            ₱{{ $preset }}
                        </button>
                    @endforeach
                </div>

                <flux:input
                    wire:model="amount"
                    type="number"
                    min="1"
                    max="10000"
                    size="sm"
                    placeholder="Enter amount (Min: ₱50)"
                    class="font-secondary text-table-row bg-light-primary dark:bg-dark-surface text-light-txt-body dark:text-dark-txt-primary border-light-bd-default dark:border-dark-bd-default placeholder:text-light-txt-muted dark:placeholder:text-dark-txt-muted"
                />
                <flux:error name="amount" class="font-secondary text-helper text-danger dark:text-dark-danger mt-1" />
                @error('payment_error')
                    <span class="font-secondary text-helper text-danger dark:text-dark-danger mt-1">{{ $message }}</span>
                @enderror
            </flux:field>

            <!-- Footer -->
            <div class="flex flex-col-reverse sm:flex-row justify-end items-stretch sm:items-center gap-2 pt-2 border-t border-light-bd-default dark:border-dark-bd-default">
                <flux:modal.close class="w-full sm:w-auto">
                    <flux:button type="button" variant="ghost" class="w-full sm:w-auto justify-center font-secondary">
                        Cancel
                    </flux:button>
                </flux:modal.close>
                <flux:button
                    type="submit"
                    variant="primary"
                    class="font-secondary w-full sm:w-auto justify-center"
                    wire:loading.attr="disabled"
                    wire:target="proceedToPayment"
                >
                    <span wire:loading.remove wire:target="proceedToPayment">Proceed to Pay</span>
                    <span wire:loading wire:target="proceedToPayment">Processing…</span>
                </flux:button>
            </div>
        </form>
    </flux:modal>

</div>
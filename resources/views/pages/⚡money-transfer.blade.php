<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use App\Models\Card;
use App\Models\CardTransaction;
use App\Models\Notification;
use App\Models\UserNotification;
use App\Events\NotificationEvent;
use App\Services\OperatorDisbursementService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Flux\Flux;

new class extends Component
{
    public string $withdrawAmount = '';
    public string $provider = 'instapay'; // defaults to instant
    public string $institutionCategory = 'ewallet';
    public string $selectedBic = '';
    public string $accountNumber = '';
    public string $accountName = '';

    public function setInstitutionCategory(string $category)
    {
        $this->institutionCategory = $category;
        $this->selectedBic = '';
    }

    #[Computed]
    public function userCard(): ?Card
    {
        return Card::where('user_id', auth()->id())->first();
    }

    #[Computed]
    public function currentBalance(): float
    {
        if (auth()->user()->role === 'admin') {
            // Admin balance is digital queuing fees collected minus previous admin withdrawals
            $totalQueuingEarnings = (float) CardTransaction::whereIn('transaction_type', ['queueing_fee', 'operator_payment'])
                ->where('status', 'success')
                ->sum('amount');

            $totalWithdrawn = (float) CardTransaction::where('transaction_type', 'admin_withdrawal')
                ->whereIn('status', ['pending', 'success'])
                ->sum('amount');

            return max(0.0, $totalQueuingEarnings - $totalWithdrawn);
        }

        return (float) ($this->userCard->balance ?? 0.0);
    }

    #[Computed]
    public function receivingInstitutions()
    {
        return \Illuminate\Support\Facades\Cache::remember(
            "paymongo:receiving_institutions:{$this->provider}",
            now()->addHours(6),
            fn () => app(\App\Services\PaymongoDisbursementService::class)->listReceivingInstitutions($this->provider)
        );
    }

    #[Computed]
    public function categorizedInstitutions()
    {
        $ewalletNames = collect(config('paymongo.ewallets', []))->map(fn ($n) => strtolower($n));

        return collect($this->receivingInstitutions)->filter(function ($institution) use ($ewalletNames) {
            $name = strtolower($institution['attributes']['name'] ?? '');
            $isEwallet = $ewalletNames->contains($name);

            return $this->institutionCategory === 'ewallet' ? $isEwallet : ! $isEwallet;
        })->values();
    }

    #[Computed]
    public function fee(): float
    {
        return $this->provider === 'instapay' ? 10.00 : 0.00;
    }

    #[Computed]
    public function amountAsFloat(): float
    {
        return is_numeric($this->withdrawAmount) ? (float) $this->withdrawAmount : 0.0;
    }

    #[Computed]
    public function totalDeduction(): float
    {
        return $this->amountAsFloat + $this->fee;
    }

    #[Computed]
    public function balanceAfter(): float
    {
        return max(0.0, $this->currentBalance - $this->totalDeduction);
    }

    #[Computed]
    public function hasSufficientBalance(): bool
    {
        return $this->amountAsFloat > 0 && $this->totalDeduction <= $this->currentBalance;
    }

    #[Computed]
    public function selectedInstitutionName(): ?string
    {
        return collect($this->receivingInstitutions)
            ->first(fn ($i) => ($i['attributes']['provider_code'] ?? null) === $this->selectedBic)['attributes']['name'] ?? null;
    }

    public function submitWithdrawal()
    {
        $validated = $this->validate([
            'withdrawAmount' => 'required|numeric|min:1',
            'provider'       => 'required|in:instapay,pesonet',
            'selectedBic'    => 'required|string',
            'accountNumber'  => 'required|string',
            'accountName'    => 'required|string',
        ]);

        $isAdmin = auth()->user()->role === 'admin';
        $card = $this->userCard;

        if (!$isAdmin && !$card) {
            $this->addError('withdrawAmount', 'No card linked to your account.');
            Flux::toast(variant: 'danger', duration: 4000, heading: 'No card linked.', text: 'No card is linked to your account.');
            return;
        }

        if (!$this->hasSufficientBalance) {
            $this->addError('withdrawAmount', 'Insufficient balance to cover this withdrawal plus the transfer fee.');
            Flux::toast(variant: 'warning', duration: 4000, heading: 'Insufficient balance.', text: 'Your balance can\'t cover this withdrawal plus the transfer fee.');
            return;
        }

        $totalDeduction = $this->totalDeduction;
        $balanceBefore = $this->currentBalance;
        $institutionName = $this->selectedInstitutionName;

        try {
            $succeeded = DB::transaction(function () use ($card, $validated, $totalDeduction, $balanceBefore, $isAdmin, $institutionName) {
                // Deduct card balance only if operator
                if (!$isAdmin && $card) {
                    $card->decrement('balance', $totalDeduction);
                    $card->refresh();
                }

                $result = app(OperatorDisbursementService::class)->createWithdrawal([
                    'provider'       => $validated['provider'],
                    'amount'         => $validated['withdrawAmount'],
                    'account_number' => $validated['accountNumber'],
                    'account_name'   => $validated['accountName'],
                    'bic'            => $validated['selectedBic'],
                    'operator_id'    => auth()->id(),
                ]);

                $transaction = CardTransaction::create([
                    'card_id'          => $isAdmin ? null : $card->id,
                    'processed_by'     => auth()->id(),
                    'transaction_type' => $isAdmin ? 'admin_withdrawal' : 'withdrawal',
                    'reference_no'     => $result['reference_number'],
                    'amount'           => $totalDeduction,
                    'balance_before'   => $balanceBefore,
                    'balance_after'    => $balanceBefore - $totalDeduction,
                    'status'           => $result['successful'] ? 'pending' : 'failed',
                    'transaction_time' => now(),
                    'source'           => $isAdmin ? 'admin_withdraw_page' : 'operator_withdraw_page',
                    'message'          => "Withdrawal via {$validated['provider']} to {$institutionName} ({$validated['accountNumber']})",
                    'metadata'         => (array) $result['response'],
                ]);

                if (!$result['successful'] && !$isAdmin && $card) {
                    $card->increment('balance', $totalDeduction);
                }

                if ($result['successful']) {
                    // Persistent, in-app notification so the withdrawal shows up
                    // in the bell/notification center, not just as a toast.
                    $notification = Notification::create([
                        'type'    => 'Withdrawal',
                        'title'   => 'Withdrawal submitted',
                        'message' => "Your ₱" . number_format($totalDeduction, 2) . " withdrawal to {$institutionName} is being processed.",
                        'metadata' => [
                            'amount'       => $totalDeduction,
                            'reference_no' => $transaction->reference_no,
                            'provider'     => $validated['provider'],
                        ],
                    ]);

                    UserNotification::create([
                        'notification_id' => $notification->id,
                        'user_id'         => auth()->id(),
                    ]);
                }

                return $result['successful'];
            });
        } catch (\Exception $e) {
            Log::error('Withdrawal failed', ['error' => $e->getMessage(), 'user_id' => auth()->id()]);
            $this->addError('withdrawAmount', 'Withdrawal request failed. Please check your details and try again.');
            Flux::toast(variant: 'danger', duration: 4000, heading: 'Withdrawal failed.', text: 'Something went wrong while processing this withdrawal. Please try again.');
            return;
        }

        if (!$succeeded) {
            $this->addError('withdrawAmount', 'Withdrawal request failed. Please check your details and try again.');
            Flux::toast(variant: 'danger', duration: 4000, heading: 'Withdrawal failed.', text: 'Please check your details and try again.');
            return;
        }

        try {
            broadcast(new NotificationEvent());
        } catch (\Exception $e) {
            // The withdrawal already succeeded above — a broadcast/websocket
            // hiccup (e.g. Reverb not running) should only cost real-time
            // UI refresh, not the transaction itself.
            Log::warning('Withdrawal succeeded but notification broadcast failed', ['error' => $e->getMessage()]);
        }

        Flux::toast(
            variant: 'success',
            duration: 4000,
            heading: 'Withdrawal submitted!',
            text: "₱" . number_format($totalDeduction, 2) . " is on its way to {$institutionName}.",
        );

        session()->flash('withdrawal_submitted', true);

        $redirectRoute = $isAdmin ? route('admin.dashboard') : route('operator.dashboard');
        $this->redirect($redirectRoute, navigate: true);
    }

    public function render()
    {
        $role = auth()->user()->role;
        return $this->view()->layout('layouts.' . $role . '-layout');
    }
}; ?>

<div>
    <x-page-header
        description="Transfer your card balance to your linked e-wallet or bank account."
        class="mb-3"
    >
    </x-page-header>

    <div class="max-w-4xl mx-auto">
    <div class="mb-5">
        <flux:breadcrumbs>
            <flux:breadcrumbs.item :href="auth()->user()->role === 'admin' ? route('admin.dashboard') : route('operator.dashboard')" wire:navigate>Back to Dashboard</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>Withdraw</flux:breadcrumbs.item>
        </flux:breadcrumbs>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-5 gap-4 items-start">

        {{-- ===================== FORM ===================== --}}
        <form wire:submit="submitWithdrawal" class="lg:col-span-3 space-y-5">
            <flux:card class="p-5 space-y-5">

                <flux:input wire:model.live="withdrawAmount" label="Amount to withdraw" type="number" step="0.01" placeholder="₱ 0.00"/>

                <div>
                    <flux:text size="sm" class="mb-1.5">Send to</flux:text>
                    <div class="grid grid-cols-2 gap-2">
                        <flux:button type="button" wire:click="setInstitutionCategory('ewallet')"
                            variant="{{ $institutionCategory === 'ewallet' ? 'primary' : 'outline' }}" class="w-full">
                             E-Wallet
                        </flux:button>
                        <flux:button type="button" wire:click="setInstitutionCategory('bank')"
                            variant="{{ $institutionCategory === 'bank' ? 'primary' : 'outline' }}" class="w-full">
                             Bank
                        </flux:button>
                    </div>
                </div>

                <flux:select wire:model.live="selectedBic" label="{{ $institutionCategory === 'ewallet' ? 'Choose your e-wallet' : 'Choose your bank' }}">
                    <flux:select.option value="">Select one</flux:select.option>
                    @foreach ($this->categorizedInstitutions as $institution)
                        <flux:select.option value="{{ $institution['attributes']['provider_code'] }}">
                            {{ $institution['attributes']['name'] }}
                        </flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input wire:model="accountName" label="Account name" placeholder="Exact name registered on the account" />
                <flux:input wire:model="accountNumber" label="Account / mobile number" />

                <flux:button type="submit" variant="primary" class="w-full" :disabled="!$this->hasSufficientBalance">
                    Confirm Withdrawal
                </flux:button>
            </flux:card>
        </form>

        {{-- ===================== SUMMARY ===================== --}}
        <div class="lg:col-span-2 lg:sticky lg:top-4">
            <flux:card class="p-5 space-y-3">
                <flux:heading size="lg">Summary</flux:heading>

                <div class="flex justify-between text-sm">
                    <span class="text-light-txt-muted dark:text-dark-txt-muted">Your current balance</span>
                    <span class="font-semibold">₱{{ number_format($this->currentBalance, 2) }}</span>
                </div>

                <hr class="border-light-bd-default dark:border-dark-bd-default">

                <div class="flex justify-between text-sm">
                    <span class="text-light-txt-muted dark:text-dark-txt-muted">Amount to withdraw</span>
                    <span>₱{{ number_format($this->amountAsFloat, 2) }}</span>
                </div>

                <div class="flex justify-between text-sm">
                    <span class="text-light-txt-muted dark:text-dark-txt-muted">
                        Transfer fee
                        @if ($provider === 'instapay')
                            <span class="text-xs">(InstaPay)</span>
                        @else
                            <span class="text-xs">(PESONet — free)</span>
                        @endif
                    </span>
                    <span class="{{ $this->fee > 0 ? 'text-warning' : '' }}">
                        {{ $this->fee > 0 ? '+ ₱' . number_format($this->fee, 2) : '₱0.00' }}
                    </span>
                </div>

                <hr class="border-light-bd-default dark:border-dark-bd-default">

                <div class="flex justify-between font-semibold">
                    <span>Total deducted from balance</span>
                    <span>₱{{ number_format($this->totalDeduction, 2) }}</span>
                </div>

                <div class="flex justify-between text-sm">
                    <span class="text-light-txt-muted dark:text-dark-txt-muted">Balance after this withdrawal</span>
                    <span class="{{ $this->hasSufficientBalance ? '' : 'text-danger font-semibold' }}">
                        ₱{{ number_format($this->balanceAfter, 2) }}
                    </span>
                </div>

                @if (!$this->hasSufficientBalance && $this->amountAsFloat > 0)
                    <div class="bg-danger/10 text-danger text-sm rounded-lg p-3">
                        You don't have enough balance to cover this withdrawal plus the ₱{{ number_format($this->fee, 2) }} fee.
                    </div>
                @endif

                @if ($selectedBic && $this->selectedInstitutionName)
                    <div class="text-sm text-light-txt-muted dark:text-dark-txt-muted">
                        Sending to: <span class="font-medium text-light-txt-primary dark:text-dark-txt-primary">{{ $this->selectedInstitutionName }}</span>
                    </div>
                @endif
            </flux:card>
        </div>
    </div>
    </div>
</div>
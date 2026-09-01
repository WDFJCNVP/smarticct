<?php

use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use App\Models\Card;
use App\Services\CheckoutSessionService;
use App\Models\CardReport;
use App\Models\CardTransaction;

new class extends Component
{
    use WithFileUploads;

    public $amount = 100; // Default preset amount

    // Report lost modal fields
    public string $reason = '';
    public string $description = '';
    public $valid_id;

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
        // 1. Validate the input locally first
        $this->validate([
            'amount' => 'required|numeric|min:1|max:10000',
        ]);

        $card = $this->userCard;
        if (!$card) {
            $this->addError('payment_error', 'No card linked to your account.');
            return;
        }

        try {
            // 2. Generate the PayMongo checkout URL securely from the server
            $checkoutUrl = $checkoutSession->createCheckoutSession(auth()->user(), $card, (float) $this->amount);

            // 3. Redirect the user to PayMongo's secure hosted page
            return redirect()->away($checkoutUrl);
        }
        catch (\Exception $e) {
            $this->addError('payment_error', $e->getMessage());
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

    #[Computed]
    public function cardReports()
    {
        if (!$this->userCard) return collect();
        return CardReport::with('newCard')
            ->where('user_id', auth()->id())
            ->latest()
            ->get();
    }

    public function removeValidId(): void
    {
        $this->reset('valid_id');
    }

    public function submitLostReport(): void
    {
        $this->validate([
            'reason'      => 'required|in:lost,damaged,other',
            'description' => 'required|string|min:10|max:1000',
            'valid_id'    => 'required|image|max:5120',
        ], [
            'reason.required'      => 'Please select a reason.',
            'description.min'      => 'Please describe what happened (at least 10 characters).',
            'valid_id.required'    => 'Please upload a photo of your valid ID.',
            'valid_id.image'       => 'The file must be an image (JPG, PNG, etc.).',
            'valid_id.max'         => 'Image must not exceed 5MB.',
        ]);

        if ($this->existingPendingReport) {
            Flux::toast(variant: 'warning', heading: 'Report already submitted.', text: 'You already have a pending report for this card.');
            return;
        }

        $path = $this->valid_id->store('card-reports/' . auth()->id(), 'public');

        CardReport::create([
            'user_id'       => auth()->id(),
            'card_id'       => $this->userCard->id,
            'reason'        => $this->reason,
            'description'   => $this->description,
            'valid_id_path' => $path,
            'status'        => 'pending',
        ]);

        $this->reset('reason', 'description', 'valid_id');
        $this->dispatch('close-report-modal');

        Flux::toast(
            variant: 'success',
            heading: 'Report submitted.',
            text: 'An admin will review your request. Visit the terminal to complete the card replacement.',
        );
    }

    public function render()
    {
        $role = auth()->user()->role;
        return $this->view()->layout('layouts.' . $role . '-layout');
    }
};
?>

<div>
    {{-- Page heading – matches queue pages --}}
    <div class="flex items-start justify-between gap-4 mb-6">
        <div>
            <x-heading
                size="xl"
                class="!font-primary !font-bold !text-light-txt-primary dark:!text-dark-txt-primary"
                style="font-size: var(--text-page-title)"
            >
                My Card
            </x-heading>
            <x-text variant="subtle" class="!font-secondary mt-1 block" style="font-size: var(--text-helper)">
                Manage your smart card and view transaction history
            </x-text>
        </div>

        {{-- Optional breadcrumb or action – none in original --}}
    </div>

    @if ($this->userCard)
        <div class="lg:sticky lg:top-6 lg:z-10 mb-5">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

                <flux:card class="bg-black text-white !border-0">
                    <div class="flex justify-between items-start mb-7">
                        <div>
                            <x-text size="sm" class="text-white opacity-70">smarticct</x-text>
                            <x-text size="lg" class="text-white font-semibold">{{ auth()->user()->role }} card</x-text>
                        </div>
                        <flux:icon name="wifi" class="w-5 h-5 opacity-70 rotate-90 text-white" />
                    </div>

                    <x-text size="xl" class="font-mono text-white tracking-widest mb-4">{{ $this->userCard->card_number }}</x-text>

                    <div class="flex justify-between items-end">
                        <div>
                            <x-text class="text-[10px] opacity-60 text-white uppercase">cardholder</x-text>
                            <x-text class="text-sm font-medium text-white">{{ $this->userCard->user->name }}</x-text>
                        </div>
                        <div class="text-right">
                            <x-text class="text-[10px] opacity-60 text-white uppercase">type</x-text>
                            <x-text class="text-sm font-medium capitalize text-white">{{ auth()->user()->role }}</x-text>
                        </div>
                    </div>
                </flux:card> 


                {{-- Total Balance --}}
                <flux:card x-data="{ showBalance: false }" class="!p-4">
                    <div class="flex justify-between items-center mb-1">
                        <x-text class="text-xs text-zinc-500 dark:text-zinc-400">Total balance</x-text>

                        <button
                            type="button"
                            @click="showBalance = !showBalance"
                            class="text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200 transition-colors focus:outline-none"
                            title="Toggle Balance Visibility"
                        >
                            <flux:icon name="eye" class="w-6 h-6 cursor-pointer" x-show="!showBalance" />
                            <flux:icon name="eye-slash" class="w-6 h-6 cursor-pointer" x-show="showBalance" x-cloak />
                        </button>
                    </div>

                    <x-text variant="strong" class="text-3xl font-medium mb-3.5 block h-[36px] flex items-center">
                        <span x-show="showBalance">
                            ₱{{ number_format($this->userCard->balance, 2) }}
                        </span>
                        <span x-show="!showBalance" x-cloak class="tracking-wider">
                            ₱••••••
                        </span>
                    </x-text>

                    <div class="flex gap-2">
                        <flux:button x-on:click="$flux.modal('top-up-modal').show()" size="sm" class="flex-1" icon="plus" variant="primary">
                            Top up
                        </flux:button>

                        {{-- Report Lost button --}}
                        @if ($this->existingPendingReport)
                            <flux:button size="sm" variant="ghost" class="flex-1" icon="clock" disabled>
                                Report pending
                            </flux:button>
                        @else
                            <flux:modal.trigger name="report-lost-modal">
                                <flux:button size="sm" variant="ghost" class="flex-1" icon="exclamation-triangle">
                                    Report lost
                                </flux:button>
                            </flux:modal.trigger>
                        @endif
                    </div>

                    {{-- Pending report notice --}}
                    @if ($this->existingPendingReport)
                        <div class="mt-3 rounded-lg bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 px-3 py-2 flex items-center gap-2">
                            <flux:icon name="clock" class="w-4 h-4 text-yellow-600 dark:text-yellow-400 shrink-0" />
                            <x-text class="text-xs text-yellow-700 dark:text-yellow-400">
                                You have a pending lost card report. Visit the terminal to complete the replacement.
                            </x-text>
                        </div>
                    @endif
                </flux:card>

            </div>
        </div>

        {{-- Recent activity – update table to match queue table style --}}
        <div>
            <div class="flex justify-between items-center mb-2.5">
                <x-text class="text-sm font-medium">Recent activity</x-text>
            </div>

            <div class="space-y-2 max-h-96 overflow-y-auto pr-1">
                @forelse ($this->recentActivity as $transaction)
                    @php
                        $isCredit = $transaction->transaction_type === 'top_up' || $transaction->amount > 0;
                    @endphp
                    <flux:card size="sm" class="flex items-center gap-3 justify-between !p-3">
                        <div>
                            @if ($transaction->status !== 'failed')
                                <flux:icon name="check" class="w-4 h-4 text-green-600 dark:text-green-400" />
                            @else
                                <flux:icon name="exclamation-triangle" class="w-4 h-4 text-red-600 dark:text-red-400" />
                            @endif
                        </div>
                        <div class="flex-1 min-w-0">
                            <x-text class="text-sm font-medium truncate">
                                {{ $transaction->message ?? ucfirst(str_replace('_', ' ', $transaction->transaction_type)) }}
                            </x-text>
                            <x-text class="text-[11px] text-zinc-400 mt-0.5">
                                {{ $transaction->location }} · {{ $transaction->transaction_time?->diffForHumans() }}
                            </x-text>
                        </div>
                        @if ($transaction->status !== 'failed')
                            <x-text size="sm" class="font-medium tabular-nums">
                                - ₱{{ number_format(abs($transaction->amount), 2) }}
                            </x-text>
                        @endif
                    </flux:card>
                @empty
                    <x-text class="text-xs text-zinc-400 text-center py-6">No activity yet.</x-text>
                @endforelse
            </div>
        </div>

        @if ($this->cardReports->isNotEmpty())
            <div class="mt-8">
                <div class="flex justify-between items-center mb-2.5">
                    <x-text class="text-sm font-medium">Card report history</x-text>
                </div>

                <div class="space-y-2">
                    @foreach ($this->cardReports as $report)
                        <flux:card size="sm" class="!p-3">
                            <div class="flex items-start justify-between gap-3">

                                <div class="flex items-center gap-3 flex-1 min-w-0">
                                    {{-- Status icon --}}
                                    @if ($report->status === 'pending')
                                        <div class="shrink-0 w-8 h-8 rounded-full bg-yellow-100 dark:bg-yellow-900/30 flex items-center justify-center">
                                            <flux:icon name="clock" class="w-4 h-4 text-yellow-600 dark:text-yellow-400" />
                                        </div>
                                    @elseif ($report->status === 'approved')
                                        <div class="shrink-0 w-8 h-8 rounded-full bg-green-100 dark:bg-green-900/30 flex items-center justify-center">
                                            <flux:icon name="check-circle" class="w-4 h-4 text-green-600 dark:text-green-400" />
                                        </div>
                                    @else
                                        <div class="shrink-0 w-8 h-8 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center">
                                            <flux:icon name="x-circle" class="w-4 h-4 text-red-600 dark:text-red-400" />
                                        </div>
                                    @endif

                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <x-text class="text-sm font-medium">
                                                {{ ucfirst($report->reason) }} card report
                                            </x-text>
                                            @if ($report->status === 'pending')
                                                <flux:badge color="yellow" size="sm">Pending review</flux:badge>
                                            @elseif ($report->status === 'approved')
                                                <flux:badge color="green" size="sm">Approved</flux:badge>
                                            @else
                                                <flux:badge color="red" size="sm">Rejected</flux:badge>
                                            @endif
                                        </div>

                                        <x-text class="text-[11px] text-zinc-400 mt-0.5">
                                            Submitted {{ $report->created_at->format('M d, Y') }}
                                        </x-text>

                                        @if ($report->status === 'approved' && $report->newCard)
                                            <x-text class="text-xs text-green-600 dark:text-green-400 mt-1">
                                                New card issued: <span class="font-mono">**** {{ substr($report->newCard->card_number, -4) }}</span>
                                                · {{ $report->approved_at?->format('M d, Y') }}
                                            </x-text>
                                        @endif

                                        @if ($report->status === 'rejected' && $report->rejection_reason)
                                            <x-text class="text-xs text-red-500 dark:text-red-400 mt-1">
                                                Reason: {{ $report->rejection_reason }}
                                            </x-text>
                                        @endif

                                        @if ($report->status === 'pending')
                                            <x-text class="text-xs text-zinc-400 mt-1">
                                                Bring a blank RFID card to the terminal to complete the replacement.
                                            </x-text>
                                        @endif
                                    </div>
                                </div>

                            </div>
                        </flux:card>
                    @endforeach
                </div>
            </div>
        @endif

    @else
        {{-- No card state – matches queue empty state styling --}}
        <flux:card class="px-6 py-14 text-center dark:bg-dark-secondary dark:border-dark-bd-default">
            <flux:icon name="credit-card" class="w-10 h-10 text-zinc-300 mx-auto mb-3" />
            <p class="text-sm text-zinc-500">No card linked to your account yet.</p>
            <p class="text-xs text-zinc-400 mt-1">Visit the terminal to get your RFID card issued.</p>
        </flux:card>
    @endif


    <flux:modal name="top-up-modal" class="min-w-96">
        <form wire:submit="proceedToPayment" class="space-y-6">
            <div>
                <flux:heading size="lg">Top Up Balance</flux:heading>
                <x-text class="text-sm text-zinc-500 mt-1">Enter the amount you want to load into your card.</x-text>
            </div>

            <flux:field>
                <flux:label>Amount (PHP)</flux:label>
                <flux:input 
                    wire:model="amount" 
                    type="number" 
                    min="1" 
                    max="10000" 
                    placeholder="Enter amount (Min: ₱50)" 
                />
                <flux:error name="amount" />
                @error('payment_error') 
                    <span class="text-sm text-red-500 mt-1">{{ $message }}</span> 
                @enderror
            </flux:field>

            <div class="flex gap-2">
                <flux:button type="button" x-on:click="$flux.modal('top-up-modal').close()" variant="ghost" class="flex-1">
                    Cancel
                </flux:button>
                <flux:button type="submit" variant="primary" class="flex-1" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="proceedToPayment">Proceed to Pay</span>
                    <span wire:loading wire:target="proceedToPayment">Processing...</span>
                </flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- ─── Report Lost Modal ──────────────────────────────────────────────── --}}
    <flux:modal
    name="report-lost-modal"
    :closable="false"
    class="w-full max-w-[95vw] sm:max-w-lg md:max-w-2xl mx-auto max-h-[80vh] sm:max-h-[90vh] overflow-hidden rounded-xl"
    x-on:close-report-modal.window="$flux.modal('report-lost-modal').close()"
    >
        <div class="flex flex-col max-h-[calc(80vh-2rem)] sm:max-h-[calc(90vh-2rem)] overflow-y-auto overscroll-contain p-4 sm:p-6 !pr-4 sm:!pr-6 space-y-5">

            {{-- Header with close button --}}
            <div class="flex items-start justify-between">
                <div>
                    <flux:heading size="xl" class="!font-primary !font-bold text-light-txt-primary dark:text-dark-txt-primary">
                        Report Lost / Damaged Card
                    </flux:heading>
                    <flux:text class="mt-1 font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">
                        Submit a report and bring a new blank RFID card to the terminal for replacement.
                    </flux:text>
                </div>

                <flux:modal.close>
                    <button type="button" class="p-1 rounded-full hover:bg-light-subtle dark:hover:bg-dark-subtle text-light-txt-muted dark:text-dark-txt-muted -mt-1">
                        <flux:icon name="x-mark" class="w-5 h-5" />
                    </button>
                </flux:modal.close>
            </div>

            {{-- Form fields (unchanged) --}}
            <flux:field>
                <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">
                    Reason
                </flux:label>
                <flux:select wire:model="reason" placeholder="Select a reason…" class="mt-1">
                    <flux:select.option value="lost">Lost</flux:select.option>
                    <flux:select.option value="damaged">Damaged</flux:select.option>
                    <flux:select.option value="other">Other</flux:select.option>
                </flux:select>
                <flux:error name="reason" />
            </flux:field>

            <flux:field>
                <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">
                    Description
                </flux:label>
                <flux:textarea
                    wire:model="description"
                    placeholder="Describe what happened to your card…"
                    rows="3"
                    class="mt-1"
                />
                <flux:error name="description" />
            </flux:field>

            <flux:field>
                <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">
                    Valid ID photo
                </flux:label>

                @if (!$valid_id)
                    <label
                        for="report_valid_id"
                        class="flex flex-col items-center justify-center gap-1 rounded-lg border-2 border-dashed border-zinc-300 dark:border-zinc-600 bg-zinc-50 dark:bg-zinc-800 px-4 py-6 cursor-pointer hover:border-zinc-400 transition-colors mt-1"
                    >
                        <flux:icon name="arrow-up-tray" class="w-6 h-6 text-zinc-400" />
                        <span class="text-sm text-zinc-600 dark:text-zinc-300">Upload a photo of your ID</span>
                        <span class="text-xs text-zinc-400">JPG or PNG, max 5MB</span>
                        <input
                            id="report_valid_id"
                            type="file"
                            accept="image/*"
                            wire:model="valid_id"
                            class="hidden"
                        />
                    </label>
                @endif

                <div wire:loading wire:target="valid_id" class="text-xs text-zinc-400 mt-1">Uploading…</div>

                @if ($valid_id)
                    <div class="flex items-center gap-3 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 px-3 py-2 mt-1">
                        <img
                            src="{{ $valid_id->temporaryUrl() }}"
                            alt="Valid ID preview"
                            class="size-10 rounded-md object-cover shrink-0"
                        />
                        <span class="text-sm text-zinc-700 dark:text-zinc-300 truncate flex-1">
                            {{ $valid_id->getClientOriginalName() }}
                        </span>
                        <flux:icon name="check-circle" class="size-4 text-green-600 shrink-0" />
                        <button type="button" wire:click="removeValidId" class="shrink-0 text-zinc-400 hover:text-zinc-600">
                            <flux:icon name="x-mark" class="size-4" />
                        </button>
                    </div>
                @endif

                <flux:error name="valid_id" />
            </flux:field>

            {{-- Actions – consistent with create-post modal footer --}}
            <div class="flex flex-col-reverse sm:flex-row justify-end items-stretch sm:items-center gap-2 pt-2 border-t border-light-bd-default     dark:border-dark-bd-default">
                <flux:modal.close class="w-full sm:w-auto">
                    <flux:button variant="ghost" class="w-full sm:w-auto justify-center !font-secondary">
                        Cancel
                    </flux:button>
                </flux:modal.close>
                <flux:button
                    variant="primary"
                    wire:click="submitLostReport"
                    wire:loading.attr="disabled"
                    wire:target="submitLostReport"
                    class="w-full sm:w-auto justify-center !font-secondary"
                >
                    <span wire:loading.remove wire:target="submitLostReport">Submit Report</span>
                    <span wire:loading wire:target="submitLostReport">Submitting…</span>
                </flux:button>
            </div>

        </div>
    </flux:modal>

</div>
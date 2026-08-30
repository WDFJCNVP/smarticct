<?php

use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use App\Models\Card;
use App\Services\CheckoutSessionService;
use App\Models\CardReport;
use Illuminate\Support\Facades\Log;

new class extends Component
{
    use WithFileUploads;

    public $amount = 100; // Default preset amount

    // Report lost modal fields
    public string $reason = '';
    public string $description = '';
    public $valid_id;

    #[Computed]
    public function userCard(): ?Card
    {
        return Card::with(['user', 'cardTransaction' => function ($query) {
            $query->latest('transaction_time')->limit(10);
        }])->where('user_id', auth()->id())->first();
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
            Flux::toast(variant: 'warning', duration: 4000, heading: 'Report already submitted.', text: 'You already have a pending report for this card.');
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
            duration: 4000,
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
    {{-- Page heading – consistent with other pages --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
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
    </div>

    @if ($this->userCard)
        {{-- Removed sticky wrapper – cards now flow naturally --}}
        <div class="mb-5">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

                {{-- Card visual – keep as is with dark mode adjustments --}}
                <flux:card class="bg-black text-white !border-0 dark:!border-0">
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

                {{-- Balance card – standard stat card layout --}}
                <flux:card x-data="{ showBalance: false }" class="p-3 sm:p-4">
                    <div class="flex items-center gap-1.5 sm:gap-2 mb-1.5">
                        <div class="flex items-center justify-center w-6 h-6 sm:w-7 sm:h-7 rounded-lg bg-primary/10 dark:bg-primary/20 shrink-0">
                            <flux:icon.credit-card class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-primary dark:text-dark-txt-primary" />
                        </div>
                        <x-text class="font-secondary text-xs sm:text-stat-label text-light-txt-muted dark:text-dark-txt-muted">
                            Available balance
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

                    <x-text class="font-primary text-stat-value font-bold text-light-txt-primary dark:text-dark-txt-primary block h-[36px] flex items-center">
                        <span x-show="showBalance">
                            ₱{{ number_format($this->userCard->balance, 2) }}
                        </span>
                        <span x-show="!showBalance" x-cloak class="tracking-wider">
                            ₱••••••
                        </span>
                    </x-text>

                    <div class="flex gap-2 mt-3.5">
                        <flux:button x-on:click="$flux.modal('top-up-modal').show()" size="sm" class="flex-1 font-secondary" icon="plus" variant="primary">
                            Top up
                        </flux:button>

                        {{-- Report Lost button --}}
                        @if ($this->existingPendingReport)
                            <flux:button size="sm" variant="ghost" class="flex-1 font-secondary" icon="clock" disabled>
                                Report pending
                            </flux:button>
                        @else
                            <flux:modal.trigger name="report-lost-modal">
                                <flux:button size="sm" variant="ghost" class="flex-1 font-secondary" icon="exclamation-triangle">
                                    Report lost
                                </flux:button>
                            </flux:modal.trigger>
                        @endif
                    </div>

                    {{-- Pending report notice --}}
                    @if ($this->existingPendingReport)
                        <div class="mt-3 rounded-lg bg-warning/10 dark:bg-dark-warning/20 border border-warning/30 dark:border-dark-warning/30 px-3 py-2 flex items-center gap-2">
                            <flux:icon name="clock" class="w-4 h-4 text-warning dark:text-dark-warning shrink-0" />
                            <x-text class="text-xs text-warning dark:text-dark-warning">
                                You have a pending lost card report. Visit the terminal to complete the replacement.
                            </x-text>
                        </div>
                    @endif
                </flux:card>

            </div>
        </div>

        {{-- Recent activity – standard card list --}}
        <div>
            <div class="flex justify-between items-center mb-2.5">
                <x-heading
                    size="lg"
                    class="!font-primary !font-bold !text-light-txt-primary dark:!text-dark-txt-primary"
                >
                    Recent activity
                </x-heading>
            </div>

            <div class="space-y-2 max-h-96 overflow-y-auto pr-1">
                @forelse ($this->userCard->cardTransaction as $transaction)
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
                    <x-text class="text-xs text-light-txt-muted dark:text-dark-txt-muted text-center py-6">No activity yet.</x-text>
                @endforelse
            </div>
        </div>

        {{-- ─── Report History ─────────────────────────────────────────────── --}}
        @if ($this->cardReports->isNotEmpty())
            <div class="mt-8">
                <div class="flex justify-between items-center mb-2.5">
                    <x-heading
                        size="lg"
                        class="!font-primary !font-bold !text-light-txt-primary dark:!text-dark-txt-primary"
                    >
                        Card report history
                    </x-heading>
                </div>

                <div class="space-y-2">
                    @foreach ($this->cardReports as $report)
                        <flux:card size="sm" class="!p-3 dark:bg-dark-secondary dark:border-dark-bd-default">
                            <div class="flex items-start justify-between gap-3">

                                <div class="flex items-center gap-3 flex-1 min-w-0">
                                    {{-- Status icon --}}
                                    @if ($report->status === 'pending')
                                        <div class="shrink-0 w-8 h-8 rounded-full bg-warning/10 dark:bg-dark-warning/20 flex items-center justify-center">
                                            <flux:icon name="clock" class="w-4 h-4 text-warning dark:text-dark-warning" />
                                        </div>
                                    @elseif ($report->status === 'approved')
                                        <div class="shrink-0 w-8 h-8 rounded-full bg-success/10 dark:bg-dark-success/20 flex items-center justify-center">
                                            <flux:icon name="check-circle" class="w-4 h-4 text-success dark:text-dark-success" />
                                        </div>
                                    @else
                                        <div class="shrink-0 w-8 h-8 rounded-full bg-danger/10 dark:bg-dark-danger/20 flex items-center justify-center">
                                            <flux:icon name="x-circle" class="w-4 h-4 text-danger dark:text-dark-danger" />
                                        </div>
                                    @endif

                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <x-text class="text-sm font-medium text-light-txt-body dark:text-dark-txt-primary">
                                                {{ ucfirst($report->reason) }} card report
                                            </x-text>
                                            @if ($report->status === 'pending')
                                                <flux:badge color="yellow" size="sm" class="font-secondary text-badge text-xs">Pending review</flux:badge>
                                            @elseif ($report->status === 'approved')
                                                <flux:badge color="green" size="sm" class="font-secondary text-badge text-xs">Approved</flux:badge>
                                            @else
                                                <flux:badge color="red" size="sm" class="font-secondary text-badge text-xs">Rejected</flux:badge>
                                            @endif
                                        </div>

                                        <x-text class="text-[11px] text-light-txt-muted dark:text-dark-txt-muted mt-0.5">
                                            Submitted {{ $report->created_at->format('M d, Y') }}
                                        </x-text>

                                        @if ($report->status === 'approved' && $report->newCard)
                                            <x-text class="text-xs text-success dark:text-dark-success mt-1">
                                                New card issued: <span class="font-mono">**** {{ substr($report->newCard->card_number, -4) }}</span>
                                                · {{ $report->approved_at?->format('M d, Y') }}
                                            </x-text>
                                        @endif

                                        @if ($report->status === 'rejected' && $report->rejection_reason)
                                            <x-text class="text-xs text-danger dark:text-dark-danger mt-1">
                                                Reason: {{ $report->rejection_reason }}
                                            </x-text>
                                        @endif

                                        @if ($report->status === 'pending')
                                            <x-text class="text-xs text-light-txt-muted dark:text-dark-txt-muted mt-1">
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

    <flux:modal
        name="report-lost-modal"
        :closable="false"
        class="w-[calc(100%-2rem)] sm:max-w-lg md:max-w-2xl mx-auto rounded-xl overflow-hidden"
        x-on:close-report-modal.window="$flux.modal('report-lost-modal').close()"
    >
        <div class="flex flex-col p-4 sm:p-6 !pr-4 sm:!pr-6 space-y-5 overflow-y-auto max-h-[70vh]">

            <!-- Header -->
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

            <!-- Fields -->
            <flux:field>
                <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">
                    Reason
                </flux:label>
                <flux:select
                    wire:model="reason"
                    placeholder="Select a reason…"
                    size="sm"
                    class="font-secondary text-table-row bg-light-primary dark:bg-dark-surface text-light-txt-body dark:text-dark-txt-primary border-light-bd-default dark:border-dark-bd-default"
                >
                    <flux:select.option value="lost">Lost</flux:select.option>
                    <flux:select.option value="damaged">Damaged</flux:select.option>
                    <flux:select.option value="other">Other</flux:select.option>
                </flux:select>
                <flux:error name="reason" class="font-secondary text-helper text-danger dark:text-dark-danger mt-1" />
            </flux:field>

            <flux:field>
                <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">
                    Description
                </flux:label>
                <flux:textarea
                    wire:model="description"
                    placeholder="Describe what happened to your card…"
                    rows="3"
                    size="sm"
                    class="font-secondary text-table-row bg-light-primary dark:bg-dark-surface text-light-txt-body dark:text-dark-txt-primary border-light-bd-default dark:border-dark-bd-default placeholder:text-light-txt-muted dark:placeholder:text-dark-txt-muted"
                />
                <flux:error name="description" class="font-secondary text-helper text-danger dark:text-dark-danger mt-1" />
            </flux:field>

            <flux:field>
                <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">
                    Valid ID photo
                </flux:label>

                @if (!$valid_id)
                    <label
                        for="report_valid_id"
                        class="flex flex-col items-center justify-center gap-1 rounded-lg border-2 border-dashed border-light-bd-default dark:border-dark-bd-default bg-light-subtle dark:bg-dark-subtle px-4 py-6 cursor-pointer hover:border-light-txt-muted dark:hover:border-dark-txt-muted transition-colors mt-1"
                    >
                        <flux:icon name="arrow-up-tray" class="w-6 h-6 text-light-txt-muted dark:text-dark-txt-muted" />
                        <span class="text-sm text-light-txt-body dark:text-dark-txt-body">Upload a photo of your ID</span>
                        <span class="text-xs text-light-txt-muted dark:text-dark-txt-muted">JPG or PNG, max 5MB</span>
                        <input
                            id="report_valid_id"
                            type="file"
                            accept="image/*"
                            wire:model="valid_id"
                            class="hidden"
                        />
                    </label>
                @endif

                <div wire:loading wire:target="valid_id" class="text-xs text-light-txt-muted dark:text-dark-txt-muted mt-1">Uploading…</div>

                @if ($valid_id)
                    <div class="flex items-center gap-3 rounded-lg border border-light-bd-default dark:border-dark-bd-default bg-light-subtle dark:bg-dark-subtle px-3 py-2 mt-1">
                        <img
                            src="{{ $valid_id->temporaryUrl() }}"
                            alt="Valid ID preview"
                            class="size-10 rounded-md object-cover shrink-0"
                        />
                        <span class="text-sm text-light-txt-body dark:text-dark-txt-primary truncate flex-1">
                            {{ $valid_id->getClientOriginalName() }}
                        </span>
                        <flux:icon name="check-circle" class="size-4 text-success dark:text-dark-success shrink-0" />
                        <button type="button" wire:click="removeValidId" class="shrink-0 text-light-txt-muted dark:text-dark-txt-muted hover:text-light-txt-body dark:hover:text-dark-txt-primary">
                            <flux:icon name="x-mark" class="size-4" />
                        </button>
                    </div>
                @endif

                <flux:error name="valid_id" class="font-secondary text-helper text-danger dark:text-dark-danger mt-1" />
            </flux:field>

            <!-- Footer -->
            <div class="flex flex-col-reverse sm:flex-row justify-end items-stretch sm:items-center gap-2 pt-2 border-t border-light-bd-default dark:border-dark-bd-default">
                <flux:modal.close class="w-full sm:w-auto">
                    <flux:button variant="ghost" class="w-full sm:w-auto justify-center font-secondary">
                        Cancel
                    </flux:button>
                </flux:modal.close>
                <flux:button
                    variant="primary"
                    wire:click="submitLostReport"
                    wire:loading.attr="disabled"
                    wire:target="submitLostReport"
                    class="font-secondary w-full sm:w-auto justify-center"
                >
                    <span wire:loading.remove wire:target="submitLostReport">Submit Report</span>
                    <span wire:loading wire:target="submitLostReport">Submitting…</span>
                </flux:button>
            </div>

        </div>
    </flux:modal>

</div>
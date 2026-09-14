<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;

use App\Models\User;
use App\Models\CardTransaction;
use App\Models\Notification;
use App\Models\UserNotification;
use App\Events\NotificationEvent;
use Illuminate\Support\Facades\DB;

new #[Layout('layouts.admin-layout')] class extends Component
{
    public $user;

    // ===================== SUSPEND / REINSTATE CARD =====================
    public bool $show_suspend_modal = false;
    public bool $show_reinstate_modal = false;
    public string $suspensionReason = '';

    public function suspendCard(): void
    {
        $this->validate([
            'suspensionReason' => 'required|string|min:5|max:500',
        ], [
            'suspensionReason.required' => 'Please provide a reason for the suspension.',
            'suspensionReason.min'      => 'Reason must be at least 5 characters.',
        ]);

        $card = $this->user->card;

        if ($card->status !== 'active') {
            $this->show_suspend_modal = false;
            Flux::toast(variant: 'warning', duration: 4000, heading: 'Card is not currently active.');
            return;
        }

        DB::transaction(function () use ($card) {
            $card->update([
                'status'            => 'suspended',
                'suspension_reason' => $this->suspensionReason,
                'suspended_at'      => now(),
                'suspended_by'      => auth()->id(),
            ]);

            $notification = Notification::create([
                'type'    => 'Card',
                'title'   => 'Card Suspended',
                'message' => "Your card has been temporarily suspended. Reason: {$this->suspensionReason} Please visit the terminal office to have this reviewed.",
            ]);

            UserNotification::create([
                'notification_id' => $notification->id,
                'user_id'         => $card->user_id,
            ]);

            broadcast(new NotificationEvent());
        });

        $this->show_suspend_modal = false;
        $this->suspensionReason = '';
        $this->user = User::with('card')->where('id', $this->user->id)->first();

        Flux::toast(variant: 'success', duration: 4000, heading: 'Card suspended.', text: 'The cardholder has been notified and can no longer transact.');
    }

    public function reinstateCard(): void
    {
        $card = $this->user->card;

        if ($card->status !== 'suspended') {
            $this->show_reinstate_modal = false;
            Flux::toast(variant: 'warning', duration: 4000, heading: 'Card is not suspended.');
            return;
        }

        DB::transaction(function () use ($card) {
            $card->update([
                'status'            => 'active',
                'suspension_reason' => null,
                'suspended_at'      => null,
                'suspended_by'      => null,
            ]);

            $notification = Notification::create([
                'type'    => 'Card',
                'title'   => 'Card Reinstated',
                'message' => 'Your card has been reviewed and reinstated. You may now use it as normal.',
            ]);

            UserNotification::create([
                'notification_id' => $notification->id,
                'user_id'         => $card->user_id,
            ]);

            broadcast(new NotificationEvent());
        });

        $this->show_reinstate_modal = false;
        $this->user = User::with('card')->where('id', $this->user->id)->first();

        Flux::toast(variant: 'success', duration: 4000, heading: 'Card reinstated.', text: 'The cardholder has been notified.');
    }

    // ===================== EXPORT MODAL =====================
    public string $exportPaper = 'legal';
    public string $exportOrientation = 'portrait';

    #[Computed]
    public function exportUrl(): string
    {
        return route('admin.card.transaction.export', array_filter([
            'user'        => $this->user,
            'paper'       => $this->exportPaper,
            'orientation' => $this->exportOrientation,
        ]));
    }

    // Same params as exportUrl, plus preview=1 so the controller streams the
    // PDF inline instead of forcing a download or logging it as an export.
    #[Computed]
    public function exportPreviewUrl(): string
    {
        return route('admin.card.transaction.export', array_filter([
            'user'        => $this->user,
            'paper'       => $this->exportPaper,
            'orientation' => $this->exportOrientation,
            'preview'     => 1,
        ]));
    }

    #[Computed]
    public function getCardTransactionRecord() {
        return CardTransaction::where('card_id', $this->user->card->id)->latest()->get();
    }

    #[Computed]
    public function transactionStats() {
        $transactions = $this->getCardTransactionRecord;
        return [
            'total'    => $transactions->count(),
            'deducted' => $transactions->sum('points_deducted'),
            'balance'  => $transactions->last()?->balance_before + $transactions->last()?->amount ?? 0,
        ];
    }

    public function mount(User $user) {
        $this->user = User::with('card')->where('id', $user->id)->first();
    }
};
?>

<div>
    {{-- ====== PAGE HEADER (mini-navbar: heading left, notifications right) ====== --}}
    <x-page-header
        description="Card's Transaction History"
        class="mb-3"
    >
        <flux:modal.trigger name="export-card-statement">
            <flux:button
                icon="arrow-down-tray"
                size="sm"
                class="font-secondary w-full sm:w-auto justify-center !bg-black !text-white !border-0 hover:!bg-neutral-800 dark:!bg-white dark:!text-black dark:hover:!bg-neutral-200"
            >
                Export statement
            </flux:button>
        </flux:modal.trigger>
    </x-page-header>

    {{-- Heading with breadcrumbs on the right --}}
    <div class="flex items-start justify-between gap-4 mb-6">
        <div>
            <x-heading
                class="!font-primary !font-bold !text-light-txt-primary dark:!text-dark-txt-primary"
                style="font-size: var(--text-section-heading)"
            >
                View all transactions for {{ $this->user->name }} ({{ $this->user->user_code }})
            </x-heading>

            <flux:modal.trigger name="export-card-statement" class="block mt-3 sm:hidden">
                <flux:button
                    variant="primary"
                    icon="arrow-down-tray"
                    size="sm"
                    class="font-secondary w-full justify-center"
                >
                    Export statement
                </flux:button>
            </flux:modal.trigger>
        </div>

        <flux:breadcrumbs class="shrink-0 pt-1">
            <flux:breadcrumbs.item href="{{ route('admin.cards') }}" wire:navigate>Back to Cards</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>Transaction</flux:breadcrumbs.item>
        </flux:breadcrumbs>
    </div>

    {{-- User profile card --}}
    <flux:card class="p-4 mb-6">
        <div class="flex items-center justify-between flex-wrap gap-4">
            <div class="flex items-center gap-4">
                <flux:avatar src="{{ $this->user->avatar_url }}" name="{{ $this->user->name }}" size="xl" />
                <div>
                    <p class="font-mono text-sm tracking-widest text-light-txt-muted dark:text-dark-txt-muted">
                        **** **** **** {{ substr($this->user->card->card_number, -4) }}
                    </p>
                    <p class="font-secondary text-sm text-light-txt-body dark:text-dark-txt-body">
                        {{ $this->user->user_code }}
                    </p>
                    <p class="font-primary text-base font-semibold text-light-txt-primary dark:text-dark-txt-primary">
                        {{ $this->user->name }}
                    </p>
                </div>
            </div>

            <div class="flex gap-6 flex-wrap">
                <div>
                    <span class="block font-secondary text-xs uppercase tracking-wider text-light-txt-muted dark:text-dark-txt-muted mb-1">Role</span>
                    @if ($this->user->role === 'operator')
                        <flux:badge color="blue" size="sm">Operator</flux:badge>
                    @else
                        <flux:badge color="yellow" size="sm">Commuter</flux:badge>
                    @endif
                </div>
                <div>
                    <span class="block font-secondary text-xs uppercase tracking-wider text-light-txt-muted dark:text-dark-txt-muted mb-1">Joined</span>
                    <span class="font-secondary text-sm text-light-txt-body dark:text-dark-txt-body">
                        {{ $this->user->created_at->format('M d, Y') }}
                    </span>
                </div>
                <div>
                    <span class="block font-secondary text-xs uppercase tracking-wider text-light-txt-muted dark:text-dark-txt-muted mb-1">Card status</span>
                    <div class="flex items-center gap-2">
                        @if ($this->user->card->status === 'active')
                            <flux:badge color="green" size="sm">Active</flux:badge>
                        @elseif ($this->user->card->status === 'suspended')
                            <flux:badge color="red" size="sm">Suspended</flux:badge>
                        @else
                            <flux:badge color="zinc" size="sm">{{ ucfirst($this->user->card->status) }}</flux:badge>
                        @endif

                        @if ($this->user->card->status === 'active')
                            <flux:button
                                wire:click="$set('show_suspend_modal', true)"
                                variant="ghost"
                                size="sm"
                                icon="no-symbol"
                                class="font-secondary !text-danger dark:!text-dark-danger"
                            >
                                Suspend
                            </flux:button>
                        @elseif ($this->user->card->status === 'suspended')
                            <flux:button
                                wire:click="$set('show_reinstate_modal', true)"
                                variant="ghost"
                                size="sm"
                                icon="check-circle"
                                class="font-secondary !text-success dark:!text-dark-success"
                            >
                                Reinstate
                            </flux:button>
                        @endif
                    </div>

                    @if ($this->user->card->status === 'suspended' && $this->user->card->suspension_reason)
                        <p class="mt-1.5 font-secondary text-xs text-light-txt-muted dark:text-dark-txt-muted max-w-xs">
                            <span class="font-medium text-light-txt-body dark:text-dark-txt-body">Reason:</span>
                            {{ $this->user->card->suspension_reason }}
                            <br>
                            Suspended {{ $this->user->card->suspended_at?->format('M d, Y') }}
                            @if ($this->user->card->suspendedBy)
                                by {{ $this->user->card->suspendedBy->name }}
                            @endif
                        </p>
                    @endif
                </div>
            </div>
        </div>
    </flux:card>

    {{-- Stats – same style as queue pages --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 sm:gap-3 mb-6">
        <flux:card class="p-3 sm:p-4">
            <div class="flex items-center gap-1.5 sm:gap-2 mb-1.5">
                <div class="flex items-center justify-center w-6 h-6 sm:w-7 sm:h-7 rounded-lg bg-primary/10 dark:bg-primary/20 shrink-0">
                    <flux:icon.document-text class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-primary dark:text-dark-txt-primary" />
                </div>
                <x-text class="font-secondary text-xs sm:text-stat-label text-light-txt-muted dark:text-dark-txt-muted">
                    Total transactions
                </x-text>
            </div>
            <x-text class="font-primary text-stat-value font-bold text-light-txt-primary dark:text-dark-txt-primary block">
                {{ $this->getCardTransactionRecord->count() }}
            </x-text>
        </flux:card>

        <flux:card class="p-3 sm:p-4">
            <div class="flex items-center gap-1.5 sm:gap-2 mb-1.5">
                <div class="flex items-center justify-center w-6 h-6 sm:w-7 sm:h-7 rounded-lg bg-success/10 dark:bg-dark-success/20 shrink-0">
                    <flux:icon.credit-card class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-success dark:text-dark-success" />
                </div>
                <x-text class="font-secondary text-xs sm:text-stat-label text-light-txt-muted dark:text-dark-txt-muted">
                    Current balance
                </x-text>
            </div>
            <x-text class="font-primary text-stat-value font-bold text-success dark:text-dark-success block">
                ₱{{ number_format($this->user->card->balance, 2) }}
            </x-text>
        </flux:card>

        {{-- Optional third stat: points deducted? --}}
        <flux:card class="p-3 sm:p-4">
            <div class="flex items-center gap-1.5 sm:gap-2 mb-1.5">
                <div class="flex items-center justify-center w-6 h-6 sm:w-7 sm:h-7 rounded-lg bg-warning/10 dark:bg-dark-warning/20 shrink-0">
                    <flux:icon.arrow-trending-down class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-warning dark:text-dark-warning" />
                </div>
                <x-text class="font-secondary text-xs sm:text-stat-label text-light-txt-muted dark:text-dark-txt-muted">
                    Total deducted
                </x-text>
            </div>
            <x-text class="font-primary text-stat-value font-bold text-warning dark:text-dark-warning block">
                ₱{{ number_format($this->transactionStats['deducted'], 2) }}
            </x-text>
        </flux:card>
    </div>

    {{-- Table – wrapped in a card for borders --}}
    <flux:card class="overflow-hidden p-0">
        <div class="overflow-x-auto">
        <flux:table>
            <flux:table.columns sticky class="bg-light-subtle/50 dark:bg-dark-secondary/50">
                <flux:table.column align="center" class="px-1 sm:px-2 md:px-4 py-2">Type</flux:table.column>
                <flux:table.column align="center" class="px-1 sm:px-2 md:px-4 py-2">Amount</flux:table.column>
                <flux:table.column align="center" class="hidden md:table-cell px-1 sm:px-2 md:px-4 py-2">Balance after</flux:table.column>
                <flux:table.column align="center" class="px-1 sm:px-2 md:px-4 py-2">Status</flux:table.column>
                <flux:table.column align="center" class="hidden md:table-cell px-1 sm:px-2 md:px-4 py-2">Date</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->getCardTransactionRecord as $index => $transaction)
                    <flux:table.row :key="$transaction->id">
                        <flux:table.cell align="center" class="px-1 sm:px-2 md:px-4 py-1.5 md:py-2">
                            <flux:badge size="sm" color="{{ $transaction->transaction_type === 'top-up' ? 'green' : 'zinc' }}">
                                {{ ucfirst(str_replace('_', ' ', $transaction->transaction_type)) }}
                            </flux:badge>
                        </flux:table.cell>

                        <flux:table.cell align="center" class="px-1 sm:px-2 md:px-4 py-1.5 md:py-2 font-secondary text-xs md:text-table-row tabular-nums {{ $transaction->amount < 0 ? 'text-danger dark:text-dark-danger' : 'text-success dark:text-dark-success' }}">
                            ₱{{ number_format($transaction->amount, 2) }}
                        </flux:table.cell>

                        <flux:table.cell align="center" class="hidden md:table-cell px-1 sm:px-2 md:px-4 py-1.5 md:py-2 font-secondary text-xs md:text-table-row tabular-nums text-light-txt-muted dark:text-dark-txt-muted">
                            ₱{{ number_format($transaction->balance_after, 2) }}
                        </flux:table.cell>

                        <flux:table.cell align="center" class="px-1 sm:px-2 md:px-4 py-1.5 md:py-2">
                            @if ($transaction->status === 'success')
                                <flux:badge color="green" size="sm" icon="check-circle">Success</flux:badge>
                            @elseif ($transaction->status === 'failed')
                                <flux:badge color="red" size="sm" icon="x-circle">Failed</flux:badge>
                            @else
                                <flux:badge color="yellow" size="sm">{{ ucfirst($transaction->status) }}</flux:badge>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell align="center" class="hidden md:table-cell px-1 sm:px-2 md:px-4 py-1.5 md:py-2 font-secondary text-xs md:text-table-row text-light-txt-muted dark:text-dark-txt-muted tabular-nums">
                            {{ $transaction->created_at->format('Y-m-d H:i') }}
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="text-center py-12">
                            <div class="flex flex-col items-center justify-center gap-2">
                                <flux:icon.document-text class="w-8 h-8 text-light-txt-muted dark:text-dark-txt-muted" />
                                <p class="font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">No transactions found.</p>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
        </div>
    </flux:card>

    {{-- If you have pagination, add it here --}}

    {{-- ===================== EXPORT MODAL ===================== --}}
    <flux:modal
        name="export-card-statement"
        :closable="false"
        class="w-[calc(100%-2rem)] sm:max-w-lg mx-auto rounded-xl overflow-hidden"
    >
        <div class="flex flex-col p-4 sm:p-6 !pr-4 sm:!pr-6 space-y-5 overflow-y-auto max-h-[70vh]">
            <div class="flex items-start justify-between">
                <div>
                    <flux:heading size="xl" class="!font-primary !font-bold text-light-txt-primary dark:text-dark-txt-primary">
                        Export card statement
                    </flux:heading>
                    <flux:text class="mt-1 font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">
                        Full transaction history for {{ $this->user->name }} ({{ $this->user->user_code }}).
                    </flux:text>
                </div>
                <flux:modal.close>
                    <button type="button" class="p-1 rounded-full hover:bg-light-subtle dark:hover:bg-dark-subtle text-light-txt-muted dark:text-dark-txt-muted -mt-1">
                        <flux:icon name="x-mark" class="w-5 h-5" />
                    </button>
                </flux:modal.close>
            </div>

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
                    x-on:click="Flux.modal('export-card-statement').close(); Flux.modal('preview-card-statement').show()"
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
        name="preview-card-statement"
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
                    x-on:click="Flux.modal('preview-card-statement').close()"
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
                    x-on:click="Flux.modal('preview-card-statement').close(); Flux.modal('export-card-statement').show()"
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

    {{-- ===================== SUSPEND CARD MODAL ===================== --}}
    <flux:modal
        wire:model="show_suspend_modal"
        :closable="false"
        class="w-[calc(100%-2rem)] sm:max-w-md mx-auto rounded-xl overflow-hidden"
    >
        <div class="flex flex-col p-4 sm:p-6 !pr-4 sm:!pr-6 space-y-5">
            <div class="flex items-start justify-between">
                <div>
                    <flux:heading size="xl" class="!font-primary !font-bold text-light-txt-primary dark:text-dark-txt-primary">
                        Suspend this card?
                    </flux:heading>
                    <flux:text class="mt-1 font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">
                        {{ $this->user->name }} won't be able to use this card for any transactions until it's reinstated. They'll be notified with the reason below.
                    </flux:text>
                </div>
                <flux:modal.close>
                    <button type="button" class="p-1 rounded-full hover:bg-light-subtle dark:hover:bg-dark-subtle text-light-txt-muted dark:text-dark-txt-muted -mt-1">
                        <flux:icon name="x-mark" class="w-5 h-5" />
                    </button>
                </flux:modal.close>
            </div>

            <div>
                <flux:textarea
                    wire:model="suspensionReason"
                    label="Reason for suspension"
                    placeholder="e.g. Reported for suspicious tap activity…"
                    rows="3"
                    class="font-secondary text-table-row bg-light-primary dark:bg-dark-surface text-light-txt-body dark:text-dark-txt-primary border-light-bd-default dark:border-dark-bd-default placeholder:text-light-txt-muted dark:placeholder:text-dark-txt-muted"
                />
                <flux:error name="suspensionReason" />
            </div>

            <div class="flex flex-col-reverse sm:flex-row justify-end items-stretch sm:items-center gap-2 pt-2 border-t border-light-bd-default dark:border-dark-bd-default">
                <flux:modal.close class="w-full sm:w-auto">
                    <flux:button type="button" variant="ghost" class="w-full sm:w-auto justify-center font-secondary">
                        Cancel
                    </flux:button>
                </flux:modal.close>
                <flux:button
                    wire:click="suspendCard"
                    wire:loading.attr="disabled"
                    wire:target="suspendCard"
                    type="button"
                    variant="danger"
                    class="w-full sm:w-auto justify-center font-secondary"
                >
                    <span wire:loading.remove wire:target="suspendCard">Suspend Card</span>
                    <span wire:loading wire:target="suspendCard">Suspending…</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal
        wire:model="show_reinstate_modal"
        :closable="false"
        class="w-[calc(100%-2rem)] max-w-xs sm:max-w-sm"
    >
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Reinstate this card?</flux:heading>
                <flux:text class="mt-2">
                    {{ $this->user->name }} will be able to use this card for transactions again. They'll be notified.
                </flux:text>
            </div>
            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">Cancel</flux:button>
                </flux:modal.close>
                <flux:button
                    wire:click="reinstateCard"
                    wire:loading.attr="disabled"
                    wire:target="reinstateCard"
                    variant="primary"
                >
                    <span wire:loading.remove wire:target="reinstateCard">Reinstate</span>
                    <span wire:loading wire:target="reinstateCard">Reinstating…</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
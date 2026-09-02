<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;

use App\Models\User;
use App\Models\CardTransaction;

new #[Layout('layouts.admin-layout')] class extends Component
{
    public $user;

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
    {{-- Heading with breadcrumbs on the right --}}
    <div class="flex items-start justify-between gap-4 mb-6">
        <div>
            <x-heading
                size="xl"
                class="!font-primary !font-bold !text-light-txt-primary dark:!text-dark-txt-primary"
                style="font-size: var(--text-page-title)"
            >
                Transaction History
            </x-heading>
            <x-text variant="subtle" class="!font-secondary mt-1 block" style="font-size: var(--text-helper)">
                View all transactions for {{ $this->user->name }} ({{ $this->user->user_code }})
            </x-text>
        </div>

        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 shrink-0">
            <flux:modal.trigger name="export-card-statement">
                <flux:button
                    variant="primary"
                    icon="arrow-down-tray"
                    size="sm"
                    class="font-secondary w-full sm:w-auto justify-center"
                >
                    Export statement
                </flux:button>
            </flux:modal.trigger>

            <flux:breadcrumbs class="shrink-0 pt-1">
                <flux:breadcrumbs.item href="{{ route('admin.cards') }}" wire:navigate>Back to Cards</flux:breadcrumbs.item>
                <flux:breadcrumbs.item>Transaction</flux:breadcrumbs.item>
            </flux:breadcrumbs>
        </div>
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
                    @if ($this->user->card->status === 'active')
                        <flux:badge color="green" size="sm">Active</flux:badge>
                    @else
                        <flux:badge color="red" size="sm">Inactive</flux:badge>
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
        <flux:table>
            <flux:table.columns sticky class="bg-white dark:bg-zinc-900">
                <flux:table.column align="center" class="px-1! sm:px-2! md:px-4! py-2">#</flux:table.column>
                <flux:table.column align="center" class="px-1 sm:px-2 md:px-4 py-2">Reference No.</flux:table.column>
                <flux:table.column align="center" class="px-1 sm:px-2 md:px-4 py-2">Type</flux:table.column>
                <flux:table.column align="center" class="px-1 sm:px-2 md:px-4 py-2">Amount</flux:table.column>
                <flux:table.column align="center" class="px-1 sm:px-2 md:px-4 py-2">Balance before</flux:table.column>
                <flux:table.column align="center" class="px-1 sm:px-2 md:px-4 py-2">Balance after</flux:table.column>
                <flux:table.column align="center" class="px-1 sm:px-2 md:px-4 py-2">Status</flux:table.column>
                <flux:table.column align="center" class="px-1 sm:px-2 md:px-4 py-2">Message</flux:table.column>
                <flux:table.column align="center" class="px-1 sm:px-2 md:px-4 py-2">Date</flux:table.column>
                <flux:table.column align="center" class="px-1! sm:px-2! md:px-4! py-2">Actions</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->getCardTransactionRecord as $index => $transaction)
                    <flux:table.row :key="$transaction->id">
                        <flux:table.cell align="center" class="px-1! sm:px-2! md:px-4! py-1.5 md:py-2 font-secondary text-xs md:text-table-row text-light-txt-muted dark:text-dark-txt-muted">
                            {{ $index + 1 }}
                        </flux:table.cell>

                        <flux:table.cell align="center" class="px-1 sm:px-2 md:px-4 py-1.5 md:py-2 font-secondary text-xs md:text-table-row text-light-txt-body dark:text-dark-txt-body">
                            {{ $transaction->reference_no }}
                        </flux:table.cell>

                        <flux:table.cell align="center" class="px-1 sm:px-2 md:px-4 py-1.5 md:py-2">
                            <flux:badge size="sm" color="{{ $transaction->transaction_type === 'top-up' ? 'green' : 'zinc' }}">
                                {{ ucfirst(str_replace('_', ' ', $transaction->transaction_type)) }}
                            </flux:badge>
                        </flux:table.cell>

                        <flux:table.cell align="center" class="px-1 sm:px-2 md:px-4 py-1.5 md:py-2 font-secondary text-xs md:text-table-row tabular-nums {{ $transaction->amount < 0 ? 'text-danger dark:text-dark-danger' : 'text-success dark:text-dark-success' }}">
                            ₱{{ number_format($transaction->amount, 2) }}
                        </flux:table.cell>

                        <flux:table.cell align="center" class="px-1 sm:px-2 md:px-4 py-1.5 md:py-2 font-secondary text-xs md:text-table-row tabular-nums text-light-txt-muted dark:text-dark-txt-muted">
                            ₱{{ number_format($transaction->balance_before, 2) }}
                        </flux:table.cell>

                        <flux:table.cell align="center" class="px-1 sm:px-2 md:px-4 py-1.5 md:py-2 font-secondary text-xs md:text-table-row tabular-nums text-light-txt-muted dark:text-dark-txt-muted">
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

                        <flux:table.cell align="center" class="px-1 sm:px-2 md:px-4 py-1.5 md:py-2 font-secondary text-xs md:text-table-row text-light-txt-muted dark:text-dark-txt-muted max-w-40 truncate">
                            {{ $transaction->message }}
                        </flux:table.cell>

                        <flux:table.cell align="center" class="px-1 sm:px-2 md:px-4 py-1.5 md:py-2 font-secondary text-xs md:text-table-row text-light-txt-muted dark:text-dark-txt-muted tabular-nums">
                            {{ $transaction->created_at->format('Y-m-d H:i') }}
                        </flux:table.cell>

                        <flux:table.cell align="center" class="px-1! sm:px-2! md:px-4! py-1.5 md:py-2">
                            <flux:button
                                variant="ghost"
                                size="sm"
                                icon="ellipsis-horizontal"
                                inset="top bottom"
                                title="More actions"
                            />
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="10" class="text-center py-12">
                            <div class="flex flex-col items-center justify-center gap-2">
                                <flux:icon.document-text class="w-8 h-8 text-zinc-300" />
                                <p class="font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">No transactions found.</p>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
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
</div>
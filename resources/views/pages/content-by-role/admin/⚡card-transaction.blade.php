<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;

use App\Models\User;
use App\Models\CardTransaction;

new #[Layout('layouts.admin-layout')] class extends Component
{
    public $user;

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
    <flux:breadcrumbs>
        <flux:breadcrumbs.item href="{{ route('admin.cards') }}" wire:navigate>Cards</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>Transaction</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <x-pages-heading class="mt-4" heading="Transaction history" />

    {{-- Profile card --}}
    <div class="mt-4 mb-4 p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 flex items-center justify-between flex-wrap gap-4">

        <div class="flex items-center gap-4">
            <flux:avatar src="{{ $this->user->avatar_url }}" name="{{ $this->user->name }}" size="xl" />
            <div>
                <x-heading class="font-mono tracking-widest text-sm">
                    **** **** **** {{ substr($this->user->card->card_number, -4) }}
                </x-heading>
                <x-text variant="strong">{{ $this->user->user_code }}</x-text>
                <x-text>{{ $this->user->name }}</x-text>
            </div>
        </div>

        <div class="flex gap-8">
            <div>
                <span class="block text-xs text-zinc-400 font-medium uppercase tracking-wider mb-1">Role</span>
                @if ($this->user->role === 'operator')
                    <flux:badge color="blue" size="sm">Operator</flux:badge>
                @else
                    <flux:badge color="yellow" size="sm">Commuter</flux:badge>
                @endif
            </div>
            <div>
                <span class="block text-xs text-zinc-400 font-medium uppercase tracking-wider mb-1">Joined</span>
                <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">
                    {{ $this->user->created_at->format('M d, Y') }}
                </span>
            </div>
            <div>
                <span class="block text-xs text-zinc-400 font-medium uppercase tracking-wider mb-1">Card status</span>
                @if ($this->user->card->status === 'active')
                    <flux:badge color="green" size="sm">Active</flux:badge>
                @else
                    <flux:badge color="red" size="sm">Inactive</flux:badge>
                @endif
            </div>
        </div>

    </div>

    <div class="grid grid-cols-3 gap-3 mb-5">
        <flux:card >
            <p class="text-xs text-zinc-400 mb-1">Total transactions</p>
            <p class="text-2xl font-medium text-zinc-800 dark:text-zinc-100">
                {{ $this->getCardTransactionRecord->count() }}
            </p>
        </flux:card>
        <flux:card >
            <p class="text-xs text-zinc-400 mb-1">Current balance</p>
            <p class="text-2xl font-medium text-green-700 dark:text-green-400">
                {{ number_format($this->user->card->balance, 2) }}
            </p>
        </flux:card>
    </div>

    {{-- Table --}}
    <flux:table container:class="max-h-160">
        <flux:table.columns sticky class="bg-white dark:bg-zinc-900">
            <flux:table.column>#</flux:table.column>
            <flux:table.column>Reference No.</flux:table.column>
            <flux:table.column>Type</flux:table.column>
            <flux:table.column>Amount</flux:table.column>
            <flux:table.column>Balance before</flux:table.column>
            <flux:table.column>Balance after</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            <flux:table.column>Message</flux:table.column>
            <flux:table.column>Date</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->getCardTransactionRecord as $index => $transaction)
                <flux:table.row :key="$transaction->id">

                    <flux:table.cell class="text-zinc-400">
                        {{ $index + 1 }}
                    </flux:table.cell>

                    <flux:table.cell>
                        <div class="flex items-center gap-2">
                            {{ $transaction->reference_no }}
                        </div>
                    </flux:table.cell>

                    <flux:table.cell>
                        <div class="flex items-center gap-2">
                            {{ $transaction->transaction_type }}
                        </div>
                    </flux:table.cell>


                    <flux:table.cell class="text-red-500 tabular-nums">
                        {{ number_format($transaction->points_deducted, 2) }}
                    </flux:table.cell>

                    <flux:table.cell class="text-zinc-500 tabular-nums">
                        {{ number_format($transaction->balance_before, 2) }}
                    </flux:table.cell>
                    <flux:table.cell class="text-zinc-500 tabular-nums">
                        {{ number_format($transaction->balance_after, 2) }}
                    </flux:table.cell>

                    <flux:table.cell>
                        @if ($transaction->status === 'success')
                            <flux:badge color="green" size="sm" icon="check-circle">Success</flux:badge>
                        @elseif ($transaction->status === 'failed')
                            <flux:badge color="red" size="sm" icon="x-circle">Failed</flux:badge>
                        @else
                            <flux:badge color="yellow" size="sm">{{ $transaction->status }}</flux:badge>
                        @endif
                    </flux:table.cell>

                    <flux:table.cell class="text-zinc-500 max-w-48 text-wrap">
                        {{ $transaction->message }}
                    </flux:table.cell>

                    <flux:table.cell class="text-zinc-400 text-xs tabular-nums">
                        {{ $transaction->created_at->format('Y-m-d') }}
                    </flux:table.cell>

                    <flux:table.cell>
                        <flux:button
                            variant="ghost"
                            size="sm"
                            icon="ellipsis-horizontal"
                            inset="top bottom"
                        />
                    </flux:table.cell>

                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="9" class="text-center text-zinc-400 py-10">
                        No transactions found for this card.
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
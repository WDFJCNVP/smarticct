<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;

use App\Models\User;
use App\Models\CardTransaction;

new #[Layout('layouts.dashboard.admin.admin-layout')] class extends Component
{
    public $user;

    #[Computed]
    public function getCardTransactionRecord() {
        return CardTransaction::where('card_id', $this->user->card->id)->get();
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

    <x-pages-heading heading="Transaction History"/>

    <div class="grid w-full grid-cols-2 text-sm mb-6 gap-6 items-center">

        <div class="flex items-center gap-4">
            <flux:avatar src="{{ $this->user->avatar_url }}" name="{{ $this->user->name }}" size="xl" />
            <div>
                <div class="font-semibold text-base text-zinc-800 dark:text-zinc-200"> ****** {{ substr($this->user->card->uid, -4) }}</div>
                <div class="text-sm text-zinc-500">{{ $this->user->name }}</div>
            </div>
        </div>

        <div class="flex flex-col gap-4">
            <div>
                <span class="block text-xs text-zinc-400 font-medium uppercase tracking-wider">Role</span>
                @if ($this->user->role === 'operator')
                    <flux:badge color="blue" size="sm" inset="top bottom">Operator</flux:badge>
                @else
                    <flux:badge color="yellow" size="sm" inset="top bottom">Commuter</flux:badge>
                @endif
            </div>
            <div>
                <span class="block text-xs text-zinc-400 font-medium uppercase tracking-wider">Joined</span>
                <span class="text-zinc-700 dark:text-zinc-300 font-medium">{{ $this->user->created_at->format('M d, Y') }}</span>
            </div>
        </div>

    </div>

    <flux:table container:class="max-h-80 mt-5">
        <flux:table.columns sticky class="bg-white dark:bg-zinc-900">
            <flux:table.column>No</flux:table.column>
            <flux:table.column>Transaction Type</flux:table.column>
            <flux:table.column>Points Deducted</flux:table.column>
            <flux:table.column>Amount</flux:table.column>
            <flux:table.column>Balance Before</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            <flux:table.column>Message</flux:table.column>
            <flux:table.column>Transaction Date</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>

            @foreach ($this->getCardTransactionRecord as $index => $transaction)
                <flux:table.row :key="$transaction->id">
                    <flux:table.cell>{{ $index + 1 }}</flux:table.cell>
                    <flux:table.cell>{{ $transaction->transaction_type }}</flux:table.cell>
                    <flux:table.cell>{{ $transaction->points_deducted }}</flux:table.cell>
                    <flux:table.cell>{{ $transaction->amount }}</flux:table.cell>
                    <flux:table.cell>{{ $transaction->balance_before }}</flux:table.cell>
                    <flux:table.cell>{{ $transaction->status }}</flux:table.cell>
                    <flux:table.cell>{{ $transaction->message }}</flux:table.cell>
                    <flux:table.cell>{{ $transaction->created_at->format('Y-m-d') }}</flux:table.cell>
                    <flux:table.cell>
                        
                        <flux:link href="/admin/card/transaction/{{ $transaction->id }}" wire:navigate>
                            <flux:button
                                variant="ghost"
                                size="sm"
                                icon="ellipsis-horizontal"
                                inset="top bottom"
                            />
                        </flux:link>

                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>
</div>
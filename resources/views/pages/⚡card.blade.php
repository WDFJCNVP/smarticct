<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;

use App\Models\Card;

new class extends Component
{
    #[Computed]
    public function userCard(): ?Card
    {
        return Card::with(['user', 'cardTransaction' => function ($query) {
            $query->latest('transaction_time')->limit(10);
        }])->where('user_id', auth()->id())->first();
    }

    public function render()
    {
        $role = auth()->user()->role;

        return $this->view()->layout('layouts.' . $role . '-layout');
    }
};
?>

<div class="max-w-8xl mx-auto p-4 lg:p-6">

    @if ($this->userCard)

        <div class="lg:sticky lg:top-6 lg:z-10 mb-5 bg-white dark:bg-zinc-900">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

                <flux:card class="bg-black text-white">
                    <div class="flex justify-between items-start mb-7">
                        <div>
                            <p class="text-[11px] opacity-60 tracking-wide">smarticct</p>
                            <p class="text-sm font-medium capitalize">{{ auth()->user()->role }} card</p>
                        </div>
                        <flux:icon name="wifi" class="w-5 h-5 opacity-70 rotate-90" />
                    </div>

                    <p class="font-mono text-lg tracking-widest mb-4">{{ $this->userCard->card_number }}</p>

                    <div class="flex justify-between items-end">
                        <div>
                            <p class="text-[10px] opacity-60">cardholder</p>
                            <p class="text-sm font-medium">{{ $this->userCard->user->name }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-[10px] opacity-60">type</p>
                            <p class="text-sm font-medium capitalize">{{ auth()->user()->role }}</p>
                        </div>
                    </div>
                </flux:card>

                <flux:card>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400 mb-1">Available balance</p>
                    <p class="text-3xl font-medium mb-3.5">
                        ₱{{ number_format($this->userCard->balance, 2) }}
                    </p>
                    <div class="flex gap-2">
                        <flux:button size="sm" class="flex-1">
                            <flux:icon name="plus" class="w-3.5 h-3.5" />
                            Top up
                        </flux:button>
                        <flux:button size="sm" variant="ghost" class="flex-1">
                            <flux:icon name="exclamation-triangle" class="w-3.5 h-3.5" />
                            Report lost
                        </flux:button>
                    </div>
                </flux:card>

            </div>
        </div>

        <div>
            <div class="flex justify-between items-center mb-2.5">
                <p class="text-sm font-medium">Recent activity</p>
            </div>

            <div class="space-y-2">
                @forelse ($this->userCard->cardTransaction as $transaction)
                    @php
                        $isCredit = $transaction->transaction_type === 'top_up' || $transaction->amount > 0;
                    @endphp
                    <div class="flex items-center gap-2.5 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-lg px-3 py-2.5 lg:px-4 lg:py-3">
                        <div @class([
                            'w-8 h-8 rounded-full flex items-center justify-center shrink-0',
                            'bg-green-100 dark:bg-green-900' => $isCredit,
                            'bg-blue-100 dark:bg-blue-900' => !$isCredit,
                        ])>
                            <flux:icon
                                :name="$isCredit ? 'arrow-down-circle' : 'truck'"
                                @class([
                                    'w-4 h-4',
                                    'text-green-700 dark:text-green-300' => $isCredit,
                                    'text-blue-700 dark:text-blue-300' => !$isCredit,
                                ])
                            />
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium truncate">
                                {{ $transaction->message ?? ucfirst(str_replace('_', ' ', $transaction->transaction_type)) }}
                            </p>
                            <p class="text-[11px] text-zinc-400 mt-0.5">
                                {{ $transaction->location }} · {{ $transaction->transaction_time?->diffForHumans() }}
                            </p>
                        </div>
                        <span @class([
                            'text-sm font-medium whitespace-nowrap',
                            'text-green-600 dark:text-green-400' => $isCredit,
                        ])>
                            {{ $isCredit ? '+' : '-' }}₱{{ number_format(abs($transaction->amount), 2) }}
                        </span>
                    </div>
                @empty
                    <p class="text-xs text-zinc-400 text-center py-6">No activity yet.</p>
                @endforelse
            </div>
        </div>

    @else
        <div class="text-center py-16">
            <flux:icon name="credit-card" class="w-10 h-10 text-zinc-300 mx-auto mb-3" />
            <p class="text-sm text-zinc-500">No card linked to your account yet.</p>
            <p class="text-xs text-zinc-400 mt-1">Visit the terminal to get your RFID card issued.</p>
        </div>
    @endif

</div>
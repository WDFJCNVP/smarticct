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

<div>

    <x-pages-heading heading="My Card" description="Manage your smart card and view transaction history" />

    @if ($this->userCard)

        <div class="lg:sticky lg:top-6 lg:z-10 mb-5">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

                <flux:card class="bg-black text-white">
                    <div class="flex justify-between items-start mb-7">
                        <div>
                            <x-text size="sm" class="text-white">smarticct</x-text>
                            <x-text size="lg" class="text-white">{{ auth()->user()->role }} card</x-text>
                        </div>
                        <flux:icon name="wifi" class="w-5 h-5 opacity-70 rotate-90 text-white" />
                    </div>

                    <x-text size="xl" class="font-mono text-white tracking-widest mb-4">{{ $this->userCard->card_number }}</x-text>

                    <div class="flex justify-between items-end">
                        <div>
                            <x-text class="text-[10px] opacity-60 text-white">cardholder</x-text>
                            <x-text class="text-sm font-medium text-white">{{ $this->userCard->user->name }}</x-text>
                        </div>
                        <div class="text-right">
                            <x-text class="text-[10px] opacity-60">type</x-text>
                            <x-text class="text-sm font-medium capitalize text-white">{{ auth()->user()->role }}</x-text>
                        </div>
                    </div>
                </flux:card>

                <flux:card x-data="{ showBalance: false }">
                    <div class="flex justify-between items-center mb-1">
                        <x-text class="text-xs text-zinc-500 dark:text-zinc-400">Available balance</x-text>
                        
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
                        <flux:button size="sm" class="flex-1" icon="plus" variant="primary">
                            Top up
                        </flux:button>
                        <flux:button size="sm" variant="ghost" class="flex-1" icon="exclamation-triangle">
                            Report lost
                        </flux:button>
                    </div>
                </flux:card>

            </div>
        </div>

        <div>
            <div class="flex justify-between items-center mb-2.5">
                <x-text class="text-sm font-medium">Recent activity</x-text>
            </div>

            <div class="space-y-2 max-h-96 overflow-y-auto pr-1">
                @forelse ($this->userCard->cardTransaction as $transaction)
                    @php
                        $isCredit = $transaction->transaction_type === 'top_up' || $transaction->amount > 0;
                    @endphp
                    <flux:card size="sm" class="flex items-center gap-3 justify-between ">
                        <div>
                            <flux:icon name="check" class="w-4 h-4 text-green-600 dark:text-green-400" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <x-text class="text-sm font-medium truncate">
                                {{ $transaction->message ?? ucfirst(str_replace('_', ' ', $transaction->transaction_type)) }}
                            </x-text>
                            <x-text class="text-[11px] text-zinc-400 mt-0.5">
                                {{ $transaction->location }} · {{ $transaction->transaction_time?->diffForHumans() }}
                            </x-text>
                        </div>
                        <x-text size="sm">
                            - ₱{{ number_format(abs($transaction->amount), 2) }}
                        </x-text>
                    </flux:card>
                @empty
                    <x-text class="text-xs text-zinc-400 text-center py-6">No activity yet.</x-text>
                @endforelse
            </div>
        </div>

    @else
        <div class="text-center py-16">
            <flux:icon name="credit-card" class="w-10 h-10 text-zinc-300 mx-auto mb-3" />
            <x-text class="text-sm text-zinc-500">No card linked to your account yet.</x-text>
            <x-text class="text-xs text-zinc-400 mt-1">Visit the terminal to get your RFID card issued.</x-text>
        </div>
    @endif

</div>
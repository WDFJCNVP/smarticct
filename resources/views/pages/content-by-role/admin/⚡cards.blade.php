<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;

use App\Models\User;

new #[Layout('layouts.admin-layout')] class extends Component
{   
    public $search;

    #[Computed]
    public function getUsers() {
        return User::with('card')
            ->whereIn('role', ['operator', 'commuter'])
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%' . $this->search . '%')
                    ->orWhere('user_code', 'like', '%' . $this->search . '%')
                    ->orWhereHas('card', fn($q) =>
                        $q->where('card_number', 'like', '%' . $this->search . '%')
                    );
                });
            })
            ->get();
    }
};
?>

<div>
    <x-pages-heading heading="Cards" description="View all registered cards in the system." />

    <div class="grid grid-cols-3 gap-3 mt-6 mb-5">
        <div class="rounded-lg bg-zinc-50 dark:bg-zinc-800 p-4">
            <p class="text-xs text-zinc-400 mb-1">Total cards</p>
            <p class="text-2xl font-medium text-zinc-800 dark:text-zinc-100">
                {{ $this->getUsers->count() }}
            </p>
        </div>
        <div class="rounded-lg bg-zinc-50 dark:bg-zinc-800 p-4">
            <p class="text-xs text-zinc-400 mb-1">Active</p>
            <p class="text-2xl font-medium text-green-700 dark:text-green-400">
                {{ $this->getUsers->filter(fn($u) => $u->card->status === 'active')->count() }}
            </p>
        </div>
        <div class="rounded-lg bg-zinc-50 dark:bg-zinc-800 p-4">
            <p class="text-xs text-zinc-400 mb-1">Inactive / suspended</p>
            <p class="text-2xl font-medium text-red-600 dark:text-red-400">
                {{ $this->getUsers->filter(fn($u) => $u->card->status !== 'active')->count() }}
            </p>
        </div>
    </div>

    <div class="flex items-center gap-3 mb-4">
        <div class="flex items-center gap-2">
            <flux:input
                class="max-w-xs"
                size="sm"
                icon="magnifying-glass"
                placeholder="Search name, ID, card…"
                wire:model.live.debounce.300ms="search"
            />
        </div>
    </div>

    <flux:table container:class="max-h-160">
        <flux:table.columns sticky class="bg-white dark:bg-zinc-900">
            <flux:table.column>#</flux:table.column>
            <flux:table.column>Owner ID</flux:table.column>
            <flux:table.column>Card no.</flux:table.column>
            <flux:table.column>Name</flux:table.column>
            <flux:table.column>Balance</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            <flux:table.column>Registered</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->getUsers as $index => $user)
                <flux:table.row :key="$user->id">

                    <flux:table.cell class="text-zinc-400">
                        {{ $index + 1 }}
                    </flux:table.cell>

                    <flux:table.cell class="text-zinc-500 text-xs">
                        {{ $user->user_code }}
                    </flux:table.cell>

                    <flux:table.cell class="font-mono text-xs text-zinc-500">
                        **** **** **** {{ substr($user->card->card_number, -4) }}
                    </flux:table.cell>

                    <flux:table.cell>
                        <div class="flex items-center gap-2">
                            <flux:avatar
                                size="xs"
                                src="{{ $user->avatar_url }}"
                                name="{{ $user->name }}"
                            />
                            {{ $user->name }}
                        </div>
                    </flux:table.cell>

                    <flux:table.cell class="tabular-nums font-medium">
                        {{ number_format($user->card->balance, 2) }}
                    </flux:table.cell>

                    <flux:table.cell>
                        @if ($user->card->status === 'active')
                            <flux:badge color="green" size="sm">Active</flux:badge>
                        @else
                            <flux:badge color="red" size="sm">{{ ucfirst($user->card->status) }}</flux:badge>
                        @endif
                    </flux:table.cell>

                    <flux:table.cell class="text-zinc-400 text-xs">
                        {{ $user->card->created_at->format('Y-m-d') }}
                    </flux:table.cell>

                    <flux:table.cell>
                        <flux:link href="/admin/card/transaction/{{ $user->id }}" wire:navigate>
                            <flux:button
                                variant="ghost"
                                size="sm"
                                icon="ellipsis-horizontal"
                                inset="top bottom"
                            />
                        </flux:link>
                    </flux:table.cell>

                </flux:table.row>

            @empty
                <flux:table.row>
                    <flux:table.cell colspan="8">
                        <div class="flex flex-col items-center justify-center py-12 gap-2">
                            <flux:icon.credit-card class="w-8 h-8 text-zinc-300" />
                            <p class="text-sm text-zinc-400">No cards found.</p>
                            @if ($search)
                                <p class="text-xs text-zinc-400">Try a different search term.</p>
                            @endif
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
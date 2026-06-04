<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;

use App\Models\User;

new #[Layout('layouts.dashboard.admin.admin-layout')] class extends Component
{   
    public $search;

     #[Computed]
    public function getUsers() {
        return User::with('card')->whereIn('role', ['operator', 'passenger'])->get();
    }
};
?>

<div>
    <x-pages-heading heading="Cards" description="View all registered cards in the system."/>
    
    <div class="flex items-center gap-3">
        <div class="flex-1 w-full">
            <flux:heading size="lg" class="flex gap-2">
                All Cards
                <flux:text size="lg" variant="subtle">
                    {{ $this->getUsers->count() }}
                </flux:text>
            </flux:heading>
        </div>
        <div class="flex items-center gap-2">
            <flux:input class="max-w-xs" size="sm" icon="magnifying-glass" placeholder="Search" wire:model.live="search" />

            <flux:link href="{{ route('admin.register.user') }}" wire:navigate>
                {{-- <flux:button variant="primary" color="zinc" size="sm">Search</flux:button> --}}
            </flux:link>
        </div>
    </div>

    <flux:table container:class="max-h-80 mt-5">
        <flux:table.columns sticky class="bg-white dark:bg-zinc-900">
            <flux:table.column>No</flux:table.column>
            <flux:table.column>Owner ID</flux:table.column>
            <flux:table.column>Card No.</flux:table.column>
            <flux:table.column>Name</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            <flux:table.column>Date Registered</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>

            @foreach ($this->getUsers as $index => $user)
                <flux:table.row :key="$user->id">
                    <flux:table.cell>{{ $index + 1 }}</flux:table.cell>
                    <flux:table.cell>{{ $user->id }}</flux:table.cell>
                    <flux:table.cell> ****** {{ substr($user->card->uid, -4) }}</flux:table.cell>
                    <flux:table.cell>{{ $user->name }}</flux:table.cell>
                    <flux:table.cell>{{ $user->card->status }}</flux:table.cell>
                    <flux:table.cell>{{ $user->card->created_at->format('Y-m-d') }}</flux:table.cell>
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
            @endforeach
        </flux:table.rows>
    </flux:table>
</div>
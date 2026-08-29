<?php

use Livewire\Component;
use Livewire\Attributes\Layout;

new #[Layout('layouts.commuter-layout')]class extends Component
{
    //
};
?>

<div>
    <div class="flex items-start justify-between gap-4 mb-6">
        <div>
            <x-heading
                size="xl"
                class="!font-primary !font-bold !text-light-txt-primary dark:!text-dark-txt-primary"
                style="font-size: var(--text-page-title)"
            >
                Transactions
            </x-heading>
            <x-text variant="subtle" class="!font-secondary mt-1 block" style="font-size: var(--text-helper)">
                View and manage your transactions.
            </x-text>
        </div>
    </div>

    <div x-data="{ tab: 'active' }" class="space-y-6">
        <div class="flex gap-6 border-b border-light-bd-default dark:border-dark-bd-default text-sm">
            <button
                type="button"
                @click="tab = 'active'"
                :class="tab === 'active'
                    ? 'border-primary text-primary dark:text-primary font-medium'
                    : 'border-transparent text-light-txt-muted dark:text-dark-txt-muted hover:text-light-txt-primary dark:hover:text-dark-txt-primary'"
                class="pb-3 border-b-2 transition-colors flex items-center gap-1.5 cursor-pointer font-secondary"
            >
                Active Renting Transaction
            </button>
            <button
                type="button"
                @click="tab = 'rent-transaction'"
                :class="tab === 'rent-transaction'
                    ? 'border-primary text-primary dark:text-primary font-medium'
                    : 'border-transparent text-light-txt-muted dark:text-dark-txt-muted hover:text-light-txt-primary dark:hover:text-dark-txt-primary'"
                class="pb-3 border-b-2 transition-colors cursor-pointer font-secondary"
            >
                Renting Transaction History
            </button>
            <button
                type="button"
                @click="tab = 'card-history'"
                :class="tab === 'card-history'
                    ? 'border-primary text-primary dark:text-primary font-medium'
                    : 'border-transparent text-light-txt-muted dark:text-dark-txt-muted hover:text-light-txt-primary dark:hover:text-dark-txt-primary'"
                class="pb-3 border-b-2 transition-colors cursor-pointer font-secondary"
            >
                Card Transactions History
            </button>
        </div>

        <div x-show="tab === 'active'" x-cloak class="space-y-3">
            <livewire:pages::content-by-role.commuter.active-renting-transaction />
        </div>

        <div x-show="tab === 'rent-transaction'" x-cloak class="space-y-4">
            <livewire:pages::partial.commuter-transaction-history />
        </div>

        <div x-show="tab === 'card-history'" x-cloak>
            {{-- Table with borders --}}
            <flux:card class="overflow-hidden p-0">
                <flux:table>
                    <flux:table.columns sticky class="bg-light-secondary dark:bg-dark-secondary">
                        <flux:table.column align="center" class="px-1! sm:px-2! md:px-4! py-2">#</flux:table.column>
                        <flux:table.column align="center" class="px-1 sm:px-2 md:px-4 py-2">Reference</flux:table.column>
                        <flux:table.column align="center" class="px-1 sm:px-2 md:px-4 py-2">Type</flux:table.column>
                        <flux:table.column align="center" class="px-1 sm:px-2 md:px-4 py-2">Amount</flux:table.column>
                        <flux:table.column align="center" class="px-1 sm:px-2 md:px-4 py-2">Status</flux:table.column>
                        <flux:table.column align="center" class="px-1 sm:px-2 md:px-4 py-2">Date</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        <flux:table.row>
                            <flux:table.cell colspan="6" class="text-center py-12">
                                <div class="flex flex-col items-center justify-center gap-2">
                                    <flux:icon.document-text class="w-8 h-8 text-light-txt-muted dark:text-dark-txt-muted" />
                                    <p class="font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">No card transactions yet.</p>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    </flux:table.rows>
                </flux:table>
            </flux:card>
        </div>
    </div>
</div>
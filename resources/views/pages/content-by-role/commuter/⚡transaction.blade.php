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
        </div>

        <div x-show="tab === 'active'" x-cloak class="space-y-3">
            <livewire:pages::content-by-role.commuter.active-renting-transaction />
        </div>

        <div x-show="tab === 'rent-transaction'" x-cloak class="space-y-4">
            <livewire:pages::partial.commuter-transaction-history />
        </div>
    </div>
</div>
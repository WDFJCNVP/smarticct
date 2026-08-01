<?php

use Livewire\Component;
use Livewire\Attributes\Layout;

new #[Layout('layouts.operator-layout')]class extends Component
{
    //
};
?>

<div>

    <x-pages-heading heading="Transactions" description="View and manage your transactions." />

    <div x-data="{ tab: 'active' }" class="space-y-6 mt-6">
        <div class="flex gap-6 border-b border-zinc-200 dark:border-zinc-700 text-sm justify-end">
            <button
                type="button"
                @click="tab = 'active'"
                :class="tab === 'active'
                    ? 'border-indigo-600 text-indigo-700 dark:text-indigo-400 font-medium'
                    : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300'"
                class="pb-3 border-b-2 transition-colors flex items-center gap-1.5 cursor-pointer"
            >
                Active Renting Transaction
            </button>

            <button
                type="button"
                @click="tab = 'rent-transaction'"
                :class="tab === 'rent-transaction'
                    ? 'border-indigo-600 text-indigo-700 dark:text-indigo-400 font-medium'
                    : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300'"
                class="pb-3 border-b-2 transition-colors cursor-pointer"
            >
                Renting Transaction History
            </button>

            <button
                type="button"
                @click="tab = 'card-history'"
                :class="tab === 'card-history'
                    ? 'border-indigo-600 text-indigo-700 dark:text-indigo-400 font-medium'
                    : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300'"
                class="pb-3 border-b-2 transition-colors cursor-pointer"
            >
                Card Transactions History
            </button>
        </div>

        <div x-show="tab === 'active'" x-cloak class="space-y-3">

            <livewire:pages::content-by-role.operator.active-renting-transaction/>

        </div>

        <div x-show="tab === 'rent-transaction'" x-cloak class="space-y-4">

            Renting Transaction History

        </div>

        <div x-show="tab === 'card-history'" x-cloak>

            Card Transactions History

        </div>

    </div>
</div>
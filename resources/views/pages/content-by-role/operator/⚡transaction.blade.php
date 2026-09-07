<?php

use Livewire\Component;
use Livewire\Attributes\Layout;

new #[Layout('layouts.operator-layout')]class extends Component
{
    //
};
?>

<div>
    <x-page-header
        heading="View and manage your transactions."
        class="mb-6"
    >
        {{-- No extra controls – keep it minimal --}}
    </x-page-header>

    {{-- Mobile-visible heading, since the page header above is desktop-only --}}
    <div class="sm:hidden mb-4 pb-4 border-b border-light-bd-default dark:border-dark-bd-default">
        <x-heading
            size="xl"
            class="!font-primary !font-bold !text-light-txt-primary dark:!text-dark-txt-primary"
            style="font-size: var(--text-page-title)"
        >
            Transactions
        </x-heading>
    </div>

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
        </div>

        <div x-show="tab === 'active'" x-cloak class="space-y-3">

            <livewire:pages::content-by-role.operator.active-renting-transaction/>

        </div>

        <div x-show="tab === 'rent-transaction'" x-cloak class="space-y-4">

           <livewire:pages::content-by-role.operator.transaction-history />

        </div>

    </div>
</div>
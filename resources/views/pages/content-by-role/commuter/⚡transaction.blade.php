<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;

use App\Models\RentalOffer;
use App\Models\TripRequest;
use App\Models\RentTransaction;

new #[Layout('layouts.commuter-layout')] class extends Component
{
    #[Computed]
    #[On('transaction-updated')]
    public function stats()
    {
        $activeOffers = RentalOffer::whereHas('post', fn ($q) => $q->where('user_id', auth()->id()))
            ->where('status', 'accept')
            ->count();

        $activeRequests = TripRequest::where('user_id', auth()->id())
            ->where('status', 'accept')
            ->count();

        $history = RentTransaction::where(function ($query) {
                $query->where('post_owner_id', auth()->id())
                      ->orWhere('interested_user_id', auth()->id());
            })
            ->whereIn('status', ['completed', 'cancelled']);

        return [
            'active'    => $activeOffers + $activeRequests,
            'completed' => $history->clone()->where('status', 'completed')->count(),
            'cancelled' => $history->clone()->where('status', 'cancelled')->count(),
        ];
    }
};
?>

<div>
    {{-- ====== PAGE HEADER (desktop only, clean mobile) ====== --}}
    <x-page-header
        heading="My Renting Transactions"
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
            My Renting Transactions
        </x-heading>
    </div>

    {{-- ===================== STATS (responsive grid) ===================== --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2 sm:gap-3 mb-6">
        <flux:card class="p-3 sm:p-4">
            <div class="flex items-center gap-1.5 sm:gap-2 mb-1.5">
                <div class="flex items-center justify-center w-6 h-6 sm:w-7 sm:h-7 rounded-lg bg-info/10 dark:bg-dark-info/20 shrink-0">
                    <flux:icon.chat-bubble-left-right class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-info dark:text-dark-info" />
                </div>
                <x-text class="font-secondary text-xs sm:text-stat-label text-light-txt-muted dark:text-dark-txt-muted">
                    Active
                </x-text>
            </div>
            <x-text class="font-primary text-stat-value font-bold text-info dark:text-dark-info block">
                {{ $this->stats['active'] }}
            </x-text>
        </flux:card>

        <flux:card class="p-3 sm:p-4">
            <div class="flex items-center gap-1.5 sm:gap-2 mb-1.5">
                <div class="flex items-center justify-center w-6 h-6 sm:w-7 sm:h-7 rounded-lg bg-success/10 dark:bg-dark-success/20 shrink-0">
                    <flux:icon.check-circle class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-success dark:text-dark-success" />
                </div>
                <x-text class="font-secondary text-xs sm:text-stat-label text-light-txt-muted dark:text-dark-txt-muted">
                    Completed
                </x-text>
            </div>
            <x-text class="font-primary text-stat-value font-bold text-success dark:text-dark-success block">
                {{ $this->stats['completed'] }}
            </x-text>
        </flux:card>

        <flux:card class="p-3 sm:p-4">
            <div class="flex items-center gap-1.5 sm:gap-2 mb-1.5">
                <div class="flex items-center justify-center w-6 h-6 sm:w-7 sm:h-7 rounded-lg bg-warning/10 dark:bg-dark-warning/20 shrink-0">
                    <flux:icon.x-circle class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-warning dark:text-dark-warning" />
                </div>
                <x-text class="font-secondary text-xs sm:text-stat-label text-light-txt-muted dark:text-dark-txt-muted">
                    Cancelled
                </x-text>
            </div>
            <x-text class="font-primary text-stat-value font-bold text-warning dark:text-dark-warning block">
                {{ $this->stats['cancelled'] }}
            </x-text>
        </flux:card>
    </div>

    <div x-data="{ tab: 'active' }" class="space-y-6">
        <div class="flex gap-4 sm:gap-6 border-b border-light-bd-default dark:border-dark-bd-default text-sm overflow-x-auto whitespace-nowrap -mx-1 px-1">
            <button
                type="button"
                @click="tab = 'active'"
                :class="tab === 'active'
                    ? 'border-primary text-primary dark:border-secondary dark:text-white font-medium'
                    : 'border-transparent text-light-txt-muted dark:text-dark-txt-muted hover:text-light-txt-primary dark:hover:text-dark-txt-primary'"
                class="pb-3 border-b-2 transition-colors flex items-center gap-1.5 cursor-pointer font-secondary shrink-0 text-xs sm:text-sm"
            >
                Active Renting Transaction
            </button>
            <button
                type="button"
                @click="tab = 'rent-transaction'"
                :class="tab === 'rent-transaction'
                    ? 'border-primary text-primary dark:border-secondary dark:text-white font-medium'
                    : 'border-transparent text-light-txt-muted dark:text-dark-txt-muted hover:text-light-txt-primary dark:hover:text-dark-txt-primary'"
                class="pb-3 border-b-2 transition-colors cursor-pointer font-secondary shrink-0 text-xs sm:text-sm"
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
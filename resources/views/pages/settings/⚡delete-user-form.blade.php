<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component {}; ?>

@if (Auth::user()->role === 'commuter')
    <section class="mt-10 space-y-6">
        <div class="relative mb-5">
            <flux:heading>{{ __('Delete account') }}</flux:heading>
            <flux:subheading>{{ __('Permanently delete your account. Your personal data will be wiped, your transaction history will be retained (anonymized) for other users, and any remaining card balance will be forfeited (it cannot be cashed out or refunded).') }}</flux:subheading>
        </div>

        <flux:modal.trigger name="confirm-user-deletion">
            <button
                type="button"
                data-test="delete-user-button"
                class="w-full inline-flex items-center justify-center gap-2 rounded-lg bg-danger hover:bg-danger/90 dark:bg-dark-danger dark:hover:bg-dark-danger/90 text-white font-secondary text-sm font-semibold px-4 py-2.5 transition-colors cursor-pointer"
            >
                {{ __('Delete account') }}
            </button>
        </flux:modal.trigger>

        <livewire:pages::settings.delete-user-modal />
    </section>
@endif
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
            <flux:button variant="danger" data-test="delete-user-button">
                {{ __('Delete account') }}
            </flux:button>
        </flux:modal.trigger>

        <livewire:pages::settings.delete-user-modal />
    </section>
@endif
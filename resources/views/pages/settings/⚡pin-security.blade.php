<?php

use App\Models\Card;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

new class extends Component
{
    private const MAX_ATTEMPTS = 5;

    public string $current_pin = '';
    public string $pin = '';
    public string $pin_confirmation = '';

    private function card(): ?Card
    {
        $card = auth()->user()->card;

        return $card && $card->status === 'active' ? $card : null;
    }

    public function updatePin(): void
    {
        $card = $this->card();

        if (! $card) {
            throw ValidationException::withMessages([
                'current_pin' => __('No active card found on your account.'),
            ]);
        }

        if ($card->pin_locked_at) {
            throw ValidationException::withMessages([
                'current_pin' => __('This card is locked after too many failed attempts. Please visit the service desk to reset your PIN.'),
            ]);
        }

        $this->validate([
            'current_pin' => ['required', 'digits:6'],
            'pin'         => ['required', 'digits:6', 'confirmed', 'different:current_pin'],
        ], [
            'current_pin.digits' => __('Your current PIN must be 6 digits.'),
            'pin.digits'         => __('Your new PIN must be 6 digits.'),
            'pin.confirmed'      => __('The PIN confirmation does not match.'),
            'pin.different'      => __('Your new PIN must be different from your current PIN.'),
        ]);

        // Reject trivial PINs (000000, 111111, 123456, ...)
        if (
            preg_match('/^(\d)\1{5}$/', $this->pin) ||
            in_array($this->pin, ['123456', '654321', '012345', '123123'], true)
        ) {
            throw ValidationException::withMessages([
                'pin' => __('That PIN is too easy to guess. Please choose another one.'),
            ]);
        }

        if (! Hash::check($this->current_pin, $card->pin)) {
            $attempts = $card->pin_attempts + 1;

            $card->update([
                'pin_attempts'  => $attempts,
                'pin_locked_at' => $attempts >= self::MAX_ATTEMPTS ? now() : null,
            ]);

            $remaining = max(self::MAX_ATTEMPTS - $attempts, 0);

            throw ValidationException::withMessages([
                'current_pin' => $remaining > 0
                    ? __('Incorrect PIN. :n attempt(s) remaining.', ['n' => $remaining])
                    : __('Too many failed attempts. Your card has been locked.'),
            ]);
        }

        // 'hashed' cast on Card::$pin hashes this on save
        $card->update([
            'pin'           => $this->pin,
            'pin_attempts'  => 0,
            'pin_locked_at' => null,
        ]);

        $this->reset('current_pin', 'pin', 'pin_confirmation');

        $this->dispatch('pin-updated');
    }

    public function render()
    {
        $role = auth()->user()->role;

        return $this->view([
            'card' => $this->card(),
        ])->layout('layouts.' . $role . '-layout');
    }
};
?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Security settings') }}</flux:heading>

    <x-pages::settings.layout
        :heading="__('Update PIN number')"
        :subheading="__('Change the 6-digit PIN used with your card at the kiosk')"
    >
        @if (! $card)
            <p class="mt-6 text-sm">{{ __('You do not have an active card registered yet.') }}</p>
        @elseif ($card->pin_locked_at)
            <p class="mt-6 text-sm text-red-600">
                {{ __('Your card is locked after too many failed PIN attempts. Please visit the service desk to reset your PIN.') }}
            </p>
        @else
            <form wire:submit="updatePin" class="mt-6 space-y-6">
                <flux:input wire:model="current_pin" :label="__('Current PIN')" type="password"
                    inputmode="numeric" maxlength="6" required autocomplete="off" viewable />
                <flux:input wire:model="pin" :label="__('New PIN')" type="password"
                    inputmode="numeric" maxlength="6" required autocomplete="off" viewable />
                <flux:input wire:model="pin_confirmation" :label="__('Confirm new PIN')" type="password"
                    inputmode="numeric" maxlength="6" required autocomplete="off" viewable />

                <div class="flex items-center gap-4">
                    <div class="flex items-center justify-end">
                        <flux:button variant="primary" type="submit" class="w-full" data-test="update-pin-button">
                            {{ __('Save') }}
                        </flux:button>
                    </div>

                    <x-action-message class="me-3" on="pin-updated">
                        {{ __('Saved.') }}
                    </x-action-message>
                </div>
            </form>
        @endif
    </x-pages::settings.layout>
</section>
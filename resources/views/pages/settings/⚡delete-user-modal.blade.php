<?php

use App\Concerns\PasswordValidationRules;
use App\Services\UserService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    use PasswordValidationRules;

    public string $password = '';

    #[Computed]
    public function blockers(): array
    {
        $user = Auth::user();

        if (!$user || $user->role !== 'commuter') {
            return [];
        }

        return app(UserService::class)->commuterDeletionBlockers($user);
    }

    #[Computed]
    public function forfeitableBalance(): float
    {
        $user = Auth::user();

        if (!$user || $user->role !== 'commuter') {
            return 0;
        }

        return app(UserService::class)->forfeitableBalance($user);
    }

    /**
     * Delete the currently authenticated commuter's own account.
     */
    public function deleteUser(Request $request): void
    {
        $user = Auth::user();

        if (!$user || $user->role !== 'commuter') {
            throw ValidationException::withMessages([
                'account' => 'Only commuters can delete their own account.',
            ]);
        }

        $this->validate([
            'password' => $this->currentPasswordRules(),
        ]);

        // Re-check eligibility right before deleting — state (posts,
        // transactions, balance) may have changed since the modal opened.
        app(UserService::class)->deleteOwnAccount($user);

        Auth::logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        $this->redirect('/', navigate: true);
    }
}; ?>

<flux:modal name="confirm-user-deletion" :show="$errors->isNotEmpty()" focusable class="max-w-lg">

    @if (!empty($this->blockers))
        <div class="space-y-4">
            <div>
                <flux:heading size="lg">{{ __('You can\'t delete your account yet') }}</flux:heading>
                <flux:subheading>
                    {{ __('Please resolve the following before your account can be permanently deleted:') }}
                </flux:subheading>
            </div>

            <ul class="list-disc pl-5 space-y-1 text-sm text-light-txt-body dark:text-dark-txt-primary">
                @foreach ($this->blockers as $blocker)
                    <li>{{ $blocker }}</li>
                @endforeach
            </ul>

            <div class="flex justify-end">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Close') }}</flux:button>
                </flux:modal.close>
            </div>
        </div>
    @else
        <form method="POST" wire:submit="deleteUser" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Are you sure you want to delete your account?') }}</flux:heading>

                <flux:subheading>
                    {{ __('Once deleted, your account and personal data will be wiped and cannot be recovered. Your transaction history will be retained (shown as "Deleted User") for the other users involved. Please enter your password to confirm.') }}
                </flux:subheading>
            </div>

            @if ($this->forfeitableBalance > 0)
                <flux:callout variant="warning" icon="exclamation-triangle">
                    <strong>{{ __('You still have ₱:balance in card balance.', ['balance' => number_format($this->forfeitableBalance, 2)]) }}</strong>
                    {{ __('This balance cannot be cashed out or refunded and will be forfeited if you proceed — it will not be transferred to any other account.') }}
                </flux:callout>
            @endif

            @error('account')
                <flux:callout variant="danger" icon="exclamation-triangle" heading="{{ $message }}" />
            @enderror

            <flux:input wire:model="password" :label="__('Password')" type="password" viewable />

            <div class="flex justify-end space-x-2 rtl:space-x-reverse">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button variant="danger" type="submit" data-test="confirm-delete-user-button">
                    {{ __('Delete account') }}
                </flux:button>
            </div>
        </form>
    @endif
</flux:modal>
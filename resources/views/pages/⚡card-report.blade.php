<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use App\Models\Card;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

new class extends Component
{
    public string $reason = '';
    public string $password = '';

    #[Computed]
    public function userCard(): ?Card
    {
        return Card::where('user_id', auth()->id())->first();
    }

    public function blockCard(): void
    {
        $this->validate([
            'reason'   => 'required|in:lost,damaged,other',
            'password' => 'required|string',
        ], [
            'reason.required'   => 'Please select a reason.',
            'password.required' => 'Please enter your password to confirm.',
        ]);

        $card = $this->userCard;

        if (!$card) {
            return;
        }

        if ($card->isSuspended()) {
            Flux::modal('confirm-block-card')->close();
            Flux::toast(variant: 'warning', duration: 4000, heading: 'Card already blocked.', text: 'Your card is already suspended.');
            return;
        }

        // Confirm the user's login password, with a simple attempt limit
        $key = 'block-card:' . auth()->id();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            $this->addError('password', "Too many attempts. Please try again in {$seconds} seconds.");
            return;
        }

        if (!Hash::check($this->password, auth()->user()->password)) {
            RateLimiter::hit($key, 60);
            $this->reset('password');
            $this->addError('password', 'The password you entered is incorrect.');
            return;
        }

        RateLimiter::clear($key);

        $card->update([
            'status'            => 'suspended',
            'suspension_reason' => 'Reported by cardholder: ' . $this->reason,
            'suspended_at'      => now(),
            'suspended_by'      => auth()->id(),
        ]);

        unset($this->userCard);
        $this->reset('reason', 'password');

        Flux::modal('confirm-block-card')->close();
        Flux::toast(
            variant: 'success',
            duration: 4000,
            heading: 'Card blocked.',
            text: 'Your card can no longer be used. Visit the terminal to get a replacement.',
        );
    }

    public function clearPassword(): void
    {
        $this->reset('password');
        $this->resetErrorBag('password');
    }

    public function render()
    {
        $role = auth()->user()->role;
        return $this->view()->layout('layouts.' . $role . '-layout');
    }
};
?>

<div>
    {{-- ====== PAGE HEADER (mini-navbar: heading left, notifications right) ====== --}}
    <x-page-header
        heading="Block Card"
        description="Lost or damaged your card? Block it instantly to keep your balance safe."
        class="mb-4"
    >
        {{-- No extra controls --}}
    </x-page-header>

    {{-- Mobile-visible heading, since the page header above is desktop-only --}}
    <div class="sm:hidden flex items-start justify-between gap-4 mb-6">
        <div>
            <x-heading
                size="xl"
                class="!font-primary !font-bold !text-light-txt-primary dark:!text-dark-txt-primary"
                style="font-size: var(--text-page-title)"
            >
                Block Card
            </x-heading>
            <x-text variant="subtle" class="!font-secondary mt-1 block" style="font-size: var(--text-helper)">
                Lost or damaged your card? Block it instantly to keep your balance safe.
            </x-text>
        </div>
    </div>

    <div class="mb-6">
        <flux:breadcrumbs>
            <flux:breadcrumbs.item href="{{ route('user.card') }}" wire:navigate>Back to My Card</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>Block Card</flux:breadcrumbs.item>
        </flux:breadcrumbs>
    </div>

    @if (!$this->userCard)
        <flux:card class="px-6 py-14 text-center dark:bg-dark-secondary dark:border-dark-bd-default">
            <flux:icon name="credit-card" class="w-10 h-10 text-light-txt-muted dark:text-dark-txt-muted mx-auto mb-3" />
            <p class="text-sm text-light-txt-muted dark:text-dark-txt-muted">No card linked to your account yet.</p>
            <p class="text-xs text-light-txt-muted dark:text-dark-txt-muted mt-1">Visit the terminal to get your RFID card issued.</p>
        </flux:card>
    @else
        <div class="max-w-2xl">
            <x-card class="!p-0 overflow-hidden">
                <div class="px-4 sm:px-5 py-3 border-b border-light-bd-default dark:border-dark-bd-default bg-light-secondary/50 dark:bg-dark-secondary/50">
                    <h3 class="font-primary text-card-title font-semibold text-light-txt-primary dark:text-dark-txt-primary">
                        Block Lost / Damaged Card
                    </h3>
                </div>

                @if ($this->userCard->isSuspended())
                    <div class="p-4 sm:p-6">
                        <div class="rounded-lg bg-danger/10 dark:bg-dark-danger/20 border border-danger/30 dark:border-dark-danger/30 px-4 py-3 flex items-start gap-3">
                            <flux:icon name="lock-closed" class="w-5 h-5 text-danger dark:text-dark-danger shrink-0 mt-0.5" />
                            <div>
                                <p class="text-sm font-medium text-danger dark:text-dark-danger">Your card is blocked</p>
                                <p class="text-xs text-danger/80 dark:text-dark-danger/80 mt-0.5">
                                    Visit the terminal and avail a new ICCT card from the cashier to complete the replacement.
                                </p>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="p-4 sm:p-6 space-y-5">
                        <flux:text class="font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">
                            Blocking your card takes effect immediately and it can no longer be used. Visit the terminal to get a replacement.
                        </flux:text>

                        <flux:field>
                            <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">
                                Reason
                            </flux:label>
                            <flux:select
                                wire:model.live="reason"
                                placeholder="Select a reason…"
                                size="sm"
                                class="font-secondary text-table-row bg-light-primary dark:bg-dark-surface text-light-txt-body dark:text-dark-txt-primary border-light-bd-default dark:border-dark-bd-default"
                            >
                                <flux:select.option value="lost">Lost</flux:select.option>
                                <flux:select.option value="damaged">Damaged</flux:select.option>
                                <flux:select.option value="other">Other</flux:select.option>
                            </flux:select>
                            <flux:error name="reason" class="font-secondary text-helper text-danger dark:text-dark-danger mt-1" />
                        </flux:field>

                        <div class="flex justify-end pt-2 border-t border-light-bd-default dark:border-dark-bd-default">
                            <flux:modal.trigger name="confirm-block-card">
                                <flux:button
                                    variant="danger"
                                    :disabled="!$reason"
                                    class="w-full sm:w-auto justify-center font-secondary"
                                >
                                    Block Card
                                </flux:button>
                            </flux:modal.trigger>
                        </div>
                    </div>
                @endif
            </x-card>
        </div>

        {{-- Confirmation modal (requires the account password) --}}
        <flux:modal name="confirm-block-card" class="min-w-[22rem] md:w-96" wire:close="clearPassword">
            <form wire:submit="blockCard" class="space-y-6">
                <div>
                    <flux:heading size="lg">Block this card?</flux:heading>
                    <flux:text class="mt-2">
                        Your card will be blocked immediately and can no longer be used for payments or top-ups.
                        Enter your account password to confirm.
                    </flux:text>
                </div>

                <flux:field>
                    <flux:label>Password</flux:label>
                    <flux:input
                        type="password"
                        wire:model="password"
                        viewable
                        autocomplete="current-password"
                        placeholder="Your login password"
                    />
                    <flux:error name="password" />
                </flux:field>

                <div class="flex gap-2">
                    <flux:spacer />
                    <flux:modal.close>
                        <flux:button type="button" variant="ghost">Cancel</flux:button>
                    </flux:modal.close>
                    <flux:button
                        type="submit"
                        variant="danger"
                        wire:loading.attr="disabled"
                        wire:target="blockCard"
                    >
                        <span wire:loading.remove wire:target="blockCard">Yes, block my card</span>
                        <span wire:loading wire:target="blockCard">Blocking…</span>
                    </flux:button>
                </div>
            </form>
        </flux:modal>
    @endif
</div>
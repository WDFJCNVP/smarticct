<?php

use Livewire\Component;
use Livewire\Attributes\On;

use App\Models\User;
use App\Mail\WelcomeUserMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

use App\Services\AuditLogsService;

new class extends Component
{
    public User $user;

    public function mount(User $user): void
    {
        $this->user = $user;
    }

    #[On('user-updated')]
    public function refreshUser(): void
    {
        $this->user->refresh();
    }

    /**
     * Give a no-account operator (a record imported from the terminal's old records)
     * a real account: generate a password and email the credentials to the address
     * already saved on the record.
     */
    public function createAccount(): void
    {
        if ($this->user->isDeleted()) {
            Flux::toast(
                variant: 'danger',
                duration: 4000,
                heading: 'Cannot edit this account.',
                text: 'This account was permanently deleted by the user and can no longer be modified.',
            );

            return;
        }

        if ($this->user->hasAccount()) {
            Flux::toast(
                variant: 'warning',
                duration: 4000,
                heading: 'Already has an account.',
                text: $this->user->name . ' already has an account.',
            );

            return;
        }

        $savedEmail = trim((string) $this->user->email_address);

        if ($savedEmail === '') {
            Flux::toast(
                variant: 'warning',
                duration: 5000,
                heading: 'Email address required.',
                text: 'Add an email address to this operator\'s record first, then click Create account.',
            );

            return;
        }

        $rawPassword = Str::password(10, true, true, true, false);

        try {
            // If the email can't be sent, the password change is rolled back,
            // so an account is never created without the operator receiving it.
            DB::transaction(function () use ($rawPassword, $savedEmail) {
                $this->user->update(['password' => $rawPassword]);

                Mail::to($savedEmail)->send(new WelcomeUserMail(
                    $this->user->name,
                    $savedEmail,
                    $rawPassword,
                    null // no RFID PIN yet: a card is issued separately, after the account exists
                ));
            });
        } catch (\Throwable $e) {
            report($e);
            $this->user->refresh();

            Flux::toast(
                variant: 'danger',
                duration: 6000,
                heading: 'Account not created.',
                text: 'The login credentials could not be emailed, so no account was created. Check the email address and try again.',
            );

            return;
        }

        $this->user->refresh();
        $this->dispatch('user-updated');

        app(AuditLogsService::class)->create([
            'user_id'  => auth()->id(),
            'action'   => 'Account Created',
            'subject'  => 'Admin created an account for an operator',
            'channel'  => 'Web',
            'metadata' => [
                'ip_address'  => request()->ip(),
                'operator_id' => $this->user->id,
                'message'     => "Created an account for operator {$this->user->name} (User No.: {$this->user->user_code}). Login credentials were emailed to the address on record.",
            ],
        ]);

        Flux::toast(
            variant: 'success',
            duration: 6000,
            heading: 'Account created.',
            text: 'The account was created and the login credentials were sent to ' . $savedEmail . '.',
        );
    }
};
?>

<div>
    @if (! $this->user->hasAccount())
        <flux:callout variant="secondary" icon="user-minus" class="mb-4">
            <flux:callout.heading>No account</flux:callout.heading>
            <flux:callout.text class="flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-4">
                <div class="text-sm sm:text-base">
                    This operator is a record only (for example, imported from the terminal's old records). They have no password, cannot log in, and cannot be issued an RFID card until they have an account. Their email, mobile number, vehicle details and franchise information may be incomplete.
                </div>
                <flux:button
                    size="sm"
                    icon="user-plus"
                    wire:click="createAccount"
                    wire:confirm="Create an account for {{ $user->name }}? The login credentials will be emailed to {{ $user->email_address ?: 'the address on their record' }}."
                    wire:loading.attr="disabled"
                    wire:target="createAccount"
                    class="w-full sm:w-auto shrink-0"
                >
                    Create account
                </flux:button>
            </flux:callout.text>
        </flux:callout>
    @endif

    @if ($this->user->hasAccount() && $this->user->card === null)
        <flux:callout variant="warning" icon="exclamation-circle">
            <flux:callout.heading>This user has no RFID card assigned</flux:callout.heading>
            <flux:callout.text class="flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-4">
                <div class="text-sm sm:text-base">
                    They won't be able to tap at the terminal until a card is issued and linked to their account.
                </div>
                <flux:button
                    size="sm"
                    icon="credit-card"
                    wire:click="$dispatch('open-card-panel')"
                    class="w-full sm:w-auto shrink-0"
                >
                    Issue card now
                </flux:button>
            </flux:callout.text>
        </flux:callout>
    @endif
</div>
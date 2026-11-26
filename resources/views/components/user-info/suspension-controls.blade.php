<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;

use App\Models\User;
use App\Services\UserService;

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

    #[Computed]
    public function getSuspensionWarnings(): array
    {
        return app(UserService::class)->activeSuspensionWarnings($this->user);
    }

    public string $suspension_reason_input = '';

    public function suspendUser() {
        if ($this->user->isDeleted()) {
            Flux::toast(
                variant: 'danger',
                duration: 4000,
                heading: 'Cannot edit this account.',
                text: 'This account was permanently deleted by the user and can no longer be modified.',
            );

            return;
        }

        $this->validate([
            'suspension_reason_input' => 'required|string|min:5|max:500',
        ], [], [
            'suspension_reason_input' => 'suspension reason',
        ]);

        app(UserService::class)->suspend($this->user, $this->suspension_reason_input);

        $this->user->refresh();
        $this->dispatch('user-updated');
        $this->suspension_reason_input = '';
        $this->modal('suspend-user')->close();

        Flux::toast(
            variant: 'success',
            duration: 4000,
            heading: 'User suspended',
            text: "{$this->user->name} has been suspended and signed out.",
        );
    }

    public function reinstateUser() {
        if ($this->user->isDeleted()) {
            Flux::toast(
                variant: 'danger',
                duration: 4000,
                heading: 'Cannot edit this account.',
                text: 'This account was permanently deleted by the user and can no longer be modified.',
            );

            return;
        }

        app(UserService::class)->reinstate($this->user);

        $this->user->refresh();
        $this->dispatch('user-updated');
        $this->modal('reinstate-user')->close();

        Flux::toast(
            variant: 'success',
            duration: 4000,
            heading: 'User reinstated',
            text: "{$this->user->name}'s account has been reactivated.",
        );
    }
};
?>

<div class="w-full sm:w-auto order-2 sm:order-1">
                    {{-- Admins can only suspend accounts — never delete them.
                         A commuter who has permanently deleted their own
                         account can no longer be suspended/reinstated. --}}
                    @if ($user->isDeleted())
                        <flux:badge color="zinc" size="sm" class="font-secondary text-badge text-xs w-full sm:w-auto text-center order-2 sm:order-1">
                            Account permanently deleted by user
                        </flux:badge>
                    @elseif ($user->isSuspended())
                        <flux:modal.trigger name="reinstate-user">
                            <flux:button
                                type="button"
                                variant="ghost"
                                size="sm"
                                class="text-success dark:text-dark-success border-success/30 dark:border-dark-success/30 hover:bg-success/10 dark:hover:bg-dark-success/10 w-full sm:w-auto order-2 sm:order-1"
                                icon="check-circle"
                            >Reinstate user</flux:button>
                        </flux:modal.trigger>
                    @else
                        <flux:modal.trigger name="suspend-user">
                            <flux:button
                                type="button"
                                variant="ghost"
                                size="sm"
                                class="text-warning dark:text-dark-warning border-warning/30 dark:border-dark-warning/30 hover:bg-warning/10 dark:hover:bg-dark-warning/10 w-full sm:w-auto order-2 sm:order-1"
                                icon="no-symbol"
                            >Suspend user</flux:button>
                        </flux:modal.trigger>
                    @endif

    {{-- Suspend-user confirmation modal --}}
    <flux:modal
        name="suspend-user"
        :closable="false"
        class="w-[calc(100%-2rem)] sm:max-w-md mx-auto rounded-xl overflow-hidden"
    >
        <div class="flex flex-col p-4 sm:p-6 !pr-4 sm:!pr-6 space-y-5">
            <div class="flex items-start justify-between">
                <div>
                    <flux:heading size="xl" class="!font-primary !font-bold text-light-txt-primary dark:text-dark-txt-primary">
                        Suspend user?
                    </flux:heading>
                    <flux:text class="mt-1 font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">
                        <strong>{{ $user->name }}</strong> will be signed out immediately and won't be able to log in
                        @if ($user->role === 'operator')
                            or queue their vehicle
                        @endif
                        until this suspension is lifted. They'll need to visit the terminal office in person to have their account reviewed.
                    </flux:text>
                </div>
                <flux:modal.close>
                    <button type="button" class="p-1 rounded-full hover:bg-light-subtle dark:hover:bg-dark-subtle text-light-txt-muted dark:text-dark-txt-muted -mt-1">
                        <flux:icon name="x-mark" class="w-5 h-5" />
                    </button>
                </flux:modal.close>
            </div>

            <div>
                <flux:textarea
                    wire:model="suspension_reason_input"
                    label="Reason for suspension"
                    placeholder="e.g. Expired franchise, reported misuse..."
                    rows="3"
                    class="font-secondary text-table-row bg-light-primary dark:bg-dark-surface text-light-txt-body dark:text-dark-txt-primary border-light-bd-default dark:border-dark-bd-default placeholder:text-light-txt-muted dark:placeholder:text-dark-txt-muted"
                />
                <flux:error name="suspension_reason_input" />
            </div>

            @if (!empty($this->getSuspensionWarnings))
                <flux:callout variant="warning" icon="exclamation-triangle">
                    <flux:callout.heading>This account currently has activity in progress</flux:callout.heading>
                    <flux:callout.text>
                        <ul class="list-disc list-inside space-y-0.5">
                            @foreach ($this->getSuspensionWarnings as $warning)
                                <li>{{ $warning }}</li>
                            @endforeach
                        </ul>
                        <p class="mt-1">You can still suspend the account — this is just so you're aware before confirming.</p>
                    </flux:callout.text>
                </flux:callout>
            @endif

            <div class="flex flex-col-reverse sm:flex-row justify-end items-stretch sm:items-center gap-2 pt-2 border-t border-light-bd-default dark:border-dark-bd-default">
                <flux:modal.close class="w-full sm:w-auto">
                    <flux:button type="button" variant="ghost" class="w-full sm:w-auto justify-center font-secondary">
                        Cancel
                    </flux:button>
                </flux:modal.close>
                <flux:button
                    type="button"
                    variant="danger"
                    wire:click="suspendUser"
                    wire:loading.attr="disabled"
                    wire:target="suspendUser"
                    class="w-full sm:w-auto justify-center font-secondary"
                >
                    <span wire:loading.remove wire:target="suspendUser">Yes, suspend user</span>
                    <span wire:loading wire:target="suspendUser">Suspending…</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Reinstate-user confirmation modal --}}
    <flux:modal name="reinstate-user" class="md:w-96">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg" class="!font-primary !font-bold text-light-txt-primary dark:text-dark-txt-primary">
                    Reinstate user?
                </flux:heading>
                <flux:text class="mt-1 font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">
                    Only confirm this after <strong>{{ $user->name }}</strong> has submitted their required documents
                    (e.g. updated franchise) in person and they've been verified. Their account will be reactivated immediately.
                </flux:text>
            </div>

            <div class="flex flex-col sm:flex-row justify-end gap-2 pt-2 border-t border-light-bd-default dark:border-dark-bd-default">
                <flux:modal.close>
                    <flux:button type="button" variant="ghost" class="font-secondary w-full sm:w-auto">
                        Cancel
                    </flux:button>
                </flux:modal.close>
                <flux:button
                    type="button"
                    variant="primary"
                    icon="check-circle"
                    wire:click="reinstateUser"
                    wire:loading.attr="disabled"
                    wire:target="reinstateUser"
                    class="font-secondary w-full sm:w-auto"
                >
                    Yes, reinstate user
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
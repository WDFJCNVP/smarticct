<?php

use App\Concerns\ProfileValidationRules;
use App\Models\Notification;
use App\Models\UserNotification;
use App\Events\NotificationEvent;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Profile settings')] class extends Component {
    use ProfileValidationRules;

    public string $name = '';
    // public string $username = '';
    public string $email_address = '';
    public string $phone_number = '';
    public string $address = '';

    // Address modal fields (mirrors admin edit-user page)
    public string $house_subd = '';
    public ?int $zone_number = null;
    public string $municipality = '';
    public string $barangay = '';
    public string $province = '';      // <-- NEW

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $user = Auth::user();

        $this->name          = $user->name;
        // $this->username      = $user->username;
        $this->email_address = $user->email_address ?? '';
        $this->phone_number  = $user->phone_number ?? '';
        $this->address       = $user->address ?? '';
    }

    /**
     * Parse the current address string and fill the modal fields.
     * Expected format: "House/Subd, Zone X, Barangay, Municipality, Province"
     */
    public function prepareAddressModal(): void
    {
        $parts = array_map('trim', explode(',', $this->address ?? ''));

        $this->house_subd   = '';
        $this->zone_number  = null;
        $this->barangay     = '';
        $this->municipality = '';
        $this->province     = '';

        if (count($parts) >= 5) {
            $this->province     = $parts[4] ?? '';
            $this->municipality = $parts[3] ?? '';
            $this->barangay     = $parts[2] ?? '';
            $zonePart = $parts[1] ?? '';
            if (preg_match('/Zone\s+(\d+)/i', $zonePart, $matches)) {
                $this->zone_number = (int) $matches[1];
            }
            $this->house_subd   = $parts[0] ?? '';
        }
        // If address doesn't match the expected format, fields stay empty
    }

    /**
     * Notify the currently authenticated user that they successfully
     * updated their own account (as opposed to an admin updating it for
     * them, which already has its own notification in UserService).
     */
    private function notifySelfUpdate(string $title, string $message): void
    {
        $notification = Notification::create([
            'type'    => 'Update',
            'title'   => $title,
            'message' => $message,
        ]);

        UserNotification::create([
            'notification_id' => $notification->id,
            'user_id'         => Auth::id(),
        ]);

        broadcast(new NotificationEvent());
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        // Get rules from trait
        $rules = $this->profileRules($user->id);

        // Remove 'username' – it's not used in this component
        unset($rules['username']);

        // Rename 'email' to 'email_address' to match the component property
        if (isset($rules['email'])) {
            $rules['email_address'] = $rules['email'];
            unset($rules['email']);
        }

        // Add phone number validation
        $rules['phone_number'] = 'required|string|regex:/^09\d{9}$/';

        $validated = $this->validate($rules, [
            'phone_number.regex' => 'Enter a valid mobile number (e.g. 09171234567).',
        ]);

        $user->fill($validated);

        // This check is now harmless because 'username' is never set,
        // but it doesn't cause errors.
        if ($user->isDirty('username')) {
            $user->email_verified_at = null;
        }

        $user->save();

        $this->notifySelfUpdate(
            'Profile Updated',
            'You\'ve successfully updated your personal information.'
        );

        Flux::toast(
            variant: 'success',
            duration: 4000,
            heading: 'Changes saved.',
            text: 'Your profile has been updated.',
        );
    }

    /**
     * Save the formatted address (from the address modal) to the user record.
     */
    public function saveAddress(): void
    {
        $data = $this->validate([
            'house_subd'   => 'nullable|string|max:255',
            'zone_number'  => 'required|integer|min:1|max:20',
            'municipality' => 'required|string|max:255',
            'barangay'     => 'required|string|max:255',
            'province'     => 'required|string|max:255',    // <-- NEW
        ]);

        $parts = array_filter([
            $data['house_subd'] !== '' ? $data['house_subd'] : null,
            'Zone ' . $data['zone_number'],
            $data['barangay'],
            $data['municipality'],
            $data['province'],                             // <-- REPLACED hardcoded
        ]);

        $this->address = implode(', ', $parts);

        Auth::user()->update(['address' => $this->address]);

        $this->notifySelfUpdate(
            'Address Updated',
            'You\'ve successfully updated your address.'
        );

        $this->resetValidation();
        $this->dispatch('address-saved');

        Flux::toast(
            variant: 'success',
            duration: 4000,
            heading: 'Address saved.',
            text: 'Your address has been updated.',
        );
    }

    /**
     * Send an email verification notification to the current user.
     */
    // public function resendVerificationNotification(): void
    // {
    //     $user = Auth::user();

    //     if ($user->hasVerifiedEmail()) {
    //         $this->redirectIntended(default: route('dashboard', absolute: false));

    //         return;
    //     }

    //     $user->sendEmailVerificationNotification();

    //     Session::flash('status', 'verification-link-sent');
    // }

    // #[Computed]
    // public function hasUnverifiedEmail(): bool
    // {
    //     return Auth::user() instanceof MustVerifyEmail && ! Auth::user()->hasVerifiedEmail();
    // }

    #[Computed]
    public function showDeleteUser(): bool
    {
        // Only commuters may delete their own account. Admins can only
        // suspend accounts (never delete), and cashiers/operators can
        // never delete their own accounts.
        if (Auth::user()->role !== 'commuter') {
            return false;
        }

        return ! Auth::user() instanceof MustVerifyEmail
            || (Auth::user() instanceof MustVerifyEmail && Auth::user()->hasVerifiedEmail());
    }

    public function render() {
        $role = auth()->user()->role;

        return $this->view()->layout('layouts.' . $role . '-layout');
    }

}; ?>

<section>

    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Profile settings') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Profile')" :subheading="__('Update your name and email address')">

        <form wire:submit="updateProfileInformation" class="my-6 w-full space-y-6">

            <x-inputs-container>

                <flux:input wire:model="name" :label="__('Name')" type="text" required autofocus autocomplete="name" />

                {{-- <flux:input wire:model="username" :label="__('Username')" type="text" required autocomplete="username" /> --}}

                    <flux:input
                        wire:model="email_address"
                        :label="__('Email')"
                        type="email"
                        autocomplete="email"
                        placeholder="you@example.com (optional)"
                    />

                        {{-- @if ($this->hasUnverifiedEmail)
                            <div>
                                <flux:text class="mt-4">
                                    {{ __('Your email address is unverified.') }}

                                    <flux:link class="text-sm cursor-pointer" wire:click.prevent="resendVerificationNotification">
                                        {{ __('Click here to re-send the verification email.') }}
                                    </flux:link>
                                </flux:text>

                                @if (session('status') === 'verification-link-sent')
                                    <flux:text class="mt-2 font-medium !dark:text-green-400 !text-green-600">
                                        {{ __('A new verification link has been sent to your email address.') }}
                                    </flux:text>
                                @endif
                            </div>
                        @endif --}}

                <flux:input
                    wire:model="phone_number"
                    :label="__('Mobile number')"
                    type="tel"
                    required
                    autocomplete="tel"
                    placeholder="09XXXXXXXXX"
                />

                @if (auth()->user()->role === 'commuter')
                    <flux:input
                        label="Commuter type"
                        value="{{ ucfirst(auth()->user()->commuter_type) }}"
                        icon:trailing="lock-closed"
                        readonly
                    />
                @endif

            </x-inputs-container>


            <flux:field>
                <flux:label>{{ __('Address') }}</flux:label>
                <flux:modal.trigger name="address-modal">
                    <button
                        type="button"
                        wire:click="prepareAddressModal"
                        class="w-full text-left font-secondary text-table-row bg-light-primary dark:bg-dark-surface text-light-txt-body dark:text-dark-txt-primary border border-light-bd-default dark:border-dark-bd-default rounded-lg px-3 py-2.5 transition-shadow duration-200 focus:outline-none focus:ring-2 focus:ring-secondary/50"
                    >
                        @if ($address)
                            {{ $address }}
                        @else
                            <span class="text-light-txt-muted dark:text-dark-txt-muted">Tap to set address</span>
                        @endif
                    </button>
                </flux:modal.trigger>
                <flux:error name="address" class="font-secondary text-helper text-danger dark:text-dark-danger mt-1" />
            </flux:field>

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit" class="w-full" data-test="update-profile-button">
                    {{ __('Save') }}
                </flux:button>
            </div>
        </form>

        @if ($this->showDeleteUser)
            <livewire:pages::settings.delete-user-form />
        @endif
        
    </x-pages::settings.layout>

    {{-- Address modal – UPDATED with Province field and restyled --}}
    <flux:modal
        name="address-modal"
        :closable="false"
        class="w-[calc(100%-2rem)] sm:max-w-lg md:max-w-2xl lg:max-w-3xl mx-auto rounded-xl overflow-hidden"
        x-on:address-saved.window="$flux.modal('address-modal').close()"
    >
        <div class="flex flex-col p-4 sm:p-6 !pr-4 sm:!pr-6 space-y-5 overflow-y-auto max-h-[70vh]">
            <div class="flex items-start justify-between">
                <div>
                    <flux:heading size="xl" class="!font-primary !font-bold text-light-txt-primary dark:text-dark-txt-primary">
                        Set your address
                    </flux:heading>
                    <flux:text class="mt-1 font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">
                        Please provide your complete address.
                    </flux:text>
                </div>
                <flux:modal.close>
                    <button type="button" class="p-1 rounded-full hover:bg-light-subtle dark:hover:bg-dark-subtle text-light-txt-muted dark:text-dark-txt-muted -mt-1">
                        <flux:icon name="x-mark" class="w-5 h-5" />
                    </button>
                </flux:modal.close>
            </div>

            <flux:field>
                <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">
                    House No. / Subdivision
                    <span class="ml-2 text-light-txt-muted dark:text-dark-txt-muted font-normal">(optional)</span>
                </flux:label>
                <flux:input
                    wire:model="house_subd"
                    placeholder="e.g. Blk 3 Lot 5, Hillside Subd."
                    class="font-secondary text-table-row bg-light-primary dark:bg-dark-surface text-light-txt-body dark:text-dark-txt-primary border-light-bd-default dark:border-dark-bd-default placeholder:text-light-txt-muted dark:placeholder:text-dark-txt-muted"
                />
                <flux:error name="house_subd" class="font-secondary text-helper text-danger dark:text-dark-danger mt-1" />
            </flux:field>

            <flux:field>
                <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">Zone No.</flux:label>
                <flux:input
                    type="number"
                    wire:model="zone_number"
                    min="1"
                    max="20"
                    placeholder="e.g. 3"
                    class="font-secondary text-table-row bg-light-primary dark:bg-dark-surface text-light-txt-body dark:text-dark-txt-primary border-light-bd-default dark:border-dark-bd-default placeholder:text-light-txt-muted dark:placeholder:text-dark-txt-muted"
                />
                <flux:error name="zone_number" class="font-secondary text-helper text-danger dark:text-dark-danger mt-1" />
            </flux:field>

            <flux:field>
                <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">Barangay</flux:label>
                <flux:input
                    wire:model="barangay"
                    placeholder="e.g. San Roque"
                    class="font-secondary text-table-row bg-light-primary dark:bg-dark-surface text-light-txt-body dark:text-dark-txt-primary border-light-bd-default dark:border-dark-bd-default placeholder:text-light-txt-muted dark:placeholder:text-dark-txt-muted"
                />
                <flux:error name="barangay" class="font-secondary text-helper text-danger dark:text-dark-danger mt-1" />
            </flux:field>

            <flux:field>
                <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">Municipality / City</flux:label>
                <flux:input
                    wire:model="municipality"
                    placeholder="e.g. Iriga City"
                    class="font-secondary text-table-row bg-light-primary dark:bg-dark-surface text-light-txt-body dark:text-dark-txt-primary border-light-bd-default dark:border-dark-bd-default placeholder:text-light-txt-muted dark:placeholder:text-dark-txt-muted"
                />
                <flux:error name="municipality" class="font-secondary text-helper text-danger dark:text-dark-danger mt-1" />
            </flux:field>

            {{-- NEW Province field --}}
            <flux:field>
                <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">Province</flux:label>
                <flux:input
                    wire:model="province"
                    placeholder="e.g. Camarines Sur"
                    class="font-secondary text-table-row bg-light-primary dark:bg-dark-surface text-light-txt-body dark:text-dark-txt-primary border-light-bd-default dark:border-dark-bd-default placeholder:text-light-txt-muted dark:placeholder:text-dark-txt-muted"
                />
                <flux:error name="province" class="font-secondary text-helper text-danger dark:text-dark-danger mt-1" />
            </flux:field>

            <div class="flex flex-col sm:flex-row justify-end gap-2 pt-2 border-t border-light-bd-default dark:border-dark-bd-default">
                <flux:modal.close>
                    <flux:button type="button" variant="ghost" class="font-secondary w-full sm:w-auto">
                        Cancel
                    </flux:button>
                </flux:modal.close>
                <flux:button
                    type="button"
                    variant="primary"
                    icon="check"
                    wire:click="saveAddress"
                    wire:loading.attr="disabled"
                    wire:target="saveAddress"
                    class="font-secondary w-full sm:w-auto"
                >
                    Save address
                </flux:button>
            </div>
        </div>
    </flux:modal>
</section>
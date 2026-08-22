<?php

use App\Concerns\ProfileValidationRules;
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
     */
    public function prepareAddressModal(): void
    {
        // Expected format: "House/Subd, Zone X, Barangay, Municipality, Camarines Sur"
        $parts = array_map('trim', explode(',', $this->address ?? ''));

        // Default empty values
        $this->house_subd   = '';
        $this->zone_number  = null;
        $this->barangay     = '';
        $this->municipality = '';

        if (count($parts) >= 5) {
            // The last part is always "Camarines Sur"
            $this->municipality = $parts[3] ?? '';
            $this->barangay     = $parts[2] ?? '';
            // Zone part: e.g. "Zone 3" -> extract number
            $zonePart = $parts[1] ?? '';
            if (preg_match('/Zone\s+(\d+)/i', $zonePart, $matches)) {
                $this->zone_number = (int) $matches[1];
            }
            $this->house_subd   = $parts[0] ?? '';
        } else {
            // Fallback: if address doesn't match expected format, leave fields empty
            // so the user can re‑enter everything.
        }
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $rules = array_merge($this->profileRules($user->id), [
            'phone_number' => 'required|string|regex:/^09\d{9}$/',
        ]);

        $validated = $this->validate($rules, [
            'phone_number.regex' => 'Enter a valid mobile number (e.g. 09171234567).',
        ]);

        $user->fill($validated);

        if ($user->isDirty('username')) {
            $user->email_verified_at = null;
        }

        $user->save();

        Flux::toast(
            variant: 'success',
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
        ]);

        $parts = array_filter([
            $data['house_subd'] !== '' ? $data['house_subd'] : null,
            'Zone ' . $data['zone_number'],
            $data['barangay'],
            $data['municipality'],
            'Camarines Sur',
        ]);

        $this->address = implode(', ', $parts);

        Auth::user()->update(['address' => $this->address]);

        $this->resetValidation();
        $this->dispatch('address-saved');

        Flux::toast(
            variant: 'success',
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
                <flux:error name="address" />
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

    <flux:modal name="address-modal" class="md:w-[26rem]" x-on:address-saved.window="$flux.modal('address-modal').close()">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg" class="!font-primary !font-bold text-light-txt-primary dark:text-dark-txt-primary">
                    Set address
                </flux:heading>
                <flux:text class="mt-1 font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">
                    Please provide the complete address within Camarines Sur.
                </flux:text>
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
                <flux:error name="house_subd" />
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
                <flux:error name="zone_number" />
            </flux:field>

            <flux:field>
                <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">Municipality / City</flux:label>
                <flux:input
                    wire:model="municipality"
                    placeholder="e.g. Iriga City"
                    class="font-secondary text-table-row bg-light-primary dark:bg-dark-surface text-light-txt-body dark:text-dark-txt-primary border-light-bd-default dark:border-dark-bd-default placeholder:text-light-txt-muted dark:placeholder:text-dark-txt-muted"
                />
                <flux:error name="municipality" />
            </flux:field>

            <flux:field>
                <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">Barangay</flux:label>
                <flux:input
                    wire:model="barangay"
                    placeholder="e.g. San Roque"
                    class="font-secondary text-table-row bg-light-primary dark:bg-dark-surface text-light-txt-body dark:text-dark-txt-primary border-light-bd-default dark:border-dark-bd-default placeholder:text-light-txt-muted dark:placeholder:text-dark-txt-muted"
                />
                <flux:error name="barangay" />
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
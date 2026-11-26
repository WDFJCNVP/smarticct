<?php

use Livewire\Component;
use Livewire\Attributes\On;

use App\Models\User;
use App\Mail\EmailChangeOtpMail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

use App\Services\UserService;
use App\Services\AuditLogsService;

new class extends Component
{
    public User $user;

    public $name;
    public $email_address;
    public $phone_number;
    public $address;

    // Email OTP verification
    public string $otp_code = '';
    public ?string $otp_target_email = null; // display only; the trusted copy lives in the cache

    private const OTP_MAX_ATTEMPTS  = 5;   // wrong guesses before the code is burned
    private const OTP_RESEND_SECS   = 60;  // cooldown between sends
    private const OTP_MAX_SENDS_HR  = 5;   // sends per user per hour

    // Address modal fields
    public string $house_subd = '';
    public ?int $zone_number = null;
    public string $municipality = '';
    public string $barangay = '';
    public string $province = '';      // <-- NEW

    protected $validationAttributes = [
        'name'          => 'name',
        'email_address' => 'email address',
        'phone_number'  => 'phone number',
        'address'       => 'address',
        'otp_code'      => 'verification code',
    ];

    public function mount(User $user): void
    {
        $this->user          = $user;
        $this->name          = $user->name;
        $this->email_address = $user->email_address ?? '';
        $this->phone_number  = $user->phone_number ?? '';
        $this->address       = $user->address;
    }

    #[On('user-updated')]
    public function refreshUser(): void
    {
        $this->user->refresh();
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
        $this->resetValidation();

        $this->dispatch('address-saved');
    }

    /**
     * Validation rules for the personal-information form.
     */
    protected function profileRules(): array
    {
        // The email is the login identity, so it stays required for accounts that can
        // log in (clearing it would lock them out). No-account operators, imported from
        // the terminal's old records, may have these blank.
        $hasAccount = $this->user->hasAccount();

        return [
            'name'          => 'required|min:2|string',
            'email_address' => ($hasAccount ? 'required' : 'nullable') . '|string|email|max:255|unique:users,email_address,' . $this->user->id,
            'phone_number'  => ($hasAccount ? 'required' : 'nullable') . '|string|regex:/^09\d{9}$/',
            'address'       => ($hasAccount ? 'required|min:1' : 'nullable') . '|string',
        ];
    }

    protected function profileMessages(): array
    {
        return [
            'phone_number.regex' => 'Enter a valid mobile number (e.g. 09171234567).',
        ];
    }

    protected function guardNotDeleted(): bool
    {
        if (! $this->user->isDeleted()) {
            return true;
        }

        Flux::toast(
            variant: 'danger',
            duration: 4000,
            heading: 'Cannot edit this account.',
            text: 'This account was permanently deleted by the user and can no longer be modified.',
        );

        return false;
    }

    public function save() {
        if (! $this->guardNotDeleted()) {
            return;
        }

        $attributes = $this->validate($this->profileRules(), $this->profileMessages());

        // Store blanks as NULL, never as an empty string.
        $attributes['email_address'] = trim((string) $attributes['email_address']) ?: null;

        $newEmail = $attributes['email_address'];
        $oldEmail = $this->user->email_address;

        // Case 1 (changing an existing email) and Case 2 (adding an email to a record
        // that has none): the NEW address must be proven first. Nothing is saved until
        // the OTP sent to that address is confirmed.
        $emailChanged = $newEmail !== null
            && strtolower($newEmail) !== strtolower(trim((string) $oldEmail));

        if ($emailChanged) {
            $this->sendEmailOtp($newEmail);

            return;
        }

        $this->persistProfile($attributes);
    }

    /**
     * Write the validated profile data and show the success toast.
     */
    protected function persistProfile(array $attributes): void
    {
        $attributes['phone_number'] = $attributes['phone_number'] ?: null;
        $attributes['address']      = $attributes['address'] ?: null;

        app(UserService::class)->update($this->user, $attributes);

        $this->dispatch('user-updated');

        Flux::toast(
            variant: 'success',
            duration: 4000,
            heading: 'Changes saved.',
            text: 'Your changes have been saved.'
        );
    }

    // ───────────────────────── Email OTP ─────────────────────────

    protected function otpCacheKey(): string
    {
        return 'email-otp:user:' . $this->user->id;
    }

    protected function otpHash(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }

    /**
     * Generate an OTP, email it to $email, remember it server-side and open the modal.
     */
    protected function sendEmailOtp(string $email): bool
    {
        $sendKey = 'email-otp-send:' . $this->user->id;

        if (RateLimiter::tooManyAttempts($sendKey, self::OTP_MAX_SENDS_HR)) {
            Flux::toast(
                variant: 'warning',
                duration: 5000,
                heading: 'Too many codes requested.',
                text: 'Please wait ' . ceil(RateLimiter::availableIn($sendKey) / 60) . ' minute(s) before requesting another code.',
            );

            return false;
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        try {
            // Send first: if the mail fails, no pending code is stored.
            Mail::to($email)->send(new EmailChangeOtpMail($this->user->name, $code));
        } catch (\Throwable $e) {
            report($e);

            Flux::toast(
                variant: 'danger',
                duration: 6000,
                heading: 'Verification code not sent.',
                text: 'We could not email a code to ' . $email . '. Check the address and try again.',
            );

            return false;
        }

        RateLimiter::hit($sendKey, 3600);
        RateLimiter::hit('email-otp-cooldown:' . $this->user->id, self::OTP_RESEND_SECS);

        // The trusted pending email lives here, NOT in a public property
        // (public properties can be tampered with from the browser).
        // No expiry: the code stays valid until it is verified, cancelled, replaced
        // by a resend, or burned by too many wrong attempts.
        Cache::forever($this->otpCacheKey(), [
            'email'    => $email,
            'hash'     => $this->otpHash($code),
            'attempts' => 0,
        ]);

        $this->otp_code         = '';
        $this->otp_target_email = $email;
        $this->resetErrorBag('otp_code');

        Flux::modal('email-otp-modal')->show();

        return true;
    }

    public function resendEmailOtp(): void
    {
        if (! $this->guardNotDeleted()) {
            return;
        }

        $pending = Cache::get($this->otpCacheKey());

        if (! $pending) {
            $this->addError('otp_code', 'No pending code found. Close this window and click Save changes again.');

            return;
        }

        $coolKey = 'email-otp-cooldown:' . $this->user->id;

        if (RateLimiter::tooManyAttempts($coolKey, 1)) {
            Flux::toast(
                variant: 'warning',
                duration: 3000,
                heading: 'Please wait.',
                text: 'You can request a new code in ' . RateLimiter::availableIn($coolKey) . ' second(s).',
            );

            return;
        }

        if ($this->sendEmailOtp($pending['email'])) {
            Flux::toast(
                variant: 'success',
                duration: 3000,
                heading: 'New code sent.',
                text: 'A new verification code was sent to ' . $pending['email'] . '.',
            );
        }
    }

    public function verifyEmailOtp(): void
    {
        if (! $this->guardNotDeleted()) {
            return;
        }

        $this->validate(['otp_code' => 'required|digits:6']);

        $key     = $this->otpCacheKey();
        $pending = Cache::get($key);

        if (! $pending) {
            $this->addError('otp_code', 'No pending code found. Close this window and click Save changes again.');

            return;
        }

        if ($pending['attempts'] >= self::OTP_MAX_ATTEMPTS) {
            Cache::forget($key);
            $this->addError('otp_code', 'Too many incorrect attempts. Close this window and click Save changes to get a new code.');

            return;
        }

        if (! hash_equals($pending['hash'], $this->otpHash($this->otp_code))) {
            $pending['attempts']++;
            Cache::forever($key, $pending);

            $left = self::OTP_MAX_ATTEMPTS - $pending['attempts'];
            $this->addError('otp_code', $left > 0
                ? "Incorrect code. {$left} attempt(s) left."
                : 'Too many incorrect attempts. Close this window and click Save changes to get a new code.');

            return;
        }

        // Code is correct. Use the email that was VERIFIED (from the cache), not
        // whatever is in the form now, then re-run the normal validation (this also
        // re-checks that the address was not taken in the meantime).
        $this->email_address = $pending['email'];

        $attributes = $this->validate($this->profileRules(), $this->profileMessages());
        $attributes['email_address'] = $pending['email'];

        $oldEmail = $this->user->email_address;

        Cache::forget($key);
        $this->persistProfile($attributes);

        $this->user->refresh();
        $this->email_address = $this->user->email_address ?? '';

        app(AuditLogsService::class)->create([
            'user_id'  => auth()->id(),
            'action'   => $oldEmail ? 'Email Changed' : 'Email Added',
            'subject'  => $oldEmail ? 'Admin changed a user\'s email address (OTP verified)' : 'Admin added an email address to a user (OTP verified)',
            'channel'  => 'Web',
            'metadata' => [
                'ip_address' => request()->ip(),
                'user_id'    => $this->user->id,
                'message'    => "Email for {$this->user->name} (User No.: {$this->user->user_code}) was " . ($oldEmail ? "changed from {$oldEmail} to " : 'set to ') . "{$pending['email']} after OTP verification.",
            ],
        ]);

        $this->otp_code         = '';
        $this->otp_target_email = null;

        Flux::modal('email-otp-modal')->close();
    }

    /**
     * Admin closed the OTP window: discard the pending change.
     */
    public function cancelEmailOtp(): void
    {
        Cache::forget($this->otpCacheKey());

        $this->otp_code         = '';
        $this->otp_target_email = null;
        $this->resetErrorBag('otp_code');
    }
};
?>

<div>
    <form wire:submit="save">
        <div class="w-full border border-light-bd-default dark:border-dark-bd-default rounded-xl bg-light-secondary dark:bg-dark-secondary overflow-hidden">
            <div class="flex items-center gap-3 px-4 sm:px-6 py-3 border-b border-light-bd-default dark:border-dark-bd-default bg-light-subtle/50 dark:bg-dark-secondary">
                <flux:icon.user class="w-5 h-5 lg:w-6 lg:h-6 text-light-txt-muted dark:text-dark-txt-muted shrink-0" />
                <span class="text-md lg:text-section-heading font-primary font-bold text-light-txt-body dark:text-dark-txt-body">Personal information</span>
            </div>

            <div class="p-4 sm:p-6 space-y-4">
                <div class="grid w-full grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6">
                    <flux:input label="Name" wire:model="name" class="w-full font-secondary" :readonly="$user->isDeleted()" />
                    <div>
                        <flux:input
                            label="Email"
                            wire:model="email_address"
                            type="email"
                            :placeholder="$user->hasAccount() ? 'Required' : 'Optional'"
                            class="w-full font-secondary"
                            :readonly="$user->isDeleted()"
                        />
                    </div>
                    <div>
                        <flux:input
                            label="Mobile number"
                            wire:model="phone_number"
                            type="tel"
                            placeholder="09XXXXXXXXX"
                            class="w-full font-secondary"
                            :readonly="$user->isDeleted()"
                        />
                    </div>
                    <div>
                        <flux:field>
                            <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">
                                Address
                            </flux:label>

                            @if ($user->isDeleted())
                                <div class="w-full font-secondary text-table-row bg-light-subtle dark:bg-dark-subtle text-light-txt-muted dark:text-dark-txt-muted border border-light-bd-default dark:border-dark-bd-default rounded-lg px-3 py-2.5 opacity-70">
                                    {{ $address ?: 'No address on file' }}
                                </div>
                            @else
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
                            @endif
                            <flux:error name="address" />
                        </flux:field>
                    </div>

                    {{-- Role and user code (always readonly) --}}
                    <flux:input
                        label="Role"
                        value="{{ ucfirst($user->role) }}"
                        class="w-full font-secondary opacity-70"
                        icon:trailing="lock-closed"
                        readonly
                    />
                    <flux:input
                        label="User code"
                        value="{{ $user->user_code }}"
                        class="w-full font-secondary opacity-70"
                        icon:trailing="lock-closed"
                        readonly
                    />
                </div>

                <div class="flex flex-col sm:flex-row sm:items-center gap-3 w-full pt-4 dark:border-dark-bd-default">

                    {{-- Suspend / reinstate controls (own component) --}}
                    <livewire:user-info.suspension-controls :user="$user" :key="'suspension-'.$user->id" />

                    <flux:spacer class="hidden sm:block" />

                    <flux:button
                        size="sm"
                        variant="primary"
                        type="submit"
                        icon="check"
                        class="w-full sm:w-auto order-1 sm:order-2"
                        :disabled="$user->isDeleted()"
                    >
                        Save changes
                    </flux:button>

                </div>
            </div>
        </div>
    </form>

    {{-- Email OTP verification modal --}}
    <flux:modal
        name="email-otp-modal"
        :closable="false"
        class="w-[calc(100%-2rem)] sm:max-w-md mx-auto rounded-xl overflow-hidden"
    >
        <div class="flex flex-col p-4 sm:p-6 !pr-4 sm:!pr-6 space-y-5">
            <div class="flex items-start justify-between">
                <div>
                    <flux:heading size="xl" class="!font-primary !font-bold text-light-txt-primary dark:text-dark-txt-primary">
                        Verify email address
                    </flux:heading>
                    <flux:text class="mt-1 font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">
                        We sent a 6-digit code to
                        <span class="font-medium text-light-txt-body dark:text-dark-txt-primary">{{ $otp_target_email }}</span>.
                        Enter it below to confirm this email address. Your changes are saved after it is verified.
                    </flux:text>
                </div>
                <flux:modal.close>
                    <button type="button" wire:click="cancelEmailOtp" class="p-1 rounded-full hover:bg-light-subtle dark:hover:bg-dark-subtle text-light-txt-muted dark:text-dark-txt-muted -mt-1">
                        <flux:icon name="x-mark" class="w-5 h-5" />
                    </button>
                </flux:modal.close>
            </div>

            <flux:field>
                <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">Verification code</flux:label>
                <flux:input
                    wire:model="otp_code"
                    wire:keydown.enter="verifyEmailOtp"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    maxlength="6"
                    placeholder="000000"
                    class="font-secondary text-center text-xl tracking-[0.5em]"
                />
                <flux:error name="otp_code" class="font-secondary text-helper text-danger dark:text-dark-danger mt-1" />
            </flux:field>

            <div class="flex items-center justify-between gap-2">
                <flux:text class="font-secondary text-xs text-light-txt-muted dark:text-dark-txt-muted">
                    Didn't get the code?
                </flux:text>
                <button
                    type="button"
                    wire:click="resendEmailOtp"
                    wire:loading.attr="disabled"
                    wire:target="resendEmailOtp"
                    class="font-secondary text-xs font-medium text-secondary hover:underline disabled:opacity-50"
                >
                    Resend code
                </button>
            </div>

            <div class="flex flex-col sm:flex-row justify-end gap-2 pt-2 border-t border-light-bd-default dark:border-dark-bd-default">
                <flux:modal.close>
                    <flux:button type="button" variant="ghost" wire:click="cancelEmailOtp" class="font-secondary w-full sm:w-auto">
                        Cancel
                    </flux:button>
                </flux:modal.close>
                <flux:button
                    type="button"
                    variant="primary"
                    icon="check"
                    wire:click="verifyEmailOtp"
                    wire:loading.attr="disabled"
                    wire:target="verifyEmailOtp"
                    class="font-secondary w-full sm:w-auto"
                >
                    Verify &amp; save
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Address modal (with pre‑fill) – UPDATED with Province field --}}
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
                        Set address
                    </flux:heading>
                    <flux:text class="mt-1 font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">
                        Please provide the complete address.
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
</div>
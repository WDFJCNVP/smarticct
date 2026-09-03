<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;

use App\Services\UserService;
use App\Mail\RegistrationOtpMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Database\QueryException;

new #[Layout('layouts.public-account-setup')] class extends Component
{

    #[Validate('required|string|email|max:255|unique:users,email_address')]
    public string $email_address = '';

    #[Validate('required|string|confirmed|min:8')]
    public string $password = '';
    public string $password_confirmation = '';

    // OTP Verification properties
    public string $otp = '';
    public bool $otpSent = false;

    private function otpThrottleKey(): string
    {
        return 'registration_otp_throttle_' . Str::lower($this->email_address) . '|' . request()->ip();
    }

    public function sendOtp(): void
    {
        $this->validate();

        if (RateLimiter::tooManyAttempts($this->otpThrottleKey(), 3)) {
            $seconds = RateLimiter::availableIn($this->otpThrottleKey());
            $this->addError('email_address', "Too many attempts. Please try again in {$seconds} seconds.");
            return;
        }

        RateLimiter::hit($this->otpThrottleKey(), 60);

        $generatedOtp = random_int(100000, 999999);

        Cache::put('registration_otp_' . $this->email_address, $generatedOtp, now()->addMinutes(10));

        Mail::to($this->email_address)->send(new RegistrationOtpMail($generatedOtp));

        $this->otpSent = true;
    }

    public function resendOtp(): void
    {
        if (RateLimiter::tooManyAttempts($this->otpThrottleKey(), 3)) {
            $seconds = RateLimiter::availableIn($this->otpThrottleKey());
            Flux::toast(
                duration: 4000,
                variant: 'danger',
                heading: 'Please wait.',
                text: "Too many attempts. Please try again in {$seconds} seconds.",
            );
            return;
        }

        RateLimiter::hit($this->otpThrottleKey(), 60);

        $generatedOtp = random_int(100000, 999999);

        Cache::put('registration_otp_' . $this->email_address, $generatedOtp, now()->addMinutes(10));

        Mail::to($this->email_address)->send(new RegistrationOtpMail($generatedOtp));

        Flux::toast(
            duration: 4000,
            variant: 'success',
            heading: 'Code resent!',
            text: 'A new verification code has been sent to your email.',
        );
    }

    public function changeEmail(): void
    {
        $this->otpSent = false;
        $this->otp = '';
        $this->resetErrorBag('otp');
    }

    public function verifyAndRegister()
    {
        $this->validate([
            'otp' => 'required|string|size:6',
        ]);

        $cachedOtp = Cache::get('registration_otp_' . $this->email_address);

        if (! $cachedOtp || $cachedOtp != $this->otp) {
            $this->addError('otp', 'The verification code is invalid or has expired.');
            return;
        }

        if (\App\Models\User::where('email_address', $this->email_address)->exists()) {
            $this->addError('email_address', 'An account with this email already exists.');
            $this->otpSent = false;
            return;
        }

        try {
            $user = app(UserService::class)->create([
                'email_address' => $this->email_address,
                'password'      => $this->password,
                'role'          => 'commuter',
            ]);
        } catch (QueryException $e) {
            $this->addError('email_address', 'An account with this email already exists.');
            $this->otpSent = false;
            return;
        }

        if ($user) {
            Cache::forget('registration_otp_' . $this->email_address);
            auth()->login($user);
            request()->session()->regenerate();
            return $this->redirect('/register/setup');
        }
    }
};
?>

<div class="flex min-h-full md:h-full overflow-y-auto md:overflow-hidden p-4 sm:p-6 md:p-10!" x-data="{ loaded: false }" x-init="setTimeout(() => loaded = true, 100)">

    {{-- HERO PANEL – same image, phrase: "Start your SMART journey." --}}
    <div 
    x-show="loaded" 
    x-transition:enter.duration.700
    x-transition:enter.start.opacity-0.-translate-x-5
    x-transition:enter.end.opacity-100.translate-x-0
    class="hidden md:flex w-5/12 h-full relative flex-col p-8 overflow-hidden"
>
    <div class="absolute inset-0 overflow-hidden">
        <a href="/">
            <img src="{{ asset('images/terminal-bg-2.jpeg') }}" alt="SmartICCT" 
                 class="h-10 w-auto md:h-full scale-105 transition-transform duration-[20s] ease-in-out hover:scale-110">
        </a>
        <div class="absolute inset-0 bg-gradient-to-t from-[#0B0F2A]/90 via-[#1A1F3A]/60 to-transparent"></div>
    </div>

    <div class="relative z-10 flex flex-col justify-end h-full pb-12">
        <h1 class="font-primary text-4xl md:text-5xl font-extrabold text-white leading-[1.1] max-w-sm">
            Start your <br>SMART journey.
        </h1>
        <p class="font-secondary text-base md:text-lg text-white/80 mt-4 max-w-xs leading-relaxed">
            Rent, pay, and queue – all in one place at SmartICCT.
        </p>
        <div class="mt-6 flex items-center gap-4">
            <span class="w-10 h-0.5 bg-secondary"></span>
            <span class="text-xs text-white/40 font-secondary">#MoveSmartIriga</span>
        </div>
    </div>
</div>

    {{-- FORM PANEL --}}
    <div
        x-show="loaded"
        x-transition:enter.duration.700.delay.200
        x-transition:enter.start.opacity-0.translate-x-5
        x-transition:enter.end.opacity-100.translate-x-0
        class="flex flex-1 flex-col justify-center px-6 py-8 sm:px-10 md:px-12 bg-white/80 dark:bg-dark-secondary/80 backdrop-blur-sm overflow-y-auto md:overflow-hidden min-h-full md:h-full"
    >
        <div class="w-full max-w-sm mx-auto">

            {{-- Mobile Brand Header --}}
            <div class="block md:hidden mb-5">
                <div class="flex items-center justify-center gap-3">
                    <a href="/" class="shrink-0">
                        <img 
                            src="{{ asset('images/logo.png') }}" 
                            alt="SmartICCT" 
                            class="h-10 w-auto"
                        >
                    </a>
                    <div>
                        <p class="font-primary text-base font-bold text-light-txt-primary dark:text-dark-txt-primary leading-tight">
                            Iriga City
                        </p>
                        <p class="font-secondary text-xs text-light-txt-muted dark:text-dark-txt-muted leading-tight -mt-0.5">
                            Central Terminal
                        </p>
                    </div>
                </div>
                <div class="w-full h-0.5 bg-secondary/60 dark:bg-secondary/80 mx-auto mt-3"></div>
            </div>

            @if (session('status'))
                <div class="mb-3 font-secondary text-sm font-medium text-success dark:text-dark-success">
                    {{ session('status') }}
                </div>
            @endif

            <p class="font-secondary text-nav-label font-semibold uppercase tracking-widest text-secondary mb-1">Register</p>
            <h2 class="font-primary text-page-title font-bold text-light-txt-primary dark:text-dark-txt-primary mb-1">
                {{ $otpSent ? 'Verify your email' : 'Create your account' }}
            </h2>
            <p class="font-secondary text-body text-light-txt-muted dark:text-dark-txt-muted mb-3">
                {{ $otpSent ? 'Step 2 of 2 — Enter the code we sent you' : 'Step 1 of 2 — Account credentials' }}
            </p>

            <div class="flex gap-1.5 mb-4">
                <div class="flex-1 h-[3px] rounded-full bg-secondary"></div>
                <div class="flex-1 h-[3px] rounded-full transition-colors duration-300 {{ $otpSent ? 'bg-secondary' : 'bg-light-bd-default dark:bg-dark-bd-default' }}"></div>
            </div>

            @if (! $otpSent)
                <div wire:key="step-1-inputs">

                    <flux:field class="mt-3">
                        <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">
                            Email Address
                        </flux:label>
                        <flux:input
                            type="email"
                            wire:key="input-email"
                            wire:model="email_address"
                            placeholder="e.g. juandelacruz@example.com"
                            class="font-secondary text-table-row rounded-lg bg-light-primary dark:bg-dark-surface text-light-txt-body dark:text-dark-txt-primary border-light-bd-default dark:border-dark-bd-default placeholder:text-light-txt-muted dark:placeholder:text-dark-txt-muted transition-shadow duration-200 focus:ring-2 focus:ring-secondary/50"
                        />
                        <flux:error name="email_address" class="font-secondary text-helper text-danger dark:text-dark-danger mt-1" />
                    </flux:field>

                    <flux:field class="mt-3">
                        <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">
                            Password
                        </flux:label>
                        <flux:input
                            type="password"
                            wire:key="input-password"
                            wire:model="password"
                            placeholder="Min. 8 characters"
                            viewable
                            class="font-secondary text-table-row rounded-lg bg-light-primary dark:bg-dark-surface text-light-txt-body dark:text-dark-txt-primary border-light-bd-default dark:border-dark-bd-default placeholder:text-light-txt-muted dark:placeholder:text-dark-txt-muted transition-shadow duration-200 focus:ring-2 focus:ring-secondary/50"
                        />
                        <flux:error name="password" class="font-secondary text-helper text-danger dark:text-dark-danger mt-1" />
                    </flux:field>

                    <flux:field class="mt-3">
                        <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">
                            Confirm Password
                        </flux:label>
                        <flux:input
                            type="password"
                            wire:key="input-password-confirmation"
                            wire:model="password_confirmation"
                            placeholder="Re-enter your password"
                            viewable
                            class="font-secondary text-table-row rounded-lg bg-light-primary dark:bg-dark-surface text-light-txt-body dark:text-dark-txt-primary border-light-bd-default dark:border-dark-bd-default placeholder:text-light-txt-muted dark:placeholder:text-dark-txt-muted transition-shadow duration-200 focus:ring-2 focus:ring-secondary/50"
                        />
                        <flux:error name="password_confirmation" class="font-secondary text-helper text-danger dark:text-dark-danger mt-1" />
                    </flux:field>

                    <flux:button
                        wire:click="sendOtp"
                        wire:loading.attr="disabled"
                        wire:target="sendOtp"
                        class="font-primary hover:bg-secondary! dark:hover:bg-secondary! text-table-row !bg-primary !text-white !font-semibold w-full mt-4 transition-transform duration-200 hover:scale-[1.02] active:scale-[0.97]"
                        variant="filled"
                    >
                        <span wire:loading.remove wire:target="sendOtp">Continue with email →</span>
                        <span wire:loading wire:target="sendOtp" class="inline-flex items-center justify-center gap-1.5">
                            <flux:icon name="arrow-path" class="size-3.5 animate-spin shrink-0" />
                            Sending code...
                        </span>
                    </flux:button>

                </div>
            @else
                <div wire:key="step-2-otp" class="text-center">

                    <div class="mx-auto rounded-full bg-secondary/10 p-3 w-fit">
                        <flux:icon name="envelope-open" class="size-8 text-secondary" />
                    </div>

                    <p class="font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted mt-3">
                        We've sent a 6-digit code to <br>
                        <span class="font-semibold text-secondary">{{ $email_address }}</span>
                    </p>

                    <flux:field class="mt-4 text-left">
                        <flux:input
                            type="text"
                            wire:key="input-otp"
                            wire:model="otp"
                            placeholder="000000"
                            maxlength="6"
                            class="font-mono text-center tracking-widest text-lg rounded-lg bg-light-primary dark:bg-dark-surface text-light-txt-body dark:text-dark-txt-primary border-light-bd-default dark:border-dark-bd-default placeholder:text-light-txt-muted dark:placeholder:text-dark-txt-muted transition-shadow duration-200 focus:ring-2 focus:ring-secondary/50"
                        />
                        <flux:error name="otp" class="mt-0! font-secondary text-helper text-danger dark:text-dark-danger" />
                    </flux:field>

                    <flux:button
                        wire:click="verifyAndRegister"
                        wire:loading.attr="disabled"
                        wire:target="verifyAndRegister"
                        class="font-primary text-table-row !bg-primary !text-white !font-semibold w-full mt-3 transition-transform duration-200 hover:scale-[1.02] active:scale-[0.97]"
                        variant="filled"
                    >
                        <span wire:loading.remove wire:target="verifyAndRegister">Verify & Complete</span>
                        <span wire:loading wire:target="verifyAndRegister">Verifying...</span>
                    </flux:button>

                    <button
                        type="button"
                        wire:click="resendOtp"
                        wire:loading.attr="disabled"
                        wire:target="resendOtp"
                        class="font-secondary text-sm text-light-txt-muted hover:text-secondary transition-colors mt-3 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <span wire:loading.remove wire:target="resendOtp">Didn't get it? <span class="underline">Resend code</span></span>
                        <span wire:loading wire:target="resendOtp" class="inline-flex items-center gap-1.5 justify-center">
                            <flux:icon name="arrow-path" class="size-3 animate-spin shrink-0" />
                            Resending...
                        </span>
                    </button>

                    <button
                        type="button"
                        wire:click="changeEmail"
                        class="block mx-auto font-secondary text-xs text-light-txt-muted mt-2 hover:text-secondary transition-colors"
                    >
                        ← Change email address
                    </button>
                </div>
            @endif

            <div class="pt-4">
                <flux:text class="font-secondary text-timestamp text-light-txt-muted dark:text-dark-txt-muted">
                    Already have an account?
                    <flux:link href="{{ route('login') }}" class="font-secondary text-timestamp text-secondary font-medium">Sign in</flux:link>
                </flux:text>
            </div>

        </div>
    </div>

</div>
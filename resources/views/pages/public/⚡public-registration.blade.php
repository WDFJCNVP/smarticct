<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;

use App\Services\UserService;
use App\Mail\RegistrationOtpMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;

new #[Layout('layouts::public-layout')] class extends Component
{

    #[Validate('required|string|email|max:255|unique:users,email_address')]
    public string $email_address = '';

    #[Validate('required|string|confirmed|min:8')]
    public string $password = '';
    public string $password_confirmation = '';

    // OTP Verification properties
    public string $otp = '';
    public bool $otpSent = false;

    public function sendOtp(): void
    {
        $this->validate();

        $generatedOtp = rand(100000, 999999);

        Cache::put('registration_otp_' . $this->email_address, $generatedOtp, now()->addMinutes(10));

        Mail::to($this->email_address)->send(new RegistrationOtpMail($generatedOtp));

        $this->otpSent = true;
    }

    public function resendOtp(): void
    {
        $generatedOtp = rand(100000, 999999);

        Cache::put('registration_otp_' . $this->email_address, $generatedOtp, now()->addMinutes(10));

        Mail::to($this->email_address)->send(new RegistrationOtpMail($generatedOtp));

        Flux::toast(
            duration: 0,
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

        $user = app(UserService::class)->create([
            'email_address' => $this->email_address,
            'password'      => $this->password,
            'role'          => 'commuter',
        ]);

        if ($user) {

            Cache::forget('registration_otp_' . $this->email_address);

            auth()->login($user);
            request()->session()->regenerate();

            return $this->redirect('/register/setup');
        }
    }
};
?>

{{--
    Scrapped the arch/banner idea — dropped it back to the exact structure
    the login page already uses (two-panel, h-full overflow-hidden, no page
    scroll). Since this component uses the SAME layout as login
    (layouts::public-layout), we know this fits the viewport without
    scrolling. Only the right-hand panel's content swaps between the
    email/password step and the OTP step; the hero panel stays static.

    Validation errors now use the same token as login's rate-limit message
    (font-secondary text-helper text-danger) instead of Flux's unstyled
    default, so both auth pages read as one consistent design language.
--}}

<div class="flex h-full overflow-hidden p-10!" x-data="{ loaded: false }" x-init="setTimeout(() => loaded = true, 100)">

    {{-- ── HERO PANEL ── --}}
    <div
        x-show="loaded"
        x-transition:enter.duration.700
        x-transition:enter.start.opacity-0.-translate-x-5
        x-transition:enter.end.opacity-100.translate-x-0
        class="hidden md:flex w-5/12 h-full relative flex-col p-8 overflow-hidden"
    >
        <div class="absolute inset-0 overflow-hidden">
            <a href="/">
                <img
                    src="{{ asset('images/iriga-terminal.jpg') }}"
                    alt="SmartICCT"
                    class="h-10 w-auto md:h-full scale-105 transition-transform duration-[20s] ease-in-out hover:scale-110"
                >
            </a>
            <div class="absolute inset-0 bg-linear-to-r from-[#21284D]/90 from-[20%] to-[#272C48]/75 to-[75%]"></div>
        </div>

        <div class="relative z-10 flex flex-col justify-start pt-2">
            <h1 class="font-primary text-page-title font-extrabold text-dark-txt-primary leading-tight">
                Welcome to SMART Iriga City Central Terminal.
            </h1>
            <p class="font-secondary text-body text-dark-txt-muted mt-3 leading-relaxed">
                Rent vehicles, top up your card, and view live queues — all from one place.
            </p>
        </div>
    </div>

    {{-- ── FORM PANEL ── --}}
    <div
        x-show="loaded"
        x-transition:enter.duration.700.delay.200
        x-transition:enter.start.opacity-0.translate-x-5
        x-transition:enter.end.opacity-100.translate-x-0
        class="flex flex-1 flex-col justify-center px-6 py-8 sm:px-12 bg-light-secondary dark:bg-dark-secondary overflow-hidden h-full"
    >
        <div class="w-full max-w-sm mx-auto">

            @if (session('status'))
                <div class="mb-3 font-secondary text-sm font-medium text-green-600 dark:text-green-400">
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
                            class="font-secondary text-table-row bg-light-primary dark:bg-dark-surface text-light-txt-body dark:text-dark-txt-primary border-light-bd-default dark:border-dark-bd-default placeholder:text-light-txt-muted dark:placeholder:text-dark-txt-muted transition-shadow duration-200 focus:ring-2 focus:ring-secondary/50"
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
                            class="font-secondary text-table-row bg-light-primary dark:bg-dark-surface text-light-txt-body dark:text-dark-txt-primary border-light-bd-default dark:border-dark-bd-default placeholder:text-light-txt-muted dark:placeholder:text-dark-txt-muted transition-shadow duration-200 focus:ring-2 focus:ring-secondary/50"
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
                            class="font-secondary text-table-row bg-light-primary dark:bg-dark-surface text-light-txt-body dark:text-dark-txt-primary border-light-bd-default dark:border-dark-bd-default placeholder:text-light-txt-muted dark:placeholder:text-dark-txt-muted transition-shadow duration-200 focus:ring-2 focus:ring-secondary/50"
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
                            class="text-center font-primary text-2xl tracking-[0.4em] font-bold py-3 border-light-bd-default dark:border-dark-bd-default placeholder:text-light-txt-muted/50"
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
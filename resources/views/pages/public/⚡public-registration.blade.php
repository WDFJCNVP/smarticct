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

<div class="min-h-screen flex items-center justify-center mx-auto px-4 py-8">
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 w-full max-w-6xl">
    <div>
        <x-card>
            @if (session('status'))
                <div class="mb-4 text-sm font-medium text-green-600 dark:text-green-400">
                    {{ session('status') }}
                </div>
            @endif

            @if (! $otpSent)
                <div wire:key="step-1-inputs" class="flex flex-col gap-4">

                    {{-- ── EMAIL ADDRESS ── --}}
                    <flux:field>
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
                        <flux:error name="email_address" />
                    </flux:field>

                    {{-- ── PASSWORD ── --}}
                    <flux:field>
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
                        <flux:error name="password" />
                    </flux:field>

                    {{-- ── CONFIRM PASSWORD ── --}}
                    <flux:field>
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
                        <flux:error name="password_confirmation" />
                    </flux:field>

                    {{-- ── SUBMIT BUTTON WITH SPINNER ── --}}
                    <flux:button 
                        wire:click="sendOtp" 
                        wire:loading.attr="disabled"
                        wire:target="sendOtp"
                        class="font-primary hover:bg-secondary! dark:hover:bg-secondary! text-table-row !bg-primary !text-white !font-semibold w-full transition-transform duration-200 hover:scale-[1.02] active:scale-[0.97] mt-1" 
                        variant="filled"
                    >
                        <span wire:loading.remove wire:target="sendOtp">
                            Continue with email →
                        </span>
                        <span wire:loading wire:target="sendOtp" class="inline-flex items-center justify-center gap-1.5">
                            <flux:icon name="arrow-path" class="size-3.5 animate-spin shrink-0" />
                            Sending code...
                        </span>
                    </flux:button>

                </div>
            @else
                {{-- Added wire:key="step-2-otp" --}}
                <div wire:key="step-2-otp" class="space-y-4">
                    <div class="text-center">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Verify Your Email</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                            We sent a 6-digit verification code to <br>
                            <span class="font-medium text-indigo-600 dark:text-indigo-400">{{ $email_address }}</span>
                        </p>
                    </div>

                    <div>
                        <x-input 
                            wire:key="input-otp"
                            wire:model="otp" 
                            placeholder="Enter 6-digit code" 
                            maxlength="6" 
                            class="text-center text-xl tracking-widest font-mono"
                        />
                        @error('otp') <span class="text-xs text-red-500 mt-1 block text-center">{{ $message }}</span> @enderror
                    </div>

                    <x-button 
                        wire:click="verifyAndRegister" 
                        wire:loading.attr="disabled"
                        class="w-full" 
                        variant="primary"
                    >
                        <span wire:loading.remove wire:target="verifyAndRegister">Verify Code & Complete</span>
                        <span wire:loading wire:target="verifyAndRegister">Verifying...</span>
                    </x-button>

                    <div class="flex flex-col items-center space-y-2 text-sm text-gray-600 dark:text-gray-400 mt-4">
                        <div>
                            Not seeing the email in your inbox?
                            <button 
                                type="button" 
                                wire:click="resendOtp" 
                                wire:loading.attr="disabled"
                                wire:target="resendOtp"
                                class="hover:text-indigo-600 underline disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                <span wire:loading.remove wire:target="resendOtp">Re-send code</span>
                                <span wire:loading wire:target="resendOtp" class="inline-flex items-center gap-1">
                                    <flux:icon name="arrow-path" class="size-3 animate-spin shrink-0" />
                                    Resending code...
                                </span>
                            </button>
                        </div>

                        <div>
                            Wrong email?
                            <button 
                                type="button" 
                                wire:click="changeEmail" 
                                class="hover:text-indigo-600 underline"
                            >
                                Change email
                            </button>
                        </div>
                    </div>
                </div>
            @endif

        </x-card> 
    </div>

    <div class="hidden lg:block p-6 bg-white rounded-xl shadow-md dark:bg-gray-800">
      <h2 class="text-xl font-bold mb-2">Secondary Content (image?)</h2>
      <p></p>
    </div>
  </div>
</div>
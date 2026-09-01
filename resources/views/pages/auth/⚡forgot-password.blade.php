<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\Attributes\Layout;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use App\Mail\PasswordResetOtpMail;
use Flux\Flux;

new #[Layout('layouts.public-account-setup')] class extends Component
{
    // State Tracking
    public bool $otpSent = false;
    public bool $otpVerified = false;

    // Form Fields
    public string $email = '';
    public string $otp = '';
    public string $password = '';
    public string $password_confirmation = '';

    public function sendOtp()
    {
        $this->validate([
            'email' => 'required|email|exists:users,email_address'
        ]);

        $generatedOtp = rand(100000, 999999);

        session()->put('password_reset_otp', [
            'code' => $generatedOtp,
            'email' => $this->email,
            'expires_at' => now()->addMinutes(10)
        ]);

        Mail::to($this->email)->send(new PasswordResetOtpMail($generatedOtp));

        $this->otpSent = true;
        $this->otpVerified = false; // Ensure this is reset if they restart
    }

    public function resendOtp()
    {
        $generatedOtp = rand(100000, 999999);

        session()->put('password_reset_otp', [
            'code' => $generatedOtp,
            'email' => $this->email,
            'expires_at' => now()->addMinutes(10)
        ]);

        Mail::to($this->email)->send(new PasswordResetOtpMail($generatedOtp));

        Flux::toast(
            duration: 4000,
            variant: 'success',
            heading: 'Code resent!',
            text: 'A new verification code has been sent to your email.',
        );
    }

    public function verifyOtp()
    {
        $this->validate([
            'otp' => 'required|string|size:6',
        ]);

        $otpData = session()->get('password_reset_otp');

        if (! $otpData || 
            $otpData['code'] != $this->otp || 
            $otpData['email'] !== $this->email || 
            now()->greaterThan($otpData['expires_at'])) {
            
            $this->addError('otp', 'The verification code is invalid or has expired.');
            return;
        }

        // OTP is correct! Unlock the next step.
        $this->otpVerified = true;
    }

    public function saveNewPassword()
    {
        // Double-check they passed the OTP gate just in case
        if (! $this->otpVerified) {
            return;
        }

        $this->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::where('email_address', $this->email)->first();
        $user->update([
            'password' => Hash::make($this->password)
        ]);

        session()->forget('password_reset_otp');

        session()->flash('status', 'Your password has been successfully reset! Please log in.');
        return $this->redirectRoute('login'); 
    }

    public function changeEmail()
    {
        $this->otpSent = false;
        $this->otpVerified = false;
        $this->otp = '';
        $this->resetErrorBag();
    }
};
?>

<div class="flex min-h-full flex-col justify-center px-6 py-12 sm:px-8">
  <div class="sm:mx-auto sm:w-full sm:max-w-sm text-center">
    <p class="font-secondary text-nav-label font-semibold uppercase tracking-widest text-secondary mb-1">Account recovery</p>
    <h2 class="font-primary text-page-title font-bold text-light-txt-primary dark:text-dark-txt-primary">Reset your password</h2>
  </div>

  <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-sm">

    @if(! $otpSent)
        <div wire:key="recovery-step-1" class="space-y-4">
            <p class="font-secondary text-body text-light-txt-muted dark:text-dark-txt-muted text-center">
                Enter the email address on your account and we'll send you a verification code.
            </p>

            <flux:field>
                <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">Email address</flux:label>
                <flux:input
                    wire:model="email"
                    id="email"
                    type="email"
                    placeholder="Enter your registered email"
                    class="font-secondary text-table-row bg-light-primary dark:bg-dark-surface text-light-txt-body dark:text-dark-txt-primary border-light-bd-default dark:border-dark-bd-default placeholder:text-light-txt-muted dark:placeholder:text-dark-txt-muted transition-shadow duration-200 focus:ring-2 focus:ring-secondary/50"
                />
                @error('email') <flux:error class="font-secondary text-helper text-danger dark:text-dark-danger mt-1">{{ $message }}</flux:error> @enderror
            </flux:field>

            <flux:button wire:click="sendOtp" wire:loading.attr="disabled" variant="primary" class="w-full justify-center">
                <span wire:loading.remove wire:target="sendOtp">Send OTP</span>
                <span wire:loading wire:target="sendOtp">Sending...</span>
            </flux:button>

            <div class="text-center">
                <flux:link href="{{ route('login') }}" wire:navigate class="font-secondary text-table-row font-medium text-secondary hover:text-secondary/80">← Back to login</flux:link>
            </div>
        </div>

    {{-- ── STEP 2: VERIFY OTP ── --}}
    @elseif($otpSent && ! $otpVerified)
        <div wire:key="recovery-step-2" class="space-y-4">
            <div class="text-center mb-2">
                <p class="font-secondary text-body text-light-txt-muted dark:text-dark-txt-muted">
                    We sent a 6-digit verification code to <br>
                    <span class="font-semibold text-light-txt-primary dark:text-dark-txt-primary">{{ $email }}</span>
                </p>
            </div>

            <flux:field>
                <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">Verification code</flux:label>
                <flux:input
                    wire:model="otp"
                    id="otp"
                    type="text"
                    maxlength="6"
                    placeholder="000000"
                    class="font-mono text-center tracking-widest text-lg bg-light-primary dark:bg-dark-surface text-light-txt-body dark:text-dark-txt-primary border-light-bd-default dark:border-dark-bd-default placeholder:text-light-txt-muted dark:placeholder:text-dark-txt-muted transition-shadow duration-200 focus:ring-2 focus:ring-secondary/50"
                />
                @error('otp') <flux:error class="font-secondary text-helper text-danger dark:text-dark-danger mt-1 text-center">{{ $message }}</flux:error> @enderror
            </flux:field>

            <flux:button wire:click="verifyOtp" wire:loading.attr="disabled" variant="primary" class="w-full justify-center">
                <span wire:loading.remove wire:target="verifyOtp">Verify code</span>
                <span wire:loading wire:target="verifyOtp">Verifying...</span>
            </flux:button>

            <div class="text-center space-y-1">
                <p class="font-secondary text-table-row text-light-txt-muted dark:text-dark-txt-muted">
                    Didn't receive the code?
                    <button type="button" wire:click="resendOtp" class="font-medium text-secondary hover:text-secondary/80 underline">Resend</button>
                </p>
                <button type="button" wire:click="changeEmail" class="font-secondary text-table-row font-medium text-light-txt-muted dark:text-dark-txt-muted hover:text-light-txt-primary dark:hover:text-dark-txt-primary">← Change email address</button>
            </div>
        </div>

    {{-- ── STEP 3: NEW PASSWORD ── --}}
    @elseif($otpVerified)
        <div wire:key="recovery-step-3" class="space-y-4">
            <div class="text-center mb-2">
                <p class="font-secondary text-table-row text-success dark:text-dark-success font-medium">
                    ✓ Code verified successfully
                </p>
                <p class="font-secondary text-body text-light-txt-muted dark:text-dark-txt-muted mt-1">Please enter your new password below.</p>
            </div>

            <flux:field>
                <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">New password</flux:label>
                <flux:input
                    wire:model="password"
                    id="password"
                    type="password"
                    viewable
                    class="font-secondary text-table-row bg-light-primary dark:bg-dark-surface text-light-txt-body dark:text-dark-txt-primary border-light-bd-default dark:border-dark-bd-default placeholder:text-light-txt-muted dark:placeholder:text-dark-txt-muted transition-shadow duration-200 focus:ring-2 focus:ring-secondary/50"
                />
                @error('password') <flux:error class="font-secondary text-helper text-danger dark:text-dark-danger mt-1">{{ $message }}</flux:error> @enderror
            </flux:field>

            <flux:field>
                <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">Confirm new password</flux:label>
                <flux:input
                    wire:model="password_confirmation"
                    id="password_confirmation"
                    type="password"
                    viewable
                    class="font-secondary text-table-row bg-light-primary dark:bg-dark-surface text-light-txt-body dark:text-dark-txt-primary border-light-bd-default dark:border-dark-bd-default placeholder:text-light-txt-muted dark:placeholder:text-dark-txt-muted transition-shadow duration-200 focus:ring-2 focus:ring-secondary/50"
                />
            </flux:field>

            <flux:button wire:click="saveNewPassword" wire:loading.attr="disabled" variant="primary" class="w-full justify-center">
                <span wire:loading.remove wire:target="saveNewPassword">Save new password</span>
                <span wire:loading wire:target="saveNewPassword">Saving...</span>
            </flux:button>
        </div>
    @endif

  </div>
</div>
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

<div class="flex min-h-full flex-col justify-center px-6 py-12 lg:px-8">
  <div class="sm:mx-auto sm:w-full sm:max-w-sm">
    <h2 class="mt-10 text-center text-2xl/9 font-bold tracking-tight text-white">Account Recovery</h2>
  </div>
  
  <div class="mt-10 sm:mx-auto sm:w-full sm:max-w-sm">
    

    @if(! $otpSent)
        <div wire:key="recovery-step-1" class="space-y-4">
            <div>
                <label for="email" class="block text-sm/6 font-medium text-gray-100">Email address</label>
                <div class="mt-2">
                    <input wire:model="email" id="email" type="email" placeholder="Enter your registered email" class="block w-full rounded-md bg-white/5 px-3 py-1.5 text-base text-white outline-1 -outline-offset-1 outline-white/10 placeholder:text-gray-500 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-500 sm:text-sm/6" />
                </div>
                @error('email') <span class="text-xs text-red-400 mt-1 block">{{ $message }}</span> @enderror
            </div>

            <div class="mt-4">
                <button wire:click="sendOtp" wire:loading.attr="disabled" class="flex w-full justify-center rounded-md bg-indigo-500 px-3 py-1.5 text-sm/6 font-semibold text-white hover:bg-indigo-400 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-500 disabled:opacity-50">
                    <span wire:loading.remove wire:target="sendOtp">Send OTP</span>
                    <span wire:loading wire:target="sendOtp">Sending...</span>
                </button>
            </div>
            
            <div class="mt-4 text-center">
                <a href="{{ route('login') }}" class="text-sm font-medium text-indigo-400 hover:text-indigo-300">← Back to login</a>
            </div>
        </div>

    {{-- ── STEP 2: VERIFY OTP ── --}}
    @elseif($otpSent && ! $otpVerified)
        <div wire:key="recovery-step-2" class="space-y-4">
            <div class="text-center mb-6">
                <p class="text-sm text-gray-300 mt-1">
                    We sent a 6-digit verification code to <br>
                    <span class="font-medium text-indigo-400">{{ $email }}</span>
                </p>
            </div>

            <div>
                <label for="otp" class="block text-sm/6 font-medium text-gray-100">Verification Code</label>
                <div class="mt-2">
                    <input wire:model="otp" id="otp" type="text" maxlength="6" placeholder="000000" class="block w-full text-center tracking-widest font-mono rounded-md bg-white/5 px-3 py-1.5 text-base text-white outline-1 -outline-offset-1 outline-white/10 placeholder:text-gray-500 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-500 sm:text-lg" />
                </div>
                @error('otp') <span class="text-xs text-red-400 mt-1 block text-center">{{ $message }}</span> @enderror
            </div>

            <div class="mt-6">
                <button wire:click="verifyOtp" wire:loading.attr="disabled" class="flex w-full justify-center rounded-md bg-indigo-500 px-3 py-1.5 text-sm/6 font-semibold text-white hover:bg-indigo-400 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-500 disabled:opacity-50">
                    <span wire:loading.remove wire:target="verifyOtp">Verify Code</span>
                    <span wire:loading wire:target="verifyOtp">Verifying...</span>
                </button>
            </div>

            <div class="mt-4 text-center">
                <p class="text-sm text-gray-400">
                    Didn't receive the code? 
                    <button type="button" wire:click="resendOtp" class="font-medium text-indigo-400 hover:text-indigo-300 underline">Resend</button>
                </p>
                <button type="button" wire:click="changeEmail" class="text-sm font-medium text-gray-400 hover:text-gray-300 mt-2">← Change email address</button>
            </div>
        </div>

    {{-- ── STEP 3: NEW PASSWORD ── --}}
    @elseif($otpVerified)
        <div wire:key="recovery-step-3" class="space-y-4">
            <div class="text-center mb-6">
                <p class="text-sm text-green-400 mt-1 font-medium">
                    ✓ Code verified successfully
                </p>
                <p class="text-sm text-gray-300 mt-1">Please enter your new password below.</p>
            </div>

            <div>
                <label for="password" class="block text-sm/6 font-medium text-gray-100">New Password</label>
                <div class="mt-2">
                    <input wire:model="password" id="password" type="password" class="block w-full rounded-md bg-white/5 px-3 py-1.5 text-base text-white outline-1 -outline-offset-1 outline-white/10 placeholder:text-gray-500 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-500 sm:text-sm/6" />
                </div>
                @error('password') <span class="text-xs text-red-400 mt-1 block">{{ $message }}</span> @enderror
            </div>

            <div>
                <label for="password_confirmation" class="block text-sm/6 font-medium text-gray-100">Confirm New Password</label>
                <div class="mt-2">
                    <input wire:model="password_confirmation" id="password_confirmation" type="password" class="block w-full rounded-md bg-white/5 px-3 py-1.5 text-base text-white outline-1 -outline-offset-1 outline-white/10 placeholder:text-gray-500 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-500 sm:text-sm/6" />
                </div>
            </div>

            <div class="mt-6">
                <button wire:click="saveNewPassword" wire:loading.attr="disabled" class="flex w-full justify-center rounded-md bg-indigo-500 px-3 py-1.5 text-sm/6 font-semibold text-white hover:bg-indigo-400 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-500 disabled:opacity-50">
                    <span wire:loading.remove wire:target="saveNewPassword">Save New Password</span>
                    <span wire:loading wire:target="saveNewPassword">Saving...</span>
                </button>
            </div>
        </div>
    @endif

  </div>
</div>
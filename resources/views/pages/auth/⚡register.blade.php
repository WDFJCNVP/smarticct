<?php

use Livewire\Component;
use Livewire\Attributes\Validate;
use Livewire\Attributes\Layout;
use Illuminate\Validation\Rules\Password;

use App\Services\UserService;

new #[Layout('layouts.public-layout')] class extends Component
{
    public int $step = 1;

    public string $password_confirmation = '';

    #[Validate('required|string|min:3|max:255')]
    public string $name = '';

    #[Validate('string|min:3|in:commuter')]
    public string $role = 'commuter';

    #[Validate('required|string|lowercase|alpha_dash|min:3|max:30|unique:users,username')]
    public string $username = '';

    #[Validate('nullable|string|email|max:255|unique:users,email_address')]
    public string $email_address = '';

    #[Validate('required|string|confirmed')]
    public string $password = '';

    #[Validate('required|string|in:regular,student,senior,pwd')]
    public string $commuter_type = '';

    #[Validate('required|integer|between:5,120')]
    public ?int $age = null;

    #[Validate('required|string|regex:/^09\d{9}$/')]
    public string $phone_number = '';

    #[Validate('nullable|string|max:500')]
    public string $address = '';

    public function nextStep(): void
    {
        $this->validateOnly('name');
        $this->validateOnly('username');
        $this->validateOnly('password');

        $this->step = 2;
    }

    public function prevStep(): void
    {
        $this->step = 1;
    }

    public function register()
    {
        $userBasicInformation = $this->validate();

       $user = app(UserService::class)->create($userBasicInformation);

       if ($user) {

            auth()->login($user);
            request()->session()->regenerate();

            return $this->redirect('/' . auth()->user()->role . '/dashboard');
       }
    }
};
?>

<div class=" flex items-center flex-col justify-center bg-[var(--color-background-secondary)] p-8">
    <div class="w-full max-w-[420px]">

        <div class="flex items-center justify-center flex-col">
            <x-text>SMARTICCT</x-text>
            <x-text size="xl" variant="strong">
                {{ $step === 1 ? 'Create your account' : 'Tell us about yourself' }}
            </x-text>
            <x-text class="text-[13px] text-[var(--color-text-secondary)] m-0">
                {{ $step === 1 ? 'Step 1 of 2 — Account credentials' : 'Step 2 of 2 — Personal details' }}
            </x-text>
        </div>

        <div class="flex gap-1.5 mb-7">
            <div class="flex-1 h-[3px] rounded-[2px] bg-[var(--color-text-primary)]"></div>
            <div class="flex-1 h-[3px] rounded-[2px] {{ $step === 2 ? 'bg-[var(--color-text-primary)]' : 'bg-[var(--color-border-secondary)]' }}"></div>
        </div>

        <flux:card>

            @if ($step === 1)
                <div class="flex flex-col gap-3.5">

                    <x-input wire:model="role" hidden/>
                    <x-input wire:model="name" type="text" placeholder="e.g. Juan dela Cruz" label="Full name"/>
                    <x-input wire:model="username" type="text" placeholder="e.g. juandelacruz" label="Username"/>

                    <x-input wire:model="password" name="password" type="password" placeholder="Min. 8 characters" viewable label="Password"/>

                    <x-input wire:model="password_confirmation" name="password_confirmation" type="password" placeholder="Re-enter your password" viewable label="Confirm Password"/>

                    <x-button wire:click="nextStep" variant="primary" class="w-full mt-1">
                        Continue →
                    </x-button>

                </div>
            @endif

            @if ($step === 2)
                <div class="flex flex-col gap-3.5">
                
                        <x-select label="Commuter type" wire:model="commuter_type" placeholder="Select commuter type">
                            <x-select-option value="regular">Regular</x-select-option>
                            <x-select-option value="student">Student</x-select-option>
                            <x-select-option value="senior">Senior citizen</x-select-option>
                            <x-select-option value="pwd">PWD</x-select-option>
                        </x-select>
                
                        <x-input wire:model="age" type="number" placeholder="e.g. 22" min="5" max="120" label="Age"/>
                        <x-input wire:model="phone_number" type="tel" placeholder="e.g. 09171234567" label="Phone number"/>
                        <x-input wire:model="email_address" type="email" placeholder="e.g. juandelacruz@example.com" label="Email"/>
                        <flux:textarea wire:model="address" placeholder="e.g. Brgy. San Roque, Iriga City" rows="2" class="resize-none" label="Address"/>

                        <div class="flex gap-2 mt-1">
                            <flux:button wire:click="prevStep" class="flex-1">
                                ← Back
                            </flux:button>
                            <flux:button wire:click="register" variant="primary" class="flex-[2]">
                                Create account
                            </flux:button>
                        </div>

                </div>
            @endif

        </flux:card>

        <x-text class="text-center text-[13px] text-[var(--color-text-tertiary)] mt-4">
            Already have an account?
            <flux:link href="{{ route('login') }}" class="font-medium">Sign in</flux:link>
        </x-text>

    </div>
</div>
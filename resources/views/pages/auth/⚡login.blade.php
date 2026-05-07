<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate; 
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

new #[Layout('layouts::login_layout')] class extends Component
{
    #[Validate('required|string')]
    public $email = '';

    #[Validate('required|string')]
    public $password = '';

    public function login() {

        $validated_attributes = $this->validate();

        // Attempt to login
        if (!Auth::attempt($validated_attributes)) {
            throw ValidationException::withMessages([
                'email' => 'Sorry, those credentials do not match.'
            ]);
        }

        // Regenerate the token
        request()->session()->regenerate();

        $user_role = auth()->user()->role;

        //redirect

        return $this->redirect("/".$user_role."/dashboard");
            
        }
    };
?>

<form wire:submit="login" method="POST">
    @csrf

    <flux:field class="mt-5">
        <flux:label>Email</flux:label>

    <flux:input 
        type="text" 
        wire:model.blur.live="email"
        name="email" 
        placeholder="Enter your email"
    />

        <flux:error name="email" />

    </flux:field>

    <flux:field class="mt-5">
        <flux:label>Password</flux:label>

        <flux:input 
            type="password" 
            wire:model.blur="password" 
            name="password" 
            viewable
            required
            />

        <flux:error name="password" />

    </flux:field>

    <flux:button type="submit" class="w-full mt-5">
        Sign in
    </flux:button>

</form>
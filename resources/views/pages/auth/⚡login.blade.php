<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

new #[Layout('layouts::login')] class extends Component
{
    #[Validate('required|string')]
    public $email = '';

    #[Validate('required|string')]
    public $password = '';

    public bool $remember = false;

    public int $rateLimitedFor = 0; 

    private function throttleKey(): string
    {
        return Str::lower($this->email) . '|' . request()->ip();
    }

    private function ensureIsNotRateLimited(): void
    {
        if (!RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        $this->rateLimitedFor = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => 'Too many login attempts.',
        ]);
    }

    public function login()
    {
        $this->ensureIsNotRateLimited();

        $validated_attributes = $this->validate();

        if (!Auth::attempt($validated_attributes, $this->remember)) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => 'Sorry, those credentials do not match.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());

        request()->session()->regenerate();

        return $this->redirect('/' . auth()->user()->role . '/dashboard');
    }
};
?>

<form 
    wire:submit="login" 
    method="POST"
    x-data="countdown($wire)"
    x-init="init()"
>
    @csrf

    <flux:field class="mt-5">
        <flux:label>Email</flux:label>
        <flux:input
            type="text"
            wire:model.blur.live="email"
            name="email"
            placeholder="Enter your email"
            required
        />

        <flux:error name="email" />

        <p 
            x-show="secondsLeft > 0" 
            x-text="'Too many attempts. Try again in ' + secondsLeft + 's.'"
            class="text-sm text-red-500 mt-1"
        ></p>
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

    <flux:field class="mt-5">
        <flux:checkbox 
            wire:model="remember" 
            label="Remember me" 
        />
    </flux:field>


    <flux:button 
        type="submit" 
        class="w-full mt-5"
    >
        Sign in
    </flux:button>

</form>

@script
<script>
    Alpine.data('countdown', ($wire) => ({
        secondsLeft: 0,
        timer: null,

        init() {
            $wire.$watch('rateLimitedFor', (val) => {
                if (val > 0) this.start(val);
            });
        },

        start(seconds) {
            clearInterval(this.timer);
            this.secondsLeft = seconds;

            this.timer = setInterval(() => {
                this.secondsLeft--;
                if (this.secondsLeft <= 0) {
                    this.secondsLeft = 0;
                    clearInterval(this.timer);
                }
            }, 1000);
        },
    }));
</script>
@endscript
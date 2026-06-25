<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

use App\Services\AuditLogsService;

new #[Layout('layouts::public-layout')] class extends Component
{
    #[Validate('required|string')]
    public $username = '';

    #[Validate('required|string')]
    public $password = '';

    public bool $remember = false;

    public int $rateLimitedFor = 0; 

    private function throttleKey(): string
    {
        return Str::lower($this->username) . '|' . request()->ip();
    }

    private function ensureIsNotRateLimited(): void
    {
        if (!RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        $this->rateLimitedFor = RateLimiter::availableIn($this->throttleKey());

        $attributes = [
            'user' => null,
            'action' => 'login_failed',
            'subject' => "Failed login - $this->username",
            'channel' => "Web",
            'metadata' => json_encode([
                'ip_address' => request()->ip(),
                'message' => ' Too many login attempts.',
            ]),
        ];

        app(AuditLogsService::class)->create($attributes);


        throw ValidationException::withMessages([
            'username' => 'Too many login attempts.',
        ]);
    }

    public function login()
    {
        $this->ensureIsNotRateLimited();

        $validated_attributes = $this->validate();

        if (!Auth::attempt($validated_attributes, $this->remember)) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'username' => 'Sorry, those credentials do not match.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());

        request()->session()->regenerate();

        return $this->redirect('/' . auth()->user()->role . '/dashboard');
    }
};
?>
    <div class="w-full min-h-[calc(100vh-64px)] flex flex-col items-center justify-center px-4">
      
      <div class="mb-4">
        <img src="{{ Vite::asset('resources/images/logo.png') }}" alt="Terminal Logo" class="size-24 object-contain">
      </div>

      <div class="flex flex-col justify-center items-center py-2">

        <div> Welcome to SMART Iriga City </div>

        <div>Central Terminal</div>

      </div>

      <div class="flex flex-col justify-center items-center w-full">

        <div class="w-full max-w-sm">

          <form 
    wire:submit="login" 
    method="POST"
    x-data="countdown($wire)"
    x-init="init()"
    >
    @csrf

    <flux:field class="mt-5">
        <flux:label>username</flux:label>
        <flux:input
            type="text"
            wire:model.blur.live="username"
            name="username"
            placeholder="Enter your username"
            required
        />

        <flux:error name="username" />

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
          
        </div>

        <div class="py-10 text-sm text-zinc-500 dark:text-zinc-400">

            <flux:text>
              
              Not a member?

              <flux:link href="#"> Register</flux:link>
              
            </flux:text>

        </div>

    </div>
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
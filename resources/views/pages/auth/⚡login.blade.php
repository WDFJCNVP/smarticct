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
    <div class="flex h-[calc(100vh-120px)] overflow-hidden">

    {{-- ── HERO PANEL ── --}}
    <div class="hidden md:flex w-5/12 h-full relative flex-col p-8 overflow-hidden">

        <div class="absolute inset-0">
            <a href="/">
                <img 
                    src="{{ Vite::asset('resources/images/iriga-terminal.jpg') }}" 
                    alt="SmartICCT" 
                    class="h-10 w-auto md:h-full"
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
    <div class="flex flex-1 flex-col justify-center px-6 py-8 sm:px-12 bg-light-secondary dark:bg-dark-secondary overflow-hidden h-full">

        <div class="w-full max-w-sm mx-auto">

            <p class="font-secondary text-nav-label font-semibold uppercase tracking-widest text-secondary mb-1">Sign in</p>
            <h2 class="font-primary text-page-title font-bold text-light-txt-primary dark:text-dark-txt-primary mb-1">Access your account</h2>
            <p class="font-secondary text-body text-light-txt-muted dark:text-dark-txt-muted mb-4">
                Enter your credentials to continue.
            </p>

            <form
                wire:submit="login"
                method="POST"
                x-data="countdown($wire)"
                x-init="init()"
            >
                @csrf

                <flux:field class="mt-3">
                    <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">Username</flux:label>
                    <flux:input
                        type="text"
                        wire:model.blur.live="username"
                        name="username"
                        placeholder="Enter your username"
                        required
                        class="font-secondary text-table-row bg-light-primary dark:bg-dark-surface text-light-txt-body dark:text-dark-txt-primary border-light-bd-default dark:border-dark-bd-default placeholder:text-light-txt-muted dark:placeholder:text-dark-txt-muted"
                    />

                    <flux:error name="username" />

                    <p
                        x-show="secondsLeft > 0"
                        x-text="'Too many attempts. Try again in ' + secondsLeft + 's.'"
                        class="font-secondary text-helper text-danger mt-1"
                    ></p>
                </flux:field>

                <flux:field class="mt-3">
                    <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">Password</flux:label>
                    <flux:input
                        type="password"
                        wire:model.blur="password"
                        name="password"
                        viewable
                        required
                        class="font-secondary text-table-row bg-light-primary dark:bg-dark-surface text-light-txt-body dark:text-dark-txt-primary border-light-bd-default dark:border-dark-bd-default placeholder:text-light-txt-muted dark:placeholder:text-dark-txt-muted"
                    />
                    <flux:error name="password" />
                </flux:field>

                <flux:field class="mt-3">
                    <flux:checkbox
                        wire:model="remember"
                        label="Remember me"
                        class="font-secondary text-table-row text-light-txt-primary dark:text-dark-txt-muted"
                        />
                    </flux:field>

                <flux:button
                    type="submit"
                    class="font-primary hover:bg-secondary! active:bg-secondary/40! dark:hover:bg-secondary! dark:active:bg-secondary/40! text-table-row !bg-primary !text-white !font-semibold w-full mt-3"
                    variant="filled" 
                >
                    Sign in
                </flux:button>
            </form>

            <div class="py-5">
                <flux:text class="font-secondary text-timestamp text-light-txt-muted dark:text-dark-txt-muted">
                    Not a member?
                    <flux:link href="#" class="font-secondary text-timestamp text-secondary font-medium"> Register</flux:link>
                </flux:text>
            </div>

        </div>
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
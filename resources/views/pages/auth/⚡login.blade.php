<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

use App\Services\AuditLogsService;

new #[Layout('layouts.public-account-setup')] class extends Component
{
    #[Validate('required|string|max:255')]
    public $email_address = '';

    #[Validate('required|string')]
    public $password = '';

    public bool $remember = false;

    public int $rateLimitedFor = 0; 

    private function throttleKey(): string
    {
        return Str::lower($this->email_address) . '|' . request()->ip();
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
            'subject' => "Failed login - $this->email_address",
            'channel' => "Web",
            'metadata' => json_encode([
                'ip_address' => request()->ip(),
                'message' => ' Too many login attempts.',
            ]),
        ];

        app(AuditLogsService::class)->create($attributes);

        throw ValidationException::withMessages([
            'email_address' => 'Too many login attempts.',
        ]);
    }

    public function login()
    {
        $this->ensureIsNotRateLimited();

        $validated_attributes = $this->validate();

        if (!Auth::attempt($validated_attributes, $this->remember)) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email_address' => 'Sorry, those credentials do not match.',
            ]); 
        }

        if (auth()->user()->isDeleted()) {
            Auth::logout();

            app(AuditLogsService::class)->create([
                'user_id' => null,
                'action' => 'login_blocked_deleted',
                'subject' => "Deleted account login attempt - $this->email_address",
                'channel' => 'Web',
                'metadata' => json_encode([
                    'ip_address' => request()->ip(),
                    'message' => 'Login blocked: account has been deleted.',
                ]),
            ]);

            throw ValidationException::withMessages([
                'email_address' => 'This account has been deleted.',
            ]);
        }

        if (auth()->user()->isSuspended()) {
            $reason = auth()->user()->userStatus?->suspension_reason;

            Auth::logout();

            app(AuditLogsService::class)->create([
                'user_id' => null,
                'action' => 'login_blocked_suspended',
                'subject' => "Suspended account login attempt - $this->email_address",
                'channel' => 'Web',
                'metadata' => json_encode([
                    'ip_address' => request()->ip(),
                    'message' => 'Login blocked: account is suspended.',
                ]),
            ]);

            throw ValidationException::withMessages([
                'email_address' => $reason
                    ? "Your account has been suspended. Reason: {$reason} Please visit the terminal office to have your account reviewed."
                    : 'Your account has been suspended. Please visit the terminal office to have your account reviewed.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());

        request()->session()->regenerate();

        return $this->redirect('/' . auth()->user()->role . '/dashboard');
    }
};
?>

<div class="flex min-h-full md:h-full overflow-y-auto md:overflow-hidden p-4 sm:p-6 md:p-10! relative" x-data="{ loaded: false }" x-init="setTimeout(() => loaded = true, 100)">


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
            Your journey <br>starts here.
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

    <div 
    x-show="loaded" 
    x-transition:enter.duration.700.delay.200
    x-transition:enter.start.opacity-0.translate-x-5
    x-transition:enter.end.opacity-100.translate-x-0
    class="flex flex-1 flex-col justify-center px-6 py-8 sm:px-10 md:px-12 bg-white/80 dark:bg-dark-secondary/80 backdrop-blur-sm overflow-y-auto md:overflow-hidden min-h-full md:h-full"
>
    <div class="w-full max-w-sm mx-auto">

        {{-- Mobile Brand Header (visible only on small screens) --}}
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
            {{-- Full-width gold divider --}}
            <div class="w-full h-0.5 bg-secondary/60 dark:bg-secondary/80 mx-auto mt-3"></div>
        </div>

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
                <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">Email Address</flux:label>
                <flux:input
                    type="text"
                    wire:model="email_address"
                    name="email_address"
                    placeholder="Enter your email address"
                    required
                    class="font-secondary text-table-row rounded-lg bg-light-primary dark:bg-dark-surface text-light-txt-body dark:text-dark-txt-primary border-light-bd-default dark:border-dark-bd-default placeholder:text-light-txt-muted dark:placeholder:text-dark-txt-muted transition-shadow duration-200 focus:ring-2 focus:ring-secondary/50"
                />
                <flux:error name="email_address" class="font-secondary text-helper text-danger dark:text-dark-danger mt-1" />

                <p
                    x-show="secondsLeft > 0"
                    x-text="'Too many attempts. Try again in ' + secondsLeft + 's.'"
                    class="font-secondary text-helper text-danger mt-1 transition-opacity duration-300"
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
                    class="font-secondary text-table-row rounded-lg bg-light-primary dark:bg-dark-surface text-light-txt-body dark:text-dark-txt-primary border-light-bd-default dark:border-dark-bd-default placeholder:text-light-txt-muted dark:placeholder:text-dark-txt-muted transition-shadow duration-200 focus:ring-2 focus:ring-secondary/50"
                />
                <flux:error name="password" class="font-secondary text-helper text-danger dark:text-dark-danger mt-1" />
            </flux:field>

            <flux:field class="mt-3 flex items-center justify-between">
                <flux:checkbox
                    wire:model="remember"
                    label="Remember me"
                    class="font-secondary text-table-row text-light-txt-primary dark:text-dark-txt-muted"
                />

                <flux:link href="{{ route('forgot.password') }}">
                    <x-text class="font-secondary text-table-row text-light-txt-primary dark:text-dark-txt-muted dark:hover:text-secondary font-medium cursor-pointer">
                        Forgot password ?
                    </x-text>
                </flux:link>
            </flux:field>

            <flux:button
                type="submit"
                class="font-primary hover:bg-secondary! dark:hover:bg-secondary! text-table-row !bg-primary !text-white !font-semibold w-full mt-3 transition-transform duration-200 hover:scale-[1.02] active:scale-[0.97]"
                variant="filled"
            >
                Sign in
            </flux:button>
        </form>

        <div class="py-5">
            <flux:text class="font-secondary text-timestamp text-light-txt-muted dark:text-dark-txt-muted">
                Not a member?
                <flux:link href="{{ route('public.register') }}" wire:navigate class="font-secondary text-timestamp text-secondary font-medium"> Register</flux:link>
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
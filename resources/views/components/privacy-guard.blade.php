@props([
    'title' => 'Your personal information',
    'description' => "This shows details like your name, email, phone number, and address. Enter your password to confirm it's you before viewing.",
])

@php
    // "Not now" needs somewhere sensible to go back to if there's no browser
    // history to fall back on (e.g. the page was opened directly).
    $role = auth()->user()->role ?? null;
    $fallbackRoute = match ($role) {
        'admin'    => route('admin.dashboard'),
        'operator' => route('operator.dashboard'),
        'commuter' => route('commuter.dashboard'),
        'cashier'  => route('cashier.dashboard'),
        default    => url('/'),
    };
@endphp

<div
    x-data="{
        revealed: false,
        password: '',
        verifying: false,
        error: '',
        async confirm() {
            if (!this.password) { this.error = 'Please enter your password.'; return; }
            this.verifying = true;
            this.error = '';
            try {
                const ok = await $wire.verifyPrivacyGuardPassword(this.password);
                if (ok) {
                    this.revealed = true;
                    this.password = '';
                } else {
                    this.error = 'Incorrect password.';
                }
            } catch (e) {
                this.error = 'Something went wrong. Please try again.';
            }
            this.verifying = false;
        }
    }"
    class="relative"
>

    <div
        :class="revealed ? '' : 'blur-md select-none pointer-events-none'"
        class="transition duration-200"
        :aria-hidden="(!revealed).toString()"
    >
        {{ $slot }}
    </div>

    <div
        x-show="!revealed"
        x-cloak
        class="absolute inset-0 z-20 flex items-center justify-center p-4 rounded-xl bg-light-secondary/70 dark:bg-dark-secondary/80 backdrop-blur-[1px]"
    >
        <div class="w-full max-w-sm rounded-xl border border-light-bd-default dark:border-dark-bd-default bg-white dark:bg-dark-surface shadow-lg p-5 text-center space-y-4">
            <div class="mx-auto flex items-center justify-center w-10 h-10 rounded-full bg-primary/10 dark:bg-dark-primary/20">
                <flux:icon name="eye-slash" class="w-5 h-5 text-primary dark:text-dark-txt-primary" />
            </div>

            <div>
                <flux:heading size="lg">{{ $title }}</flux:heading>
                <flux:text class="mt-1 text-sm text-light-txt-muted dark:text-dark-txt-muted">
                    {{ $description }}
                </flux:text>
            </div>

            <div class="text-left space-y-1.5">
                <flux:input
                    type="password"
                    x-model="password"
                    placeholder="Enter your password"
                    autocomplete="current-password"
                    @keydown.enter="confirm()"
                    x-bind:disabled="verifying"
                    class="font-secondary text-table-row"
                />
                <p x-show="error" x-cloak class="text-xs text-danger dark:text-dark-danger" x-text="error"></p>
            </div>

            <div class="flex flex-col sm:flex-row gap-2 pt-1">
                <flux:button
                    type="button"
                    variant="ghost"
                    class="w-full"
                    @click="window.history.length > 1 ? window.history.back() : (window.location.href = '{{ $fallbackRoute }}')"
                >
                    Not now
                </flux:button>
                <flux:button
                    type="button"
                    variant="primary"
                    class="w-full"
                    @click="confirm()"
                    x-bind:disabled="verifying"
                >
                    <span x-show="!verifying">Confirm</span>
                    <span x-show="verifying" x-cloak>Checking…</span>
                </flux:button>
            </div>
        </div>
    </div>

    {{-- Lets them re-blur it themselves if someone walks up while it's open. --}}
    <button
        type="button"
        x-show="revealed"
        x-cloak
        @click="revealed = false"
        class="absolute -top-3 right-0 z-10 inline-flex items-center gap-1 font-secondary text-xs text-light-txt-muted dark:text-dark-txt-muted hover:text-light-txt-primary dark:hover:text-dark-txt-primary transition"
    >
        <flux:icon name="eye-slash" class="w-3.5 h-3.5" />
        Hide again
    </button>
</div>
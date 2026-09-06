@props([
    'heading'     => '',
    'description' => '',
])

<div
    class="hidden sm:block sticky top-0 z-30 -mx-4 sm:-mx-6 lg:-mx-8 -mt-6 lg:-mt-8 px-4 sm:px-6 lg:px-8 py-3
           bg-white/90 dark:bg-dark-secondary/90 backdrop-blur
           border-b border-light-bd-default dark:border-dark-bd-default mb-6"
>
    <div class="flex items-center justify-between gap-3 sm:gap-4">
        {{-- Left: greeting --}}
        <div class="min-w-0">
            <p class="font-primary text-base sm:text-lg font-bold text-light-txt-primary dark:text-dark-txt-primary truncate">
                Hi, {{ auth()->user()->name }}!
            </p>
            @if ($heading)
                <p class="font-secondary text-xs text-light-txt-muted dark:text-dark-txt-muted truncate">
                    {{ $heading }}@if ($description) &middot; {{ $description }} @endif
                </p>
            @endif
        </div>

        {{-- Right: date + clock, notifications, and any extra controls (e.g. Filters) --}}
        <div class="flex items-center gap-2 sm:gap-3 shrink-0">
            <div class="flex flex-col items-end">
                <span
                    class="text-xs text-light-txt-muted dark:text-dark-txt-muted font-secondary leading-none mb-0.5"
                    x-data="{ date: '' }"
                    x-init="date = new Date().toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })"
                    x-text="date"
                ></span>
                <div
                    class="flex items-center gap-1.5 font-primary text-sm sm:text-base font-bold tabular-nums text-light-txt-primary dark:text-dark-txt-primary whitespace-nowrap"
                    x-data="{
                        now: '',
                        tick() {
                            this.now = new Date().toLocaleString('en-US', {
                                hour: '2-digit', minute: '2-digit', second: '2-digit',
                            });
                        },
                    }"
                    x-init="tick(); setInterval(() => tick(), 1000)"
                >
                    <span x-text="now"></span>
                </div>
            </div>

            {{-- Notifications: bordered pill + label so it clearly reads as a button --}}
            <livewire:pages::notification-bell />

            {{ $slot }}
        </div>
    </div>
</div>
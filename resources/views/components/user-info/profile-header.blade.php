@props(['user', 'vehicleCount' => 0])

    {{-- User profile header --}}
    <div class="mt-4 mb-6 p-4 sm:p-5 rounded-xl border border-light-bd-default dark:border-dark-bd-default bg-light-secondary dark:bg-dark-secondary">
        <div class="flex items-center gap-4">
            <div class="p-1 rounded-xl bg-primary/10 dark:bg-primary/25 shrink-0">
                <flux:avatar src="{{ $user->avatar_url }}" name="{{ $user->name }}" size="xl" />
            </div>
            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2 flex-wrap">
                    <x-text class="font-primary text-base sm:text-lg font-bold text-light-txt-primary dark:text-dark-txt-primary truncate">{{ $user->name }}</x-text>
                    @if (! $user->hasAccount())
                        <flux:badge color="amber" size="sm" class="shrink-0">No account</flux:badge>
                    @elseif (! $user->card)
                        <span class="inline-flex items-center shrink-0 text-[11px] sm:text-xs font-semibold px-2 py-0.5 rounded-full bg-danger/10 text-danger dark:bg-dark-danger/15 dark:text-dark-danger">
                            No card
                        </span>
                    @endif
                </div>
                <div class="flex items-center gap-1.5 mt-0.5 min-w-0">
                    @if ($user->card)
                        <span class="font-secondary text-xs sm:text-sm text-light-txt-muted dark:text-dark-txt-muted font-mono truncate">{{ $user->card->card_number }}</span>
                        <span class="text-light-bd-strong dark:text-dark-bd-strong text-xs">·</span>
                    @endif
                    <span class="font-secondary text-xs sm:text-sm text-light-txt-muted dark:text-dark-txt-muted truncate">{{ $user->user_code }}</span>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-2 {{ $user->role === 'operator' ? 'sm:grid-cols-3' : 'sm:grid-cols-2' }} gap-2 sm:gap-3 mt-4">
            <div class="rounded-lg bg-light-subtle dark:bg-dark-subtle px-3 py-2">
                <span class="block text-[10px] sm:text-xs font-medium uppercase tracking-wider text-light-txt-muted dark:text-dark-txt-muted mb-1">Role</span>
                @if ($user->role === 'operator')
                    <flux:badge color="blue" size="lg">Operator</flux:badge>
                @else
                    <flux:badge color="yellow" size="lg">Commuter</flux:badge>
                @endif
            </div>
            <div class="rounded-lg bg-light-subtle dark:bg-dark-subtle px-3 py-2">
                <span class="block text-[10px] sm:text-xs font-medium uppercase tracking-wider text-light-txt-muted dark:text-dark-txt-muted mb-1">Joined</span>
                <span class="font-secondary text-lg font-medium text-light-txt-primary dark:text-dark-txt-primary">
                    {{ $user->created_at->format('M d, Y') }}
                </span>
            </div>
            @if ($user->role === 'operator')
                <div class="rounded-lg bg-light-subtle dark:bg-dark-subtle px-3 py-2">
                    <span class="block text-[10px] sm:text-xs font-medium uppercase tracking-wider text-light-txt-muted dark:text-dark-txt-muted mb-1">Vehicles</span>
                    <span class="font-secondary text-lg font-medium text-light-txt-primary dark:text-dark-txt-primary">
                        {{ $vehicleCount }}
                    </span>
                </div>
            @endif
        </div>
    </div>
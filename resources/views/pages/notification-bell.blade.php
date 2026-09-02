<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use App\Models\UserNotification;

new class extends Component
{
    #[Computed]
    public function unreadCount(): int
    {
        return UserNotification::where('user_id', auth()->id())
            ->where('is_read', false)
            ->count();
    }

    // Unread-first so a burst of new notifications doesn't get pushed out
    // of the preview by older, already-read ones.
    #[Computed]
    public function recentNotifications()
    {
        return UserNotification::with('notification')
            ->where('user_id', auth()->id())
            ->orderByDesc('is_read')
            ->latest()
            ->take(5)
            ->get();
    }

    #[On('echo:notification-event,.NotificationEvent')]
    public function refreshNotifications(): void
    {
        unset($this->unreadCount);
        unset($this->recentNotifications);
    }
};
?>

<flux:dropdown position="bottom" align="end">
    <div class="relative inline-block">
        <button
            type="button"
            class="relative flex items-center justify-center w-8 h-8 sm:w-9 sm:h-9 rounded-lg border border-light-bd-default dark:border-dark-bd-default text-light-txt-muted dark:text-dark-txt-muted hover:bg-light-subtle dark:hover:bg-dark-subtle transition shrink-0"
            aria-label="Notifications"
        >
            <flux:icon.bell class="w-3.5 h-3.5 sm:w-4 sm:h-4" />
            @if ($this->unreadCount > 0)
                <span class="absolute top-1.5 right-1.5 w-1.5 h-1.5 rounded-full bg-danger dark:bg-dark-danger"></span>
            @endif
        </button>
    </div>

    <flux:menu class="w-80 max-w-[90vw] font-secondary">
        <div class="flex items-center justify-between px-3 py-2">
            <flux:heading size="sm">Notifications</flux:heading>
            @if ($this->unreadCount > 0)
                <flux:badge color="red" size="sm">{{ $this->unreadCount > 99 ? '99+' : $this->unreadCount }} new</flux:badge>
            @endif
        </div>

        <flux:menu.separator />

        @forelse ($this->recentNotifications as $item)
            <flux:menu.item
                :href="route('notification', $item->id)"
                wire:navigate
                class="!items-start !whitespace-normal"
            >
                <div class="flex items-start gap-2 w-full py-0.5">
                    @if (! $item->is_read)
                        <span class="mt-1.5 w-1.5 h-1.5 rounded-full bg-primary dark:bg-dark-primary shrink-0"></span>
                    @else
                        <span class="mt-1.5 w-1.5 h-1.5 shrink-0"></span>
                    @endif
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-light-txt-primary dark:text-dark-txt-primary truncate">
                            {{ $item->notification?->title ?? 'No Title' }}
                        </p>
                        <p class="text-xs text-light-txt-muted dark:text-dark-txt-muted line-clamp-2">
                            {{ $item->notification?->message ?? 'No message content available.' }}
                        </p>
                        <p class="text-[11px] text-light-txt-muted dark:text-dark-txt-muted mt-0.5">
                            {{ $item->created_at->diffForHumans() }}
                        </p>
                    </div>
                </div>
            </flux:menu.item>
        @empty
            <div class="px-3 py-6 text-center">
                <flux:text class="text-sm text-light-txt-muted dark:text-dark-txt-muted">
                    You're all caught up.
                </flux:text>
            </div>
        @endforelse

        <flux:menu.separator />

        <flux:menu.item :href="route('notifications')" wire:navigate class="justify-center font-medium">
            View all
        </flux:menu.item>
    </flux:menu>
</flux:dropdown>
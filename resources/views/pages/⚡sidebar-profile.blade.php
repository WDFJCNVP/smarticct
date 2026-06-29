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

    #[On('echo:notification-event,.NotificationEvent')]
    public function refreshUnreadCount(): void
    {
        unset($this->unreadCount);
    }
};
?>


   <flux:dropdown position="bottom" align="start">

    <div class="w-full relative inline-block">
        <flux:sidebar.profile
            class="w-full"
            :name="auth()->user()->name"
            :initials="auth()->user()->initials()"
            icon:trailing="chevron-up-down"
            data-test="sidebar-menu-button"
        />

        @if ($this->unreadCount > 0)
            <span class="absolute top-0 right-0 -translate-y-1/2 translate-x-1/2 flex h-2.5 w-2.5 pointer-events-none z-10">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-red-500"></span>
            </span>
        @endif
    </div>

        <flux:menu>

            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                <div class="relative">
                    <flux:avatar
                        :name="auth()->user()->name"
                        :initials="auth()->user()->initials()"
                    />
                </div>
                <div class="grid flex-1 text-start text-sm leading-tight">
                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                </div>
            </div>

            <flux:menu.separator />

            <flux:menu.radio.group>

                {{-- Notification item with unread count badge --}}
                <flux:menu.item
                    :href="route('notifications')"
                    icon="bell"
                    wire:navigate
                >
                    <div class="flex items-center justify-between w-full gap-2">
                        <span>Notifications</span>
                        @if ($this->unreadCount > 0)
                            <flux:badge class="inline-flex items-center justify-center" color="red" size="sm" data-test="unread-notifications-badge">
                                {{ $this->unreadCount > 99 ? '99+' : $this->unreadCount }}
                            </flux:badge>
                        @endif
                    </div>
                </flux:menu.item>

                <flux:menu.item
                    :href="route('profile.edit')"
                    icon="cog"
                    wire:navigate
                >
                    Settings
                </flux:menu.item>

                <form method="POST" action="{{ route('logout') }}" class="w-full">
                    @csrf
                    <flux:menu.item
                        as="button"
                        type="submit"
                        icon="arrow-right-start-on-rectangle"
                        class="w-full cursor-pointer"
                        data-test="logout-button"
                    >
                        Log out
                    </flux:menu.item>
                </form>

            </flux:menu.radio.group>
        </flux:menu>
    </flux:dropdown>

<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;

use App\Models\UserNotification;
use App\Events\NotificationEvent;

new #[Layout('layouts.admin-layout')] class extends Component
{

    #[Computed]
    public function getNotifications() {
        return UserNotification::with('notification')->where('user_id', auth()->user()->id)->latest()->get();
    }

    #[On('echo:notification-event,.NotificationEvent')]
    public function refreshNotifications() {
        unset($this->getNotifications);
    }
};
?>

<div>
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div class="flex-1 flex items-center gap-2">
            <flux:heading size="xl">Notifications</flux:heading>

            @php
                
                $total_unread_notification = 0;

                foreach($this->getNotifications as $notification) {
                    if ($notification->is_read === 0) {
                        $total_unread_notification += 1;
                    }
                }

            @endphp

            @if ($total_unread_notification)
                <flux:badge color="blue" size="sm">{{ $total_unread_notification }} Unread</flux:badge>
            @else
                <flux:badge color="zinc" size="sm">All read</flux:badge>
            @endif            
        </div>

        <div>
            <x-button size="sm" wire:click="markAllAsRead" class="w-full sm:w-auto justify-center">Mark all as read </x-button>
        </div>

    </div>

    @foreach ($this->getNotifications as $notification)
        <x-notification-container :notification_id="$notification->id" href="/admin/notification/{{ $notification->id }}">
            @if ($notification->is_read === 0)  
                <div class="relative inline-block shrink-0">
                    <span class="absolute -top-0.2 -left-0.2 z-10 block h-1.5 w-1.5 rounded-full bg-danger dark:bg-dark-danger ring-2 ring-light-primary dark:ring-dark-surface"></span>
                    <flux:icon.envelope class="h-5 w-5 text-primary dark:text-dark-txt-primary" />
                </div>
            @else
                <flux:icon.envelope class="h-5 w-5 text-primary dark:text-dark-txt-primary shrink-0" />
            @endif

            <div class="flex-1 min-w-0">
                <x-text color="blue"  variant="strong">
                    {{ $notification->notification->title }}
                </x-text>

                <div class="flex items-center gap-2" >
                    <x-text class="flex-1 min-w-0 text-xs text-light-txt-muted dark:text-dark-txt-muted mt-0.5 leading-snug truncate">
                        {{ $notification->notification->message }}
                    </x-text>

                    <div class="flex items-center gap-2 mt-1.5 shrink-0">
                        <span class="text-xs text-light-txt-muted dark:text-dark-txt-muted flex items-center gap-1 whitespace-nowrap">
                            {{ $notification->created_at->format('F d, Y') }}
                        </span>
                    </div>
                </div>
            </div>
        </x-notification-container>
    @endforeach

</div>
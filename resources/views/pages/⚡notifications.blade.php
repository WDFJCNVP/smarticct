<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;

use App\Models\UserNotification;
use App\Events\NotificationEvent;

new class extends Component
{

    public function markAllAsRead() {
        UserNotification::where('user_id', auth()->user()->id)->update(['is_read' => 1]);

        event(new NotificationEvent());
    }

    #[Computed]
    public function getNotification() {
        return UserNotification::with('notification')
            ->where('user_id', auth()->user()->id)
            ->latest()
            ->get();

    }

    #[On('echo:notification-event,.NotificationEvent')]
    public function reloadNotification() {
        unset($this->getNotification);
    }

    public function render() {
        $role = auth()->user()->role;

        return $this->view()->layout('layouts.' . $role . '-layout');
    }

    // public function mount() {
    //     dd($this->getNotification);
    // }
};
?>

<div>
    <div class="flex items-center mb-6">
        <div class="flex-1 flex items-center gap-2">
            <flux:heading size="xl" class="font-primary">Notifications</flux:heading>

            @php
                
                $total_unread_notification = 0;

                foreach($this->getNotification as $notification) {
                    if ($notification->is_read === 0) {
                        $total_unread_notification += 1;
                    }
                }

            @endphp

            @if ($total_unread_notification)
                <flux:badge color="blue" size="sm" class="font-secondary">{{ $total_unread_notification }} Unread</flux:badge>
            @elseif($this->getNotification->isEmpty())
            @else
                <flux:badge color="zinc" size="sm" class="font-secondary">All read</flux:badge>
            @endif            
        </div>

        <div>
            @if ($this->getNotification->isNotEmpty())
                <x-button size="sm" wire:click="markAllAsRead">Mark all as read </x-button>
            @endif
        </div>

    </div>

    <div>
        @forelse ($this->getNotification as $notification)

            <x-notification-container :notification_id="$notification->id" href="/notification/{{ $notification->id }}" >
                @if ($notification->is_read === 0)  
                    <div class="relative inline-block">
                        <span class="absolute -top-0.2 -left-0.2 z-10 block h-1.5 w-1.5 rounded-full bg-danger ring-2 ring-white dark:ring-dark-surface"></span>
                        <flux:icon.envelope class="h-5 w-5 text-primary dark:text-white" />
                    </div>
                @else
                    <flux:icon.envelope class="h-5 w-5 text-primary dark:text-white" />
                @endif

                <div class="flex-1 min-w-0">
                    <x-text color="blue" variant="strong" class="font-secondary text-light-txt-primary dark:text-dark-txt-primary">
                        {{ $notification->notification?->title ?? 'No Title' }}
                    </x-text>

                    <div class="flex items-center" >
                        <x-text class="flex-1 font-secondary text-xs text-light-txt-muted dark:text-dark-txt-muted mt-0.5 leading-snug truncate">
                            {{ $notification->notification?->message ?? 'No message content available.' }}
                        </x-text>

                        <div class="flex items-center gap-2 mt-1.5">
                            <span class="font-secondary text-xs text-light-txt-muted dark:text-dark-txt-muted flex items-center gap-1">
                                {{ $notification->created_at->format('F d, Y') }}
                            </span>
                        </div>
                    </div>
                </div>
            </x-notification-container>
        @empty

        <flux:card>
            <flux:text class="font-secondary text-light-txt-muted dark:text-dark-txt-muted">There's no notification yet.</flux:text>
        </flux:card>

        @endforelse

    </div>
</div>
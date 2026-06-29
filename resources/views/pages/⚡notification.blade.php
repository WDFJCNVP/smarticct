<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;

use App\Models\UserNotification;

new class extends Component
{

    public UserNotification $user_notification;

    public function destroyNotification($notification_id) {
        $notification = UserNotification::find($notification_id);
        if ($notification) {
            $notification->delete();

           return $this->redirect(route('notifications'), navigate: true);
        }
    }

    #[Computed]
    public function notificationDetails() 
    {
        if (!$this->user_notification->relationLoaded('notification')) {
            $this->user_notification->load('notification');
        }

        return $this->user_notification;
    }

    public function render() {
        $role = auth()->user()->role;
        return $this->view()->layout('layouts.' . $role . '-layout');
    }

    public function mount() {
        if($this->user_notification->is_read === 0) {
            $this->user_notification->update(['is_read' => true]);
        }
    }
};
?>

<div>
    <div class="flex mb-4">
        <flux:breadcrumbs class="flex-1">
            <flux:breadcrumbs.item href="{{ route('notifications') }}" wire:navigate>
                Notifications
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item>Inbox</flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <flux:modal.trigger name="delete-notification">
            <flux:button variant="ghost" icon="trash" size="sm" class="text-red-500 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-950" />
        </flux:modal.trigger>
    </div>

    <x-card class="overflow-hidden">
        <div class="h-1 w-full bg-blue-500 -mt-4 mb-4 rounded-t-xl"></div>

        <div class="flex items-start gap-4 px-2">
            <div class="flex-shrink-0 w-10 h-10 rounded-full bg-blue-100 dark:bg-blue-900 flex items-center justify-center">
                <flux:icon.envelope class="w-5 h-5 text-blue-600 dark:text-blue-400" />
            </div>

            <div class="flex-1 min-w-0">
                <x-pages-heading
                    class="!text-blue-600 dark:!text-blue-400 !mb-0.5"
                    heading="{{ $this->notificationDetails->notification->title ?? 'No Title' }}"
                />
                <div class="flex items-center gap-2 flex-wrap">
                    <x-text class="text-xs text-gray-400">
                        {{ $this->notificationDetails->created_at?->format('F d, Y \a\t h:i a') ?? 'N/A' }}
                    </x-text>
                </div>
            </div>
        </div>

        <flux:separator class="my-4" />

        <div class="px-2 pb-2">
            <x-text variant="strong">

                {{ $this->notificationDetails->notification->message ?? 'No Message Content available.' }}
            </x-text>
        </div>
    </x-card>

    <flux:modal name="delete-notification" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Delete notification?</flux:heading>
                <flux:text class="mt-2">
                    You're about to delete this notification.<br>
                    This action cannot be reversed.
                </flux:text>
            </div>
            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">Cancel</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="destroyNotification({{ $this->user_notification->id }})">Delete notification</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
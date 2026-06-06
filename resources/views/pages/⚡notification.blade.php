<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;

use App\Models\UserNotification;

new  class extends Component
{
    public UserNotification $user_notification;

    #[Computed]
    public function getUserNotification() {
        return UserNotification::with('notification')->where('notification_id', $this->user_notification->id)->first();
    }

    public function render() {
        $role = auth()->user()->role;

        return $this->view()->layout('layouts.' . $role . '-layout');
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

        <flux:button variant="ghost" icon="trash" size="sm" class="text-red-500 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-950" />
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
                    heading="{{ $this->getUserNotification()->notification->title }}"
                />
                <div class="flex items-center gap-2 flex-wrap">
                    <x-text class="text-xs text-gray-400">
                        {{ $this->getUserNotification()->created_at->format('F d, Y \a\t h:i a') }}
                    </x-text>
                </div>
            </div>
        </div>

        <flux:separator class="my-4" />

        <div class="px-2 pb-2">
            <x-text variant="strong">
                {{ $this->getUserNotification()->notification->message }}
            </x-text>
        </div>

    </x-card>
</div>
<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;

use App\Models\UserNotification;

new  #[Layout('layouts.operator-layout')] class extends Component
{
    public UserNotification $user_notification;

    #[Computed]
    public function getUserNotification() {
        return UserNotification::with('notification')->where('notification_id', $this->user_notification->id)->first();
    }
};
?>

<div>
    <div class="flex items-center">
        <flux:breadcrumbs class="flex-1">
            <flux:breadcrumbs.item href="{{ route('operator.all.notifications') }}" wire:navigate>Notifications</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>Inbox</flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div>
            <x-button variant="ghost" icon="trash" class="text-color:red"></x-button>
        </div>
    </div>

    <x-card>
        <div class="flex justify-center items-center flex-col gap-4">
            <flux:icon.envelope class="w-5 h-5 text-blue-600"/>
            <div>
                <x-text>
                    {{ $this->getUserNotification()->created_at->format('F d, Y h:i a') }}
                </x-text>
            </div>
            <div>
                <x-pages-heading class="!text-blue-600 dark:!text-blue-400" heading="{{ $this->getUserNotification()->notification->title }}">
                </x-pages-heading>
            </div>

            <flux:separator />

            <x-text class="text-gray-600 dark:text-gray-300 whitespace-pre-wrap">
                {{ $this->getUserNotification()->notification->message }}
            </x-text>

        </div>
    </x-card>
</div>
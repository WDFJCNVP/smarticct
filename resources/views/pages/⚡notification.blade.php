<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;

use App\Models\RentalRequest;
use App\Models\UserNotification;

new class extends Component
{

    public UserNotification $user_notification;

    public function destroyNotification($notification_id) {
        $notification = UserNotification::where('id', $notification_id)
            ->where('user_id', auth()->id())
            ->first();
        if ($notification) {
            $notification->delete();

            Flux::toast(
                duration: 4000,
                variant: 'success',
                heading: 'Notification deleted',
                text: 'The notification has been removed.',
            );

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

    /**
     * For a rental notification: the page where this user can see the request.
     * Operator -> the review page, passenger -> My requests. Nothing for anyone else.
     */
    #[Computed]
    public function rentalLink(): ?array
    {
        $notification = $this->notificationDetails->notification;

        if (! $notification || $notification->type !== 'Rental') {
            return null;
        }

        $requestId = $notification->metadata['rental_request_id'] ?? null;
        $request   = $requestId ? RentalRequest::with('vehicle')->find($requestId) : null;

        if (! $request) {
            return null;
        }

        $userId = auth()->id();

        if ($request->vehicle->user_id === $userId) {
            return ['url' => route('rental-request-review', ['rentalRequest' => $request->id]), 'label' => 'View request'];
        }

        if ($request->passenger_id === $userId) {
            return ['url' => route('my-rental-requests'), 'label' => 'View my requests'];
        }

        return null;
    }

    public function render() {
        $role = auth()->user()->role;
        return $this->view()->layout('layouts.' . $role . '-layout');
    }

    public function mount() {
        abort_if($this->user_notification->user_id !== auth()->id(), 404);

        if($this->user_notification->is_read === 0) {
            $this->user_notification->update(['is_read' => true]);
        }
    }
};
?>

<div>
    <div class="flex mb-4">
        <flux:breadcrumbs class="flex-1 font-secondary">
            <flux:breadcrumbs.item href="{{ route('notifications') }}" wire:navigate>
                Notifications
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item>Inbox</flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <flux:modal.trigger name="delete-notification">
            <flux:button variant="ghost" icon="trash" size="sm" class="!text-danger dark:!text-dark-danger hover:!bg-danger/10 dark:hover:!bg-dark-danger/10" />
        </flux:modal.trigger>
    </div>

    <x-card class="overflow-hidden">
        <div class="flex items-start gap-4 px-2">
            <div class="flex-shrink-0 w-10 h-10 rounded-full bg-primary/10 dark:bg-white/10 flex items-center justify-center">
                @if ($this->notificationDetails->notification?->type === 'Rental')
                    <flux:icon.truck class="w-5 h-5 text-primary dark:text-white" />
                @else
                    <flux:icon.envelope class="w-5 h-5 text-primary dark:text-white" />
                @endif
            </div>

            <div class="flex-1 min-w-0">
                <x-pages-heading
                    class="font-primary !text-primary dark:!text-white !mb-0.5"
                    heading="{{ $this->notificationDetails->notification->title ?? 'No Title' }}"
                />
                <div class="flex items-center gap-2 flex-wrap">
                    <x-text class="font-secondary text-xs text-light-txt-muted dark:text-dark-txt-muted">
                        {{ $this->notificationDetails->created_at?->format('F d, Y \a\t h:i a') ?? 'N/A' }}
                    </x-text>
                </div>
            </div>
        </div>

        <flux:separator class="my-4" />

        <div class="px-2 pb-2">
            {{-- whitespace-pre-line shows each detail line of a rental message on its own line --}}
            <x-text variant="strong" class="font-secondary whitespace-pre-line text-light-txt-body dark:text-dark-txt-body">{{ $this->notificationDetails->notification->message ?? 'No Message Content available.' }}</x-text>
        </div>

        @if ($this->rentalLink)
            <div class="px-2 pb-2 pt-2">
                <flux:button :href="$this->rentalLink['url']" wire:navigate variant="primary" size="sm" class="font-secondary">
                    {{ $this->rentalLink['label'] }}
                </flux:button>
            </div>
        @endif
    </x-card>

    <flux:modal name="delete-notification" class="w-[calc(100%-2rem)] sm:min-w-[22rem] sm:max-w-none">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg" class="font-primary">Delete notification?</flux:heading>
                <flux:text class="mt-2 font-secondary text-light-txt-muted dark:text-dark-txt-muted">
                    You're about to delete this notification.<br>
                    This action cannot be reversed.
                </flux:text>
            </div>
            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost" class="font-secondary">Cancel</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" class="font-secondary" wire:click="destroyNotification({{ $this->user_notification->id }})">Delete notification</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
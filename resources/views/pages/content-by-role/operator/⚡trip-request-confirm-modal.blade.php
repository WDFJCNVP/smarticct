<?php

use Livewire\Component;
use Illuminate\Support\Facades\DB;


use App\Models\TripRequest;
use App\Models\RentTransaction;
use App\Models\Notification;
use App\Models\UserNotification;
use App\Events\NotificationEvent;

new class extends Component
{
    public TripRequest $tripRequest;
    public string $modalType;

    protected function isAuthorized(): bool
    {
        return $this->tripRequest->user_id === auth()->id()
            || optional($this->tripRequest->post)->user_id === auth()->id();
    }

    protected function notifyCounterparty(TripRequest $tripRequest, string $title, string $message): void
    {
        $postOwnerId = optional($tripRequest->post)->user_id;
        $requesterId = $tripRequest->user_id;

        $recipientId = auth()->id() === $postOwnerId ? $requesterId : $postOwnerId;

        if (!$recipientId) {
            return;
        }

        $notification = Notification::create([
            'type'    => 'Trip Request',
            'title'   => $title,
            'message' => $message,
        ]);

        UserNotification::create([
            'notification_id' => $notification->id,
            'user_id'         => $recipientId,
        ]);

        broadcast(new NotificationEvent());
    }

    public function cancelTrip() {

        if($this->tripRequest && $this->isAuthorized()) {

            $tripRequest = $this->tripRequest;
            
            DB::transaction(function () use($tripRequest) {
                $tripRequest->update(['status' => 'cancel']);

                RentTransaction::create([
                    'post_owner_id' => $tripRequest->post->user->id,
                    'interested_user_id' => $tripRequest->user_id,
                    'trip_request_id' =>$tripRequest->id,
                    'status'          => 'cancelled',
                ]);

            });

            $this->notifyCounterparty(
                $tripRequest,
                'Trip cancelled',
                'A trip you were part of has been cancelled.',
            );

            Flux::toast(
                duration: 4000,
                variant: 'success',
                heading: 'Trip cancelled',
                text: 'This trip has been cancelled.',
            );

        }

    }

    public function completeTrip() {

        if($this->tripRequest && $this->isAuthorized()) {

            $tripRequest = $this->tripRequest;
            
            DB::transaction(function () use($tripRequest) {
                $tripRequest->update(['status' => 'completed']);

                RentTransaction::create([
                    'post_owner_id' => $tripRequest->post->user->id,
                    'interested_user_id' => $tripRequest->user_id,
                    'trip_request_id' =>$tripRequest->id,
                    'status'          => 'completed',
                ]);

            });

            $this->notifyCounterparty(
                $tripRequest,
                'Trip completed',
                'A trip you were part of has been marked as completed.',
            );

            Flux::toast(
                duration: 0,
                variant: 'success',
                heading: 'Trip completed',
                text: 'This trip has been marked as completed.',
            );

        }

    }
};
?>

<div>
    @if ($modalType === 'complete-trip')
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Complete trip?</flux:heading>
                <flux:text class="mt-2">
                    You're about to complete this trip.<br>
                    This action cannot be reversed.
                </flux:text>
            </div>
            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">Cancel</flux:button>
                </flux:modal.close>
                <flux:button wire:click="completeTrip" variant="primary" color="green">Complete trip</flux:button>
            </div>
        </div>
    
    @elseif($modalType === 'cancel-trip')

        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Cancel trip?</flux:heading>
                <flux:text class="mt-2">
                    You're about to Cancel this trip.<br>
                    This action cannot be reversed.
                </flux:text>
            </div>
            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">Cancel</flux:button>
                </flux:modal.close>
                <flux:button wire:click="cancelTrip" variant="danger">Cancel trip</flux:button>
            </div>
        </div>

    @endif

</div>
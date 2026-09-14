<?php

use Livewire\Component;
use Illuminate\Support\Facades\DB;

use App\Models\RentalOffer;
use App\Models\RentTransaction;
use App\Models\Notification;
use App\Models\UserNotification;
use App\Events\NotificationEvent;

new class extends Component
{

    public RentalOffer $rentalOffer;
    public string $modalType;

    protected function isAuthorized(): bool
    {
        return $this->rentalOffer->user_id === auth()->id()
            || optional($this->rentalOffer->post)->user_id === auth()->id();
    }

    protected function notifyCounterparty(RentalOffer $rentalOffer, string $title, string $message): void
    {
        $postOwnerId = optional($rentalOffer->post)->user_id;
        $offeringUserId = $rentalOffer->user_id;

        $recipientId = auth()->id() === $postOwnerId ? $offeringUserId : $postOwnerId;

        if (!$recipientId) {
            return;
        }

        $notification = Notification::create([
            'type'    => 'Rental',
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

        if($this->rentalOffer && $this->isAuthorized()) {

            $rentalOffer = $this->rentalOffer;
            
            DB::transaction(function () use($rentalOffer) {
                $rentalOffer->update(['status' => 'cancel']);

                RentTransaction::create([
                    'post_owner_id' => $rentalOffer->post->user->id,
                    'interested_user_id' => $rentalOffer->user_id,
                    'rental_offer_id' =>$rentalOffer->id,
                    'status'          => 'cancelled',
                ]);

            });

            $this->notifyCounterparty(
                $rentalOffer,
                'Rental cancelled',
                'A rental you were part of has been cancelled.',
            );

            Flux::toast(
                duration: 0,
                variant: 'success',
                heading: 'Rental cancelled',
                text: 'This rental has been cancelled.',
            );

        }

    }

    public function completeTrip() {

        if($this->rentalOffer && $this->isAuthorized()) {

            $rentalOffer = $this->rentalOffer;
            
            DB::transaction(function () use($rentalOffer) {
                $rentalOffer->update(['status' => 'completed']);

                RentTransaction::create([
                    'post_owner_id' => $rentalOffer->post->user->id,
                    'interested_user_id' => $rentalOffer->user_id,
                    'rental_offer_id' =>$rentalOffer->id,
                    'status'          => 'completed',
                ]);

            });

            $this->notifyCounterparty(
                $rentalOffer,
                'Rental completed',
                'A rental you were part of has been marked as completed.',
            );

            Flux::toast(
                duration: 4000,
                variant: 'success',
                heading: 'Rental completed',
                text: 'This rental has been marked as completed.',
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
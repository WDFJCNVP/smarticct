<?php

use Livewire\Component;
use Illuminate\Support\Facades\DB;

use App\Models\RentalOffer;
use App\Models\RentTransaction;

new class extends Component
{

    public RentalOffer $rentalOffer;
    public string $modalType;

    public function cancelTrip() {

        if($this->rentalOffer) {

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

            Flux::toast(
                duration: 0,
                variant: 'success',
                heading: 'Rental cancelled',
                text: 'This rental has been cancelled.',
            );

        }

    }

    public function completeTrip() {

        if($this->rentalOffer) {

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

            Flux::toast(
                duration: 0,
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
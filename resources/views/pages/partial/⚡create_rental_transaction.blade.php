<?php

use Livewire\Component;

use App\Models\RentTransaction;
use App\Models\TripRequest;
use App\Models\RentalOffer;

use Illuminate\Database\Eloquent\Model;

use App\Services\PostService;
use App\Events\LiveActionEvent;

new class extends Component
{   
    public ?Model $interested_user = null;

    public function createRentalTransaction() {

        if(auth()->user()->role === 'operator') {
            $rental_transaction = app(PostService::class)->createRentalTransaction([
                'post_owner_id'       => $this->interested_user->post->user->id,
                'interested_user_id'  => $this->interested_user->user_id,
                'trip_request_id'  => $this->interested_user->id,
                'status'              => 'ongoing'
                
            ], $this->interested_user);


            if($rental_transaction) {
                $this->dispatch('transaction-updated');
                
                Flux::toast(
                    duration: 0,
                    variant: 'success',
                    heading: 'Request Accepted',
                    text: 'You have been successfully accepted the request.',
                );
            }

        } elseif(auth()->user()->role === 'commuter') {
            $rental_transaction = app(PostService::class)->createRentalTransaction([
                'post_owner_id'       => $this->interested_user->post->user->id,
                'interested_user_id'  => $this->interested_user->user_id,
                'rental_offer_id'     => $this->interested_user->id,
                'status'              => 'ongoing'
                
            ], $this->interested_user);

            if($rental_transaction) {
                $this->dispatch('transaction-updated');
                
                Flux::toast(
                    duration: 0,
                    variant: 'success',
                    heading: 'Request Accepted',
                    text: 'You have been successfully accepted the request.',
                );
            }
        }

    }

    public function cancelAction() {
        $this->interested_user = null;
    }

    // public function mount() {
    //             dd($this->interested_user);
    // }
};
?>

<div>
    <div class="space-y-6">
        <div>
            <flux:heading size="lg">Accept this interested user?</flux:heading>
            <flux:text class="mt-2">
                You're about to accept this interested user.<br>
                By accepting interested user you cannot be able to transact <br> 
                with another interested user for the mean while.
            </flux:text>
        </div>
        <div class="flex gap-2">
            <flux:spacer />
            <flux:modal.close>
                <flux:button wire:click="cancelAction" variant="ghost">Cancel</flux:button>
            </flux:modal.close>
            <flux:button wire:click="createRentalTransaction" type="button" color="green" variant="primary">Accept</flux:button>
        </div>
    </div>
</div>
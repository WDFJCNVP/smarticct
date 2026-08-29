<?php

use Livewire\Component;
use Livewire\Attributes\On;

use App\Models\RentTransaction;
use App\Models\TripRequest;
use App\Models\RentalOffer;

use Illuminate\Database\Eloquent\Model;

use App\Services\PostService;
use App\Events\LiveActionEvent;

new class extends Component
{
    public ?Model $interested_user = null;
    public bool $is_show_confirm_modal = false;

    #[On('open-confirm-modal')]
    public function openConfirmModal($id) {
        // The type of record being accepted mirrors the acting user's role:
        // an operator accepts a commuter's TripRequest; a commuter accepts
        // an operator's RentalOffer. Same pairing used in createRentalTransaction() below.
        $this->interested_user = auth()->user()->role === 'operator'
            ? TripRequest::find($id)
            : RentalOffer::find($id);

        $this->is_show_confirm_modal = true;
    }

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

                $this->is_show_confirm_modal = false;
                $this->interested_user = null;

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

                $this->is_show_confirm_modal = false;
                $this->interested_user = null;

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
        $this->is_show_confirm_modal = false;
    }
};
?>

<div>
    <flux:modal
        wire:model.live="is_show_confirm_modal"
        :closable="false"
        name="confirm"
        class="w-[calc(100%-2rem)] sm:max-w-lg md:max-w-2xl lg:max-w-3xl mx-auto max-h-[80vh] sm:max-h-[90vh] overflow-hidden rounded-xl"
    >
        <div class="flex flex-col max-h-[calc(80vh-2rem)] sm:max-h-[calc(90vh-2rem)] overflow-y-auto overscroll-contain p-4 sm:p-6 !pr-4 sm:!pr-6 space-y-5">
            <!-- Header -->
            <div class="flex items-start justify-between">
                <div>
                    <flux:heading size="xl" class="!font-primary !font-bold text-light-txt-primary dark:text-dark-txt-primary">
                        Accept this request?
                    </flux:heading>
                    <flux:text class="mt-1 font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">
                        You won't be able to accept another interested user until this transaction ends.
                    </flux:text>
                </div>
                <flux:modal.close>
                    <button type="button" wire:click="cancelAction" class="p-1 rounded-full hover:bg-light-subtle dark:hover:bg-dark-subtle text-light-txt-muted dark:text-dark-txt-muted -mt-1">
                        <flux:icon name="x-mark" class="w-5 h-5" />
                    </button>
                </flux:modal.close>
            </div>

            <!-- Footer -->
            <div class="flex flex-col-reverse sm:flex-row justify-end items-stretch sm:items-center gap-2 pt-2 border-t border-light-bd-default dark:border-dark-bd-default">
                <flux:modal.close>
                    <flux:button wire:click="cancelAction" variant="ghost" class="w-full sm:w-auto justify-center !font-secondary">
                        Cancel
                    </flux:button>
                </flux:modal.close>
                <flux:button wire:click="createRentalTransaction" type="button" color="green" variant="primary" class="w-full sm:w-auto justify-center !font-secondary">
                    Accept
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
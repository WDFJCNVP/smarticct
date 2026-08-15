<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;

use App\Events\PostActionEvent;

use App\Models\RentTransaction;
use App\Models\RentalOffer;
use App\Models\Vehicle;

new class extends Component
{
    public $post;

    public $isMarkAsCompleteModalOpen = false;
    public $isMarkAsCancelModalOpen = false;

    public function markAsCancelModal() {
        $this->isMarkAsCancelModalOpen = true;
    }

    public function markAsCompleteModal() {
        $this->isMarkAsCompleteModalOpen = true;
    }

    #[Computed]
    public function getVehicle() {
        $operatorVehicle =  $this->getActiveTransactionRecord;
        $vehicle_id = $operatorVehicle->rentalOffer->vehicle_id;
        $vehicle = Vehicle::where('user_id', $operatorVehicle->interested_user_id)->where('id', $vehicle_id)->first();

        return [
            'vehicle_type' => $vehicle->vehicle_type,
            'total_seats' => $vehicle->total_seats,
            'plate_number' => $vehicle->plate_number,
        ];
    }

    public function markAsCancel() {

        $getActiveTransactionRecord = $this->getActiveTransactionRecord;
        $post = $this->post;

        $tripRequest = DB::transaction(function () use ($getActiveTransactionRecord, $post){
            $getActiveTransactionRecord->update(['status' => 'cancelled']);
            $tripRequest = RentalOffer::where('id', $getActiveTransactionRecord->rental_offer_id)->update(['status' => 'cancel']);
            $post->update(['status' => 'published']);

            return $tripRequest;
        });
        
        unset($getActiveTransactionRecord);
        unset($this->interestedUser);

        $this->dispatch('transaction-updated');
        
        if ($tripRequest) {
                  
            Flux::toast(
                duration: 0,
                variant: 'success',
                heading: 'Marked as cancelled',
                text: 'Transaction has been marked as cancelled.',
            );

        }
    }

    public function markAsComplete() {
        $this->getActiveTransactionRecord->update(['status' => 'completed']);
        $tripRequest = RentalOffer::where('id', $this->getActiveTransactionRecord->rental_offer_id)->update(['status' => 'completed']);
        
        unset($this->getActiveTransactionRecord);
        $this->dispatch('transaction-updated'); 
        
        if ($tripRequest) {

            Flux::toast(
                duration: 0,
                variant: 'success',
                heading: 'Marked as complete',
                text: 'Transaction has been marked as complete.',
            );

        }
    }  

    #[Computed]
    public function getActiveTransactionRecord() {
        return RentTransaction::with('rentalOffer', 'post')->where('post_owner_id', $this->post->user_id)->where('status', 'ongoing')->first();
    }

    #[On('transaction-updated')]
    public function refreshActiveTransaction() {
        unset($this->getActiveTransactionRecord);
    }

    // public function mount() {
    //     dd($this->getActiveTransactionRecord);
    // }
};
?>

<div>
    @if ($this->getActiveTransactionRecord)
        <x-card>
            <div class="flex items-center">
                <div class="flex-1">
                    <x-text size="sm">Operator's name</x-text>
                    <x-text variant="strong" size="xl">{{ $this->getActiveTransactionRecord->rentalOffer->user->name }}</x-text>
                    <x-text variant="strong" size="lg" color="blue">{{ $this->getActiveTransactionRecord->rentalOffer->user->phone_number }}</x-text>
                </div>
                <div>
                    <x-badge color="orange">{{ ucfirst($this->getActiveTransactionRecord->status) }}</x-badge>
                </div>
            </div>
            <div class="mt-6 grid grid-cols-2 gap-4">
                <div>
                    <div>
                        <x-text size="sm">Available date:</x-text>
                        <x-text size="lg" variant="strong">
                            {{ $this->getActiveTransactionRecord->rentalOffer->available_from->format('D, M j Y') }}
                            -
                            {{ $this->getActiveTransactionRecord->rentalOffer->available_until->format('D, M j Y') }}
                        </x-text>
                    </div>

                    <div class="mt-2">
                        <x-text size="sm">Vehicle Type:</x-text>
                        <x-text size="lg" variant="strong">{{ $this->getVehicle['vehicle_type'] }}</x-text>
                    </div>
                    <div class="mt-2">
                        <x-text size="sm">Vehicle Model:</x-text>
                        <x-text size="lg" variant="strong">{{ $this->getActiveTransactionRecord->rentalOffer->metadata['vehicle_name'] }}</x-text>
                    </div>
                </div>
                <div>
                    <div class="mt-2">
                        <x-text size="sm">Plate Number:</x-text>
                        <x-text size="lg" variant="strong">{{ $this->getVehicle['plate_number'] }}</x-text>
                    </div>
                    <div class="mt-2">
                        <x-text size="sm">Seat Capacity:</x-text>
                        <x-text size="lg" variant="strong">{{ $this->getVehicle['total_seats'] }}</x-text>
                    </div>
                    <div class="mt-2">
                        <x-text size="sm">Destination:</x-text>
                        <x-text size="lg" variant="strong">{{ $this->getActiveTransactionRecord->rentalOffer->destination_coverage }}</x-text>
                    </div>
                </div>
            </div>

            <x-separator />

            <div class="flex items-center gap-4">
                <x-button wire:click="markAsCompleteModal" variant="primary" color="green">Mark as Completed</x-button>
                <x-button wire:click="markAsCancelModal" variant="primary" color="red">Cancel transaction</x-button>
            </div>

        </x-card>
    @else
        <x-text>No record found</x-text>
    @endif
    <flux:modal wire:model="isMarkAsCompleteModalOpen" class="min-w-96">
        @if ($this->isMarkAsCompleteModalOpen)
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">Complete this transaction?</flux:heading>
                    <flux:text class="mt-2">
                        You're about to mark this transaction as complete.<br>
                        This action cannot be undone.
                    </flux:text>
                </div>
                <div class="flex gap-2">
                    <flux:spacer />
                    <flux:modal.close>
                        <flux:button variant="ghost">Cancel</flux:button>
                    </flux:modal.close>
                    <flux:button wire:click="markAsComplete" type="button" color="green" variant="primary">Complete</flux:button>
                </div>
            </div>
        @endif
    </flux:modal>

    <flux:modal wire:model="isMarkAsCancelModalOpen" class="min-w-96">
        @if ($this->isMarkAsCancelModalOpen)
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">Cancel this transaction?</flux:heading>
                    <flux:text class="mt-2">
                        You're about to mark this transaction as cancelled.<br>
                        This action cannot be undone.
                    </flux:text>
                </div>
                <div class="flex gap-2">
                    <flux:spacer />
                    <flux:modal.close>
                        <flux:button variant="ghost">Cancel</flux:button>
                    </flux:modal.close>
                    <flux:button wire:click="markAsCancel" type="button" color="red" variant="primary">Cancel</flux:button>
                </div>
            </div>
        @endif
    </flux:modal>
</div>
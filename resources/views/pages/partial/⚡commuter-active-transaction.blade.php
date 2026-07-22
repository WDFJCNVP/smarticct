<?php

use Livewire\Component;
use Livewire\Attributes\Computed;

use App\Models\RentTransaction;
use App\Models\RentalOffer;
use App\Models\Vehicle;

new class extends Component
{
    public $post;

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
        $this->getActiveTransactionRecord->update(['status' => 'cancelled']);
        RentalOffer::where('id', $this->getActiveTransactionRecord->rental_offer_id)->update(['status' => 'cancel']);
    }

    public function markAsComplete() {
        
        $this->getActiveTransactionRecord->update(['status' => 'completed']);
        rentalOffer::where('id', $this->getActiveTransactionRecord->rental_offer_id)->update(['status' => 'completed']);
    }   

    #[Computed]
    public function getActiveTransactionRecord() {
        return RentTransaction::with('rentalOffer')->where('post_owner_id', $this->post->user_id)->where('status', 'ongoing')->first();
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
                <x-button wire:click="markAsComplete" variant="primary" color="green">Mark as Completed</x-button>
                <x-button wire:click="markAsCancel" variant="primary" color="red">Cancel transaction</x-button>
            </div>

        </x-card>
    @else
        <x-text>No record found</x-text>
    @endif

</div>
<?php

use Livewire\Component;
use Livewire\Attributes\Computed;

use App\Models\RentTransaction;
use App\Models\PostInterest;
use App\Models\Vehicle;

new class extends Component
{
    public $post;

    #[Computed]
    public function getVehicle() {
        $operatorVehicle =  $this->getActiveTransactionRecord;
        $vehicle_id = $operatorVehicle->postInterest->metadata['vehicle_id'];
        $vehicle = Vehicle::where('user_id', $operatorVehicle->client_id)->where('id', $vehicle_id)->get(['vehicle_type', 'total_seats', 'plate_number']);
        return [
            'vehicle_type' => $vehicle[0]['vehicle_type'],
            'total_seats' => $vehicle[0]['total_seats'],
            'plate_number' => $vehicle[0]['plate_number'],
        ];
    }

    public function markAsCancel() {
        $this->getActiveTransactionRecord->update(['status' => 'cancelled']);
        PostInterest::where('id', $this->getActiveTransactionRecord->post_interest_id)->update(['status' => 'cancel']);
    }

    public function markAsComplete() {
        
        $this->getActiveTransactionRecord->update(['status' => 'completed']);
        PostInterest::where('id', $this->getActiveTransactionRecord->post_interest_id)->update(['status' => 'completed']);
    }   

    #[Computed]
    public function getActiveTransactionRecord() {
        return RentTransaction::with('post.user', 'postInterest.user')->where('operator_id', $this->post->user_id)->where('status', 'ongoing')->first();
    }
};
?>

<div>
    @if ($this->getActiveTransactionRecord)
        <x-card>
            <div class="flex items-center">
                <div class="flex-1">
                    <x-text size="sm">Operator's name</x-text>
                    <x-text variant="strong" size="xl">{{ $this->getActiveTransactionRecord->postInterest->user->name }}</x-text>
                    <x-text variant="strong" size="lg" color="blue">{{ $this->getActiveTransactionRecord->postInterest->user->phone_number }}</x-text>
                </div>
                <div>
                    <x-badge color="orange">{{ $this->getActiveTransactionRecord->status }}</x-badge>
                </div>
            </div>
            <div class="mt-6 grid grid-cols-2 gap-4">
                <div>
                    <div>
                        <x-text size="sm">Available date:</x-text>
                        <x-text size="lg" variant="strong">
                            {{ \Carbon\Carbon::parse($this->getActiveTransactionRecord->postInterest->metadata['available_from'])->format('D, M j Y') }}
                            -
                            {{ \Carbon\Carbon::parse($this->getActiveTransactionRecord->postInterest->metadata['available_until'])->format('D, M j Y') }}
                        </x-text>
                    </div>
                    {{-- <div class="my-4">
                        <x-badge size="sm" color="emerald" icon="arrows-right-left">{{ $this->getActiveTransactionRecord->postInterest->trip_type }}</x-badge>
                    </div> --}}
                    <div class="mt-2">
                        <x-text size="sm">Vehicle Type:</x-text>
                        <x-text size="lg" variant="strong">{{ $this->getVehicle['vehicle_type'] }}</x-text>
                    </div>
                    <div class="mt-2">
                        <x-text size="sm">Vehicle Model:</x-text>
                        <x-text size="lg" variant="strong">{{ $this->getActiveTransactionRecord->postInterest->metadata['vehicle_name'] }}</x-text>
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
                        <x-text size="lg" variant="strong">{{ $this->getActiveTransactionRecord->postInterest->metadata['destination_coverage'] }}</x-text>
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
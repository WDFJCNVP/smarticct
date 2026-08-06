<?php

use Livewire\Component;
use Illuminate\Support\Facades\DB;


use App\Models\TripRequest;
use App\Models\RentTransaction;

new class extends Component
{
    public TripRequest $tripRequest;
    public string $modalType;
    public function cancelTrip() {

        if($this->tripRequest) {

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

        }

    }

    public function completeTrip() {

        if($this->tripRequest) {

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
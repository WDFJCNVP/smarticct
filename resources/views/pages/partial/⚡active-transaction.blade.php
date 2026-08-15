<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use App\Models\RentTransaction;
use App\Models\TripRequest;
use App\Models\User;

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

    public function markAsCancel() {

        $getActiveTransactionRecord = $this->getActiveTransactionRecord;
        $post = $this->post;

        $tripRequest = DB::transaction(function () use ($getActiveTransactionRecord, $post){

            $getActiveTransactionRecord->update(['status' => 'cancelled']);
            $tripRequest = TripRequest::where('id', $getActiveTransactionRecord->trip_request_id)->update(['status' => 'cancel']);
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

        $tripRequest = TripRequest::where('id', $this->getActiveTransactionRecord->trip_request_id)->update(['status' => 'completed']);

        unset($this->getActiveTransactionRecord);
        unset($this->interestedUser);

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
        return RentTransaction::with('tripRequest')->where('post_owner_id', $this->post->user_id)->where('status', 'ongoing')->first();
    }

    #[Computed]
    public function interestedUser() {
        if (!$this->getActiveTransactionRecord) return null;
        return User::find($this->getActiveTransactionRecord->interested_user_id);
    }

    #[On('transaction-updated')]
    public function refreshActiveTransaction() {
        unset($this->getActiveTransactionRecord);
        unset($this->interestedUser);
    }
};
?>

<div>
    @if ($this->getActiveTransactionRecord)
        <x-card>
            <div class="flex items-center">
                <div class="flex-1">
                    <x-text size="sm">Client's name</x-text>
                    <x-text variant="strong" size="xl">{{ $this->interestedUser->name }}</x-text>
                    <x-text variant="strong" size="lg" color="blue">{{ $this->interestedUser->phone_number }}</x-text>
                </div>
                <div>
                    <x-badge color="orange">{{ ucfirst($this->getActiveTransactionRecord->status) }}</x-badge>
                </div>
            </div>
            <div class="mt-6 grid grid-cols-2 gap-4">
                <div>
                    <div>
                        <x-text size="sm">Trip date:</x-text>
                        <x-text size="lg" variant="strong">{{ $this->getActiveTransactionRecord->tripRequest->trip_date->format('D, M j Y') }}</x-text>
                    </div>
                    <div class="my-4">
                        <x-badge size="sm" color="emerald" icon="arrows-right-left">{{ $this->getActiveTransactionRecord->tripRequest->trip_type }}</x-badge>
                    </div>
                    <div class="mt-2">
                        <x-text size="sm">Pick-up location:</x-text>
                        <x-text size="lg" variant="strong">{{ $this->getActiveTransactionRecord->tripRequest->pick_up_location }}</x-text>
                    </div>
                    <div class="mt-2">
                        <x-text size="sm">Return location:</x-text>
                        <x-text size="lg" variant="strong">{{ $this->getActiveTransactionRecord->tripRequest->pick_up_location }}</x-text>
                    </div>
                </div>
                <div>
                    <div class="mt-2">
                        <x-text size="sm">Total passenger/s:</x-text>
                        <x-text size="lg" variant="strong">{{ $this->getActiveTransactionRecord->tripRequest->body_count }}</x-text>
                    </div>
                    <div class="mt-2">
                        <x-text size="sm">Destination:</x-text>
                        <x-text size="lg" variant="strong">{{ $this->getActiveTransactionRecord->tripRequest->drop_off_location }}</x-text>
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
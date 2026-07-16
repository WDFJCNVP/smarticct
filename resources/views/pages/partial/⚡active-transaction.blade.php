<?php

use Livewire\Component;
use Livewire\Attributes\Computed;

use App\Models\RentTransaction;
use App\Models\PostInterest;

new class extends Component
{
    public $post;

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
                    <x-text size="sm">Client's name</x-text>
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
                        <x-text size="sm">Trip date:</x-text>
                        <x-text size="lg" variant="strong">{{ $this->getActiveTransactionRecord->postInterest->trip_date->format('D, M j Y') }}</x-text>
                    </div>
                    <div class="my-4">
                        <x-badge size="sm" color="emerald" icon="arrows-right-left">{{ $this->getActiveTransactionRecord->postInterest->trip_type }}</x-badge>
                    </div>
                    <div class="mt-2">
                        <x-text size="sm">Pick-up location:</x-text>
                        <x-text size="lg" variant="strong">{{ $this->getActiveTransactionRecord->postInterest->pick_up_location }}</x-text>
                    </div>
                    <div class="mt-2">
                        <x-text size="sm">Return location:</x-text>
                        <x-text size="lg" variant="strong">{{ $this->getActiveTransactionRecord->postInterest->pick_up_location }}</x-text>
                    </div>
                </div>
                <div>
                    <div class="mt-2">
                        <x-text size="sm">Total passenger/s:</x-text>
                        <x-text size="lg" variant="strong">{{ $this->getActiveTransactionRecord->postInterest->body_count }}</x-text>
                    </div>
                    <div class="mt-2">
                        <x-text size="sm">Destination:</x-text>
                        <x-text size="lg" variant="strong">{{ $this->getActiveTransactionRecord->postInterest->drop_off_location }}</x-text>
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
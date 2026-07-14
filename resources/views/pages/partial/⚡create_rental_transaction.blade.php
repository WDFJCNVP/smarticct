<?php

use Livewire\Component;

use App\Models\RentTransaction;

new class extends Component
{   
    public $client;

    public function createRentalTransaction() {
        RentTransaction::create([
            'operator_id'      => $this->client->post->user->id,
            'client_id'        => $this->client->user_id,
            'post_interest_id' => $this->client->id,
            'status'           => 'ongoing'
        ]);
    }

    public function cancelAction() {
        $this->client = null;
    }
};
?>

<div>
    <div class="space-y-6">
        <div>
            <flux:heading size="lg">Accept this client?</flux:heading>
            <flux:text class="mt-2">
                You're about to accept this client.<br>
                By accepting client you cannot be able to transact <br> 
                with another client for the mean while.
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
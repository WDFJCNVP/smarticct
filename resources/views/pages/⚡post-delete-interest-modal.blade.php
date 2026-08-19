<?php

use Livewire\Component;

use App\Models\TripRequest;
use App\Models\RentalOffer;

new class extends Component
{
    public $selected_post;

    public function destroy()
    {
        if(auth()->user()->role === 'operator') {
            $this->selected_post = RentalOffer::where('user_id', auth()->id())
                    ->where('post_id', $this->selected_post->post_id)
                    ->delete();

            $this->selected_post = null;
            $this->show_interested_modal = false;

            $this->dispatch('interest-deleted');
        } elseif (auth()->user()->role === 'commuter') {
            $this->selected_post = TripRequest::where('user_id', auth()->id())
                    ->where('post_id', $this->selected_post->post_id)
                    ->delete();

            $this->selected_post = null;
            $this->show_interested_modal = false;

            $this->dispatch('interest-deleted');
        }
    }

};
?>

<div>
    <div class="space-y-6">
        <div>
            <flux:heading size="lg">Are you sure?</flux:heading>
            <flux:text class="mt-2">
                You're about to remove this from your interest list. <br>
                This action cannot be reversed.
            </flux:text>
        </div>
        <div class="flex gap-2">
            <flux:spacer />
            <flux:modal.close>
                <flux:button variant="ghost">Cancel</flux:button>
            </flux:modal.close>
            <flux:button wire:click="destroy" variant="danger">Uninterest post</flux:button>
        </div>
    </div>
</div>
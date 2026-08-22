<?php

use Livewire\Component;

use App\Models\TripRequest;
use App\Models\RentalOffer;

new class extends Component
{
    public $selected_post;

    public function destroy()
    {
        // Guard against double-click: if this already ran once,
        // $selected_post is null, so just bail out instead of crashing.
        if (! $this->selected_post) {
            return;
        }

        if (auth()->user()->role === 'operator') {
            RentalOffer::where('user_id', auth()->id())
                ->where('post_id', $this->selected_post->post_id)
                ->delete();
        } elseif (auth()->user()->role === 'commuter') {
            TripRequest::where('user_id', auth()->id())
                ->where('post_id', $this->selected_post->post_id)
                ->delete();
        }

        $this->selected_post = null;
        $this->dispatch('interested-list-updated');
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
            <flux:button wire:click="destroy" wire:loading.attr="disabled" variant="danger">Uninterest post</flux:button>
        </div>
    </div>
</div>
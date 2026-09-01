<?php

use Livewire\Component;

use App\Models\TripRequest;
use App\Models\RentalOffer;
use Flux\Flux;

new class extends Component
{
    public $selected_post;

    public function destroy()
    {
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

        Flux::toast(
            duration: 4000,
            variant: 'success',
            heading: 'Removed',
            text: 'This post has been removed from your interest list.',
        );

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
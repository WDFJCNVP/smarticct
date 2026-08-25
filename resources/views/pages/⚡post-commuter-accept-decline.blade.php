<?php

use Livewire\Component;

new class extends Component
{
    public $this_operator;

    public bool $is_operator_accept = false;
    public bool $is_confirm_cancel = false;

    public function cancelThisTransaction() {
        $this->this_operator->update(['status' => 'cancel']);
        $this->this_operator->post->update(['status' => 'published']);

        Flux::toast(
            duration: 0,
            variant: 'success',
            heading: 'Transaction cancelled',
            text: 'The transaction has been cancelled and the post is published again.',
        );
    }

    public function confirmCancel() {
        $this->is_confirm_cancel = false;
        $this->is_confirm_cancel = true;
    }

    public function acceptThisOperator() {
        $this->this_operator->update(['status' => 'accept']);
        $this->this_operator->post->update(['status' => 'rented']);

        Flux::toast(
            duration: 0,
            variant: 'success',
            heading: 'Operator accepted',
            text: 'You have accepted this operator for the rental.',
        );
    }

    public function acceptOperatorRequest() {
        $this->is_operator_accept = false;
        $this->is_operator_accept = true;
    }

    // public function mount() {
    //     dd($this->this_operator);
    // }
};
?>

<div class="flex items-center mt-3 gap-2">
    <div>
        @if ($this->this_operator->status === 'accept')
            <x-button wire:click="confirmCancel" icon="x-mark" color="red" variant="primary">Cancel</x-button>
        @else
            <x-button wire:click="" icon="x-mark" color="red" variant="primary" />
            <x-button wire:click="acceptOperatorRequest" icon="check" color="green" variant="primary" />
        @endif
    </div>


    <flux:modal wire:model="is_confirm_cancel" class="min-w-[22rem]">
        @if ($this->is_confirm_cancel)
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">Cancel this transaction?</flux:heading>
                    <flux:text class="mt-2">
                        You're about to cancel this transaction.
                    </flux:text>
                </div>
                <div class="flex gap-2">
                    <flux:spacer />
                    <flux:modal.close>
                        <flux:button variant="ghost">Cancel</flux:button>
                    </flux:modal.close>
                    <flux:button wire:click="cancelThisTransaction" type="button" color="red" variant="primary">Cancel this transaction</flux:button>
                </div>
            </div>
        @endif
    </flux:modal>

    <flux:modal wire:model="is_operator_accept" class="min-w-[22rem]">
        @if ($this->is_operator_accept)
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">Accept this operator?</flux:heading>
                    <flux:text class="mt-2">
                        You're about to accept this operator.<br>
                        By accepting operator you cannot be able to transact <br> 
                        with another operator for the mean while.
                    </flux:text>
                </div>
                <div class="flex gap-2">
                    <flux:spacer />
                    <flux:modal.close>
                        <flux:button variant="ghost">Cancel</flux:button>
                    </flux:modal.close>
                    <flux:button wire:click="acceptThisOperator" type="button" color="green" variant="primary">Accept</flux:button>
                </div>
            </div>
        @endif
    </flux:modal>
</div>
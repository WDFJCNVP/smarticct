<?php

use Livewire\Component;

use App\Model\PostInterest;

new class extends Component
{
    public $replies;

    public bool $isAccept = false;
    public bool $isAccepted;
    public bool $cancelModal = false;
    public bool $declineModal = false;

    public function declineThisClient() {
        $this->replies->update(['status' => 'decline']);
        $this->declineModal = false;

        Flux::toast(
            duration: 0,
            variant: 'success',
            heading: 'Client declined',
            text: 'This client has been declined.',
        );
    }

    public function showDeclineModal() {
        $this->declineModal = false;
        $this->declineModal = true;
    }

    public function cancelThisClient() {
        $this->replies->update(['status' => 'cancel']);
        $this->cancelModal = false;

        Flux::toast(
            duration: 0,
            variant: 'success',
            heading: 'Client cancelled',
            text: 'This client has been cancelled.',
        );
    }

    public function showCancelModal() {
        $this->cancelModal = false;
        $this->cancelModal = true;
    }

    public function mount() {
        $this->isAccepted = $this->replies->where('status', 'accept')->exists();
    }

    public function acceptThisClient() {
        $this->replies->update(['status' => 'accept']);

        Flux::toast(
            duration: 0,
            variant: 'success',
            heading: 'Client accepted',
            text: 'This client has been accepted.',
        );
    }

    public function showAcceptModal() {
        $this->isAccept = false;
        $this->isAccept = true;
    }
};
?>

<div>
    <div class="space-y-6">
        <div>
            <flux:heading size="lg">Client details</flux:heading>
        </div>
        <div class="flex-1 flex items-center gap-2">
            <flux:avatar size="xs" name="{{ $this->replies->user->name }}" color="emerald"/>
            <div class="flex flex-col">
                <x-text class="text-sm font-medium">{{ $this->replies->user->name }}</x-text>
            </div>
        </div>

        <div class="mt-2 flex items-center">   
            <div class="flex-1 flex items-center gap-1.5 text-zinc-500 dark:text-zinc-400">
                <flux:icon.map-pin class="w-4 h-4" />
                <x-text class="text-inherit">Address</x-text>
            </div>
            <x-text variant="strong">{{ $this->replies->user->address }}</x-text>
        </div>

        <div class="mt-2 flex items-center">   
            <div class="flex-1 flex items-center gap-1.5 text-zinc-500 dark:text-zinc-400">
                <flux:icon.phone class="w-4 h-4" />
                <x-text class="text-inherit">Phone no.</x-text>
            </div>
            <x-text variant="strong">{{ $this->replies->user->phone_number }}</x-text>
        </div>

        <div>

            @php
                $url = Storage::url($this->replies->metadata['valid_ids']['user_valid_id']);
            @endphp

            <flux:label class="mb-4">Client's valid id</flux:label>
            <img src="{{ $url }}" alt="">
        </div>

        <div>
            @php
                $hasDriver = $this->replies->metadata['valid_ids']['driver_valid_id'] ?? null;
            @endphp

            @if ($hasDriver)

                <x-text variant="strong">Driver details</x-text>

                <div class="mt-4 flex items-center">   
                    <div class="flex-1 flex items-center gap-1.5 text-zinc-500 dark:text-zinc-400">
                        <flux:icon.user class="w-4 h-4" />
                        <x-text class="text-inherit">Name</x-text>
                    </div>
                    <x-text variant="strong">{{ $this->replies->metadata['driver_name'] }}</x-text>
                </div>

                <div class="mt-4 flex items-center">   
                    <div class="flex-1 flex items-center gap-1.5 text-zinc-500 dark:text-zinc-400">
                        <flux:icon.identification class="w-4 h-4" />
                        <x-text class="text-inherit">Age</x-text>
                    </div>
                    <x-text variant="strong">{{ $this->replies->metadata['driver_age'] }}</x-text>
                </div>

                <div class="mt-4 flex items-center">   
                    <div class="flex-1 flex items-center gap-1.5 text-zinc-500 dark:text-zinc-400">
                        <flux:icon.map-pin class="w-4 h-4" />
                        <x-text class="text-inherit">Home address</x-text>
                    </div>
                    <x-text variant="strong">{{ $this->replies->metadata['driver_home_address'] }}</x-text>
                </div>

                <div class="mt-4 flex items-center">   
                    <div class="flex-1 flex items-center gap-1.5 text-zinc-500 dark:text-zinc-400">
                        <flux:icon.phone class="w-4 h-4" />
                        <x-text class="text-inherit">Phone no.</x-text>
                    </div>
                    <x-text variant="strong">{{ $this->replies->metadata['driver_contact_number'] }}</x-text>
                </div>

                <div>

                    @php
                        $url = Storage::url($this->replies->metadata['valid_ids']['driver_valid_id']);
                    @endphp

                    <flux:label class="my-4">Driver's valid id</flux:label>
                    <img src="{{ $url }}" alt="">
                </div>

            @else

            <flux:badge color="orange" size="sm"> Client has no driver. </flux:badge>

            @endif
        </div>

        <div class="flex items-center w-full gap-4">
            @if ($this->isAccepted)
                <x-button wire:click="showCancelModal" class="w-full" icon="x-mark" variant="primary" color="red">Cancel</x-button>
            @else
                <x-button wire:click="showDeclineModal" class="w-full" icon="x-mark" variant="primary" color="red">Decline</x-button>
                <x-button wire:click="showAcceptModal" class="w-full" icon="check"  variant="primary" color="green">Accept</x-button>
            @endif
        </div>

    </div>

    <flux:modal wire:model="isAccept" class="w-[calc(100%-2rem)] sm:min-w-[22rem] sm:max-w-none">
        @if ($this->isAccept)
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
                        <flux:button variant="ghost">Cancel</flux:button>
                    </flux:modal.close>
                    <flux:button wire:click="acceptThisClient" type="button" color="green" variant="primary">Accept client</flux:button>
                </div>
            </div>
        @endif
    </flux:modal>

    <flux:modal wire:model="declineModal" class="w-[calc(100%-2rem)] sm:min-w-[22rem] sm:max-w-none">
        @if ($this->declineModal)
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">Decline this client?</flux:heading>
                    <flux:text class="mt-2">
                        You're about to decline this client.<br>
                        By declining this client this will be removed from the list.
                    </flux:text>
                </div>
                <div class="flex gap-2">
                    <flux:spacer />
                    <flux:modal.close>
                        <flux:button variant="ghost">Cancel</flux:button>
                    </flux:modal.close>
                    <flux:button wire:click="declineThisClient" type="button" variant="danger">Decline client</flux:button>
                </div>
            </div>
        @endif
    </flux:modal>

    <flux:modal wire:model="cancelModal" class="w-[calc(100%-2rem)] sm:min-w-[22rem] sm:max-w-none">
        @if ($this->cancelModal)
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">Cancel this client?</flux:heading>
                    <flux:text class="mt-2">
                        You're about to Cancel this client.
                    </flux:text>
                </div>
                <div class="flex gap-2">
                    <flux:spacer />
                    <flux:modal.close>
                        <flux:button variant="ghost">Cancel</flux:button>
                    </flux:modal.close>
                    <flux:button wire:click="cancelThisClient" type="button" variant="danger">Cancel client</flux:button>
                </div>
            </div>
        @endif
    </flux:modal>
</div>
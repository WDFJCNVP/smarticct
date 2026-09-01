<?php

use Livewire\Component;

use App\Models\RentalOffer;

new class extends Component
{
    public RentalOffer $rentalOffer;
};
?>

<div class="flex flex-col p-4 sm:p-6 !pr-4 sm:!pr-6 space-y-5 overflow-y-auto max-h-[70vh]">
    {{-- Header with custom close --}}
    <div class="flex items-start justify-between">
        <div>
            <flux:heading size="xl" class="!font-primary !font-bold text-light-txt-primary dark:text-dark-txt-primary">
                Client's Post
            </flux:heading>
            <flux:text class="mt-1 font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">
                Full details of the rental request and your offer.
            </flux:text>
        </div>
        <flux:modal.close>
            <button type="button" class="p-1 rounded-full hover:bg-light-subtle dark:hover:bg-dark-subtle text-light-txt-muted dark:text-dark-txt-muted -mt-1">
                <flux:icon name="x-mark" class="w-5 h-5" />
            </button>
        </flux:modal.close>
    </div>

    {{-- Client's Post --}}
    <flux:card class="!p-4 !rounded-xl !border !border-light-bd-default dark:!border-dark-bd-default !shadow-sm">
        <div class="flex items-center gap-3">
            <flux:avatar size="sm" name="{{ $this->rentalOffer->post->user->name }}" />
            <div>
                <x-text variant="strong" class="text-light-txt-primary dark:text-dark-txt-primary">
                    {{ $this->rentalOffer->post->user->name }}
                </x-text>
                <x-text size="sm" variant="subtle" class="block text-light-txt-muted dark:text-dark-txt-muted">
                    {{ $this->rentalOffer->post->created_at->diffForHumans(['short' => true]) }}
                </x-text>
            </div>
        </div>

        <div class="mt-3 flex flex-wrap gap-2">
            @if ($this->rentalOffer->post->status === 'rented')
                <flux:badge size="sm" color="green">Active</flux:badge>
            @endif
            <flux:badge size="sm" color="blue">{{ $this->rentalOffer->post->metadata['vehicle_type'] }}</flux:badge>
            <flux:badge size="sm" color="yellow" class="inline-flex items-center">
                {{ $this->rentalOffer->post->metadata['from'] }}
                <flux:icon.arrow-right class="size-3 mx-1" />
                {{ $this->rentalOffer->post->metadata['to'] }}
            </flux:badge>
        </div>

        <div class="mt-3">
            <x-text class="text-light-txt-body dark:text-dark-txt-body">
                {{ $this->rentalOffer->post->body }}
            </x-text>
        </div>
    </flux:card>

    {{-- Client's Personal Information --}}
    <div>
        <flux:heading size="md" class="!font-primary !font-semibold text-light-txt-primary dark:text-dark-txt-primary mb-3">
            Client's Personal Information
        </flux:heading>

        <flux:card class="!p-4 !rounded-xl !border !border-light-bd-default dark:!border-dark-bd-default !shadow-sm">
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <span class="font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">Full Name</span>
                    <span class="font-secondary text-sm font-medium text-light-txt-primary dark:text-dark-txt-primary">
                        {{ $this->rentalOffer->post->user->name ?? 'Unknown' }}
                    </span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">Home Address</span>
                    <span class="font-secondary text-sm font-medium text-light-txt-primary dark:text-dark-txt-primary text-right max-w-[200px]">
                        {{ $this->rentalOffer->post->user->address ?? 'Unknown' }}
                    </span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">Contact No.</span>
                    <span class="font-secondary text-sm font-medium text-light-txt-primary dark:text-dark-txt-primary">
                        {{ $this->rentalOffer->post->user->phone_number ?? 'Unknown' }}
                    </span>
                </div>
                @if (!empty($this->rentalOffer->post->user->email_address))
                    <div class="flex items-center justify-between">
                        <span class="font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">Email Address</span>
                        <span class="font-secondary text-sm font-medium text-light-txt-primary dark:text-dark-txt-primary">
                            {{ $this->rentalOffer->post->user->email_address }}
                        </span>
                    </div>
                @endif
            </div>
        </flux:card>
    </div>

    {{-- Your Offer --}}
    <div>
        <flux:heading size="md" class="!font-primary !font-semibold text-light-txt-primary dark:text-dark-txt-primary mb-3">
            Your Offer
        </flux:heading>

        <flux:card class="!p-4 !rounded-xl !border !border-light-bd-default dark:!border-dark-bd-default !shadow-sm">
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <span class="font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">Status</span>
                    <flux:badge color="green" size="sm">{{ ucfirst($this->rentalOffer->status) }}ed</flux:badge>
                </div>
                <div class="flex items-center justify-between">
                    <span class="font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">Vehicle Type</span>
                    <span class="font-secondary text-sm font-medium text-light-txt-primary dark:text-dark-txt-primary">
                        {{ $this->rentalOffer->vehicle->vehicle_type }}
                    </span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">Model</span>
                    <span class="font-secondary text-sm font-medium text-light-txt-primary dark:text-dark-txt-primary">
                        {{ $this->rentalOffer->metadata['vehicle_name'] }}
                    </span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">Destination Coverage</span>
                    <span class="font-secondary text-sm font-medium text-light-txt-primary dark:text-dark-txt-primary text-right max-w-[200px]">
                        {{ $this->rentalOffer->destination_coverage }}
                    </span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">Available From</span>
                    <span class="font-secondary text-sm font-medium text-light-txt-primary dark:text-dark-txt-primary">
                        {{ $this->rentalOffer->available_from->format('M d, Y') }}
                    </span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">Available Until</span>
                    <span class="font-secondary text-sm font-medium text-light-txt-primary dark:text-dark-txt-primary">
                        {{ $this->rentalOffer->available_until->format('M d, Y') }}
                    </span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">Accepted at</span>
                    <span class="font-secondary text-sm font-medium text-light-txt-primary dark:text-dark-txt-primary">
                        {{ $this->rentalOffer->updated_at->format('M d, Y') }}
                    </span>
                </div>
            </div>
        </flux:card>
    </div>
</div>
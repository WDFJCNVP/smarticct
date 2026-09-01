<?php

use Livewire\Component;

use App\Models\TripRequest;

new class extends Component
{
    public TripRequest $tripRequest;
};
?>

<div class="flex flex-col p-4 sm:p-6 !pr-4 sm:!pr-6 space-y-5 overflow-y-auto max-h-[70vh]">
    {{-- Header with custom close --}}
    <div class="flex items-start justify-between">
        <div>
            <flux:heading size="xl" class="!font-primary !font-bold text-light-txt-primary dark:text-dark-txt-primary">
                Trip Request Details
            </flux:heading>
            <flux:text class="mt-1 font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">
                Full details of the trip request and client's offer.
            </flux:text>
        </div>
        <flux:modal.close>
            <button type="button" class="p-1 rounded-full hover:bg-light-subtle dark:hover:bg-dark-subtle text-light-txt-muted dark:text-dark-txt-muted -mt-1">
                <flux:icon name="x-mark" class="w-5 h-5" />
            </button>
        </flux:modal.close>
    </div>

    {{-- Your Post --}}
    <flux:card class="!p-4 !rounded-xl !border !border-light-bd-default dark:!border-dark-bd-default !shadow-sm">
        <div class="flex items-center gap-3">
            <flux:avatar size="sm" name="{{ $tripRequest->post->user->name }}" />
            <div>
                <x-text variant="strong" class="text-light-txt-primary dark:text-dark-txt-primary">
                    {{ $tripRequest->post->user->name }}
                </x-text>
                <x-text size="sm" variant="subtle" class="block text-light-txt-muted dark:text-dark-txt-muted">
                    {{ $tripRequest->post->created_at->diffForHumans(['short' => true]) }}
                </x-text>
            </div>
        </div>

        <div class="mt-3 flex flex-wrap gap-2">
            @if ($tripRequest->post->status === 'rented')
                <flux:badge size="sm" color="green">Active</flux:badge>
            @endif
            <flux:badge size="sm" color="blue">{{ $tripRequest->post->metadata['vehicle_type'] }}</flux:badge>
            <flux:badge size="sm" color="yellow" class="inline-flex items-center">
                {{ $tripRequest->post->metadata['from'] }}
                <flux:icon.arrow-right class="size-3 mx-1" />
                {{ $tripRequest->post->metadata['to'] }}
            </flux:badge>
        </div>

        <div class="mt-3">
            <x-text class="text-light-txt-body dark:text-dark-txt-body">
                {{ $tripRequest->post->body }}
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
                        {{ $tripRequest->user->name ?? 'Unknown' }}
                    </span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">Home Address</span>
                    <span class="font-secondary text-sm font-medium text-light-txt-primary dark:text-dark-txt-primary text-right max-w-[200px]">
                        {{ $tripRequest->user->address ?? 'Unknown' }}
                    </span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">Contact No.</span>
                    <span class="font-secondary text-sm font-medium text-light-txt-primary dark:text-dark-txt-primary">
                        {{ $tripRequest->user->phone_number ?? 'Unknown' }}
                    </span>
                </div>
                @if (!empty($tripRequest->user->email_address))
                    <div class="flex items-center justify-between">
                        <span class="font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">Email Address</span>
                        <span class="font-secondary text-sm font-medium text-light-txt-primary dark:text-dark-txt-primary">
                            {{ $tripRequest->user->email_address }}
                        </span>
                    </div>
                @endif
            </div>
        </flux:card>
    </div>

    {{-- Client's Offer --}}
    <div>
        <flux:heading size="md" class="!font-primary !font-semibold text-light-txt-primary dark:text-dark-txt-primary mb-3">
            Client's Offer
        </flux:heading>

        <flux:card class="!p-4 !rounded-xl !border !border-light-bd-default dark:!border-dark-bd-default !shadow-sm">
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <span class="font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">Status</span>
                    <flux:badge color="green" size="sm">{{ ucfirst($tripRequest->status) }}ed</flux:badge>
                </div>
                <div class="flex items-center justify-between">
                    <span class="font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">Trip Type</span>
                    <span class="font-secondary text-sm font-medium text-light-txt-primary dark:text-dark-txt-primary">
                        {{ $tripRequest->trip_type }}
                    </span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">Body Count</span>
                    <span class="font-secondary text-sm font-medium text-light-txt-primary dark:text-dark-txt-primary">
                        {{ $tripRequest->body_count }}
                    </span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">Pick-up Location</span>
                    <span class="font-secondary text-sm font-medium text-light-txt-primary dark:text-dark-txt-primary text-right max-w-[200px]">
                        {{ $tripRequest->pick_up_location }}
                    </span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">Drop-off Location</span>
                    <span class="font-secondary text-sm font-medium text-light-txt-primary dark:text-dark-txt-primary text-right max-w-[200px]">
                        {{ $tripRequest->drop_off_location }}
                    </span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">Trip Date</span>
                    <span class="font-secondary text-sm font-medium text-light-txt-primary dark:text-dark-txt-primary">
                        {{ $tripRequest->trip_date->format('M d, Y') }}
                    </span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">Accepted at</span>
                    <span class="font-secondary text-sm font-medium text-light-txt-primary dark:text-dark-txt-primary">
                        {{ $tripRequest->updated_at->format('M d, Y') }}
                    </span>
                </div>
            </div>
        </flux:card>
    </div>
</div>
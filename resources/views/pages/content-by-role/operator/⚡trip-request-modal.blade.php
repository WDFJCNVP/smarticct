<?php

use Livewire\Component;

use App\Models\TripRequest;

new class extends Component
{
    public TripRequest $tripRequest;
};
?>

<div>
   <x-pages-heading>Your Post</x-pages-heading>

    <div>
        <x-card>
            <div class="flex items-center gap-2">
                <flux:avatar size="sm" name="{{ $tripRequest->post->user->name  }}" />
                <div>
                    <x-text variant="strong" >{{ $tripRequest->post->user->name }}</x-text>
                    <x-text size="sm" variant="subtle">{{ $tripRequest->post->created_at->diffForHumans(['short' => true]) }}</x-text>
                </div>
            </div>
            <div class="mt-2">
                @if ($tripRequest->post->status === 'rented')
                    <x-badge size="sm" color="green">Active</x-badge>
                @endif

                <x-badge size="sm" color="green">{{$tripRequest->post->metadata['vehicle_type']}}</x-badge>
                <x-badge size="sm" color="yellow">
                    {{$tripRequest->post->metadata['from']}}
                    <flux:icon.arrow-right class="size-3.5 mx-1" />
                    {{$tripRequest->post->metadata['to']}}
                </x-badge>
            </div>

            <div class="mt-2">
                <x-text>{{ $tripRequest->post->body }}</x-text>
            </div>
        </x-card>
    </div>

   <x-pages-heading>Client's Personal Information</x-pages-heading>

    <div class="mt-2">
        <div class="flex items-center gap-2 justify-between mt-2">
            <x-text variant="subtle">Full Name</x-text>
            <x-text variant="strong">{{ $tripRequest->user->name ?? 'Unknown' }}</x-text>
        </div>
        <div class="flex items-center gap-2 justify-between mt-2">
            <x-text variant="subtle">Home Address</x-text>
            
            <x-text variant="strong">{{ $tripRequest->user->address ?? 'Unknown' }}</x-text>
        </div>
        <div class="flex items-center gap-2 justify-between mt-2">
            <x-text variant="subtle">Contact No.</x-text>
            
            <x-text variant="strong" class="truncate max-w-[150px]">{{ $tripRequest->user->phone_number ?? 'Unknown' }}</x-text>
        </div>

        @if (!empty($tripRequest->user->email_address))
            <div class="flex items-center gap-2 justify-between mt-2">
                <x-text variant="subtle">Email Address</x-text>
                
                <x-text variant="strong" class="truncate max-w-[150px]">{{ $tripRequest->user->email_address ?? 'Unknown' }}</x-text>
            </div>
        @endif
    </div>

    <x-pages-heading>Client's Offer</x-pages-heading>

    <div class="mt-2">

        <div class="flex items-center gap-2 justify-between mt-2">
            <x-text variant="subtle">Status</x-text>
            <x-badge color="green" size="sm">{{ ucfirst($tripRequest->status) }}ed</x-badge>
        </div>

        <div class="flex items-center gap-2 justify-between mt-2">
            <x-text variant="subtle">Trip Type</x-text>
            <x-text variant="strong">{{ $tripRequest->trip_type }}</x-text>
        </div>
        <div class="flex items-center gap-2 justify-between mt-2">
            <x-text variant="subtle">Body count</x-text>
            
            <x-text variant="strong">{{ $tripRequest->body_count }}</x-text>
        </div>
        <div class="flex items-center gap-2 justify-between mt-2">
            <x-text variant="subtle">Pick-up location</x-text>
            
            <x-text variant="strong" class="truncate max-w-[150px]">{{ $tripRequest->pick_up_location }}</x-text>
        </div>

        <div class="flex items-center gap-2 justify-between mt-2">
            <x-text variant="subtle">Drop off location</x-text>
            
            <x-text variant="strong" class="truncate max-w-[150px]">{{ $tripRequest->drop_off_location }}</x-text>
        </div>

        <div class="flex items-center gap-2 justify-between mt-2">
            <x-text variant="subtle">Trip date</x-text>
            
            <x-text variant="strong" class="truncate max-w-[150px]">{{ $tripRequest->trip_date->format('M d, Y') }}</x-text>
        </div>

        <div class="flex items-center gap-2 justify-between mt-2">
            <x-text variant="subtle">Accepted at</x-text>
            
            <x-text variant="strong" class="truncate max-w-[150px]">{{ $tripRequest->updated_at->format('M d, Y') }}</x-text>
        </div>

    </div>
</div>
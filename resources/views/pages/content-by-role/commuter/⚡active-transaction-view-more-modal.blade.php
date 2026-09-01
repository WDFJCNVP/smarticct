<?php

use Livewire\Component;

use App\Models\RentalOffer;

new class extends Component
{
    public RentalOffer $rentalOffer;
};
?>

<div>
   <x-pages-heading>Your Post</x-pages-heading>

    <div>
        <x-card>
            <div class="flex items-center gap-2">
                <flux:avatar size="sm" name="{{ $this->rentalOffer->post->user->name  }}" />
                <div>
                    <x-text variant="strong" >{{ $this->rentalOffer->post->user->name }}</x-text>
                    <x-text size="sm" variant="subtle">{{ $this->rentalOffer->post->created_at->diffForHumans(['short' => true]) }}</x-text>
                </div>
            </div>
            <div class="mt-2">
                @if ($this->rentalOffer->post->status === 'rented')
                    <x-badge size="sm" color="green">Active</x-badge>
                @endif

                <x-badge size="sm" color="green">{{$this->rentalOffer->post->metadata['vehicle_type']}}</x-badge>
                <x-badge size="sm" color="yellow">
                    {{$this->rentalOffer->post->metadata['from']}}
                    <flux:icon.arrow-right class="size-3.5 mx-1" />
                    {{$this->rentalOffer->post->metadata['to']}}
                </x-badge>
            </div>

            <div class="mt-2">
                <x-text>{{ $this->rentalOffer->post->body }}</x-text>
            </div>
        </x-card>
    </div>

   <x-pages-heading>Operator's Personal Information</x-pages-heading>
    <div class="mt-2">
        <div class="flex items-center gap-2 justify-between mt-2">
            <x-text variant="subtle">Full Name</x-text>
            <x-text variant="strong">{{ $this->rentalOffer->user->name ?? 'Unknown' }}</x-text>
        </div>
        <div class="flex items-center gap-2 justify-between mt-2">
            <x-text variant="subtle">Home Address</x-text>
            
            <x-text variant="strong">{{ $this->rentalOffer->user->address ?? 'Unknown' }}</x-text>
        </div>
        <div class="flex items-center gap-2 justify-between mt-2">
            <x-text variant="subtle">Contact No.</x-text>
            
            <x-text variant="strong" class="truncate max-w-[150px]">{{ $this->rentalOffer->user->phone_number ?? 'Unknown' }}</x-text>
        </div>

        @if (!empty($this->rentalOffer->post->user->email_address))
            <div class="flex items-center gap-2 justify-between mt-2">
                <x-text variant="subtle">Email Address</x-text>
                
                <x-text variant="strong" class="truncate max-w-[150px]">{{ $this->rentalOffer->user->email_address ?? 'Unknown' }}</x-text>
            </div>
        @endif
    </div>

    <x-pages-heading>Operator's Offer</x-pages-heading>
    <div class="mt-2">

        <div class="flex items-center gap-2 justify-between mt-2">
            <x-text variant="subtle">Status</x-text>
            <x-badge color="green" size="sm">{{ match($this->rentalOffer->status) {
                'accept' => 'Accepted',
                'pending' => 'Pending',
                'cancel' => 'Cancelled',
                'decline' => 'Declined',
                'ongoing' => 'Ongoing',
                default => ucfirst($this->rentalOffer->status),
            } }}</x-badge>
        </div>

        <div class="flex items-center gap-2 justify-between mt-2">
            <x-text variant="subtle">Vehicle Type</x-text>
            <x-text variant="strong">{{ $this->rentalOffer->vehicle->vehicle_type }}</x-text>
        </div>
        <div class="flex items-center gap-2 justify-between mt-2">
            <x-text variant="subtle">Model</x-text>
            
            <x-text variant="strong">{{ $this->rentalOffer->metadata['vehicle_name'] }}</x-text>
        </div>
        <div class="flex items-center gap-2 justify-between mt-2">
            <x-text variant="subtle">Destination Coverage</x-text>
            
            <x-text variant="strong" class="truncate max-w-[150px]">{{ $this->rentalOffer->destination_coverage }}</x-text>
        </div>

        <div class="flex items-center gap-2 justify-between mt-2">
            <x-text variant="subtle">Available From</x-text>
            
            <x-text variant="strong" class="truncate max-w-[150px]">{{ $this->rentalOffer->available_from->format('M d, Y') }}</x-text>
        </div>

        <div class="flex items-center gap-2 justify-between mt-2">
            <x-text variant="subtle">Available Until</x-text>
            
            <x-text variant="strong" class="truncate max-w-[150px]">{{ $this->rentalOffer->available_until->format('M d, Y') }}</x-text>
        </div>

        <div class="flex items-center gap-2 justify-between mt-2">
            <x-text variant="subtle">Accepted at</x-text>
            
            <x-text variant="strong" class="truncate max-w-[150px]">{{ $this->rentalOffer->updated_at->format('M d, Y') }}</x-text>
        </div>

    </div>
</div>
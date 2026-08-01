<?php

use Livewire\Component;
use Livewire\Attributes\Computed;

use App\Models\RentalOffer;
use App\Models\TripRequest;

new class extends Component
{
    #[Computed]
    public function rentalOffers() {
        return RentalOffer::with('post.user', 'vehicle')->where('user_id', auth()->id())->where('status', 'accept')->get();
    }

    #[Computed]
    public function tripRequests() {
        return TripRequest::whereHas('post', function ($query) {
                $query->where('user_id', auth()->id());
            })
            ->where('status', 'accept')
            ->get();
    }
};
?>

    <div>
        <x-pages-heading>Active Rental Transactions</x-pages-heading>

        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-3 gap-6">
            
            @if ($this->rentalOffers)
                @foreach ($this->rentalOffers as $item)
                    <x-card class="w-full border-t-4 border-t-blue-500">
                        <div class="flex items-center justify-between gap-2">
                            <div>
                                {{-- Data comes from the Offer --}}
                                <x-text variant="strong">{{ $item->post->metadata['vehicle_type'] }}</x-text>
                                <x-text size="sm" variant="subtle">Requested Vehicle</x-text>
                            </div>
                            <div class="flex flex-col items-end gap-1">
                                <x-badge size="sm" color="green">Active</x-badge>
                                {{-- Distinguishing Label --}}
                                <x-badge size="xs" color="blue">From Client's Post</x-badge> 
                            </div>
                        </div>
                        <div class="mt-5">
                            <div class="flex items-center gap-2 justify-between">
                                <x-text variant="subtle">Renter: </x-text>
                                {{-- Commuter owns the post --}}
                                <x-text variant="strong">{{ $item->post->user->name ?? 'Unknown' }}</x-text>
                            </div>
                            <div class="flex items-center gap-2 justify-between">
                                <x-text variant="subtle">Coverage: </x-text>
                                {{-- Direct column in rental_offers --}}
                                <x-text variant="strong" class="truncate max-w-[150px]">{{ $item->destination_coverage }}</x-text>
                            </div>
                        </div>
                        <div class="mt-4 flex items-center gap-2 w-full">
                            <x-button class="w-full flex gap-2 items-center justify-center cursor-pointer">
                                <flux:icon.x-mark class="text-red-500 w-5 h-5" />
                                Cancel Trip
                            </x-button>
                            <x-button class="w-full flex gap-2 items-center justify-center cursor-pointer">
                                <flux:icon.check class="text-green-500 w-5 h-5"/>
                                Complete Trip
                            </x-button>
                        </div>
                        <div class="mt-4 flex items-center justify-center w-full">
                            <x-text class="text-blue-500 hover:text-blue-700 cursor-pointer">View more details</x-text>
                        </div>
                    </x-card>
                @endforeach
            @endif
            
            @if ($this->tripRequests)
                @foreach ($this->tripRequests as $item)
                    <x-card class="w-full border-t-4 border-t-orange-500">
                        <div class="flex items-center justify-between gap-2">
                            <div>
                                <x-text variant="strong">{{ $item->post->metadata['vehicle_type']}}</x-text>
                                <x-text size="sm" variant="subtle">Requested Vehicle</x-text>
                            </div>
                            <div class="flex flex-col items-end gap-1">
                                <x-badge size="sm" color="green">Active</x-badge>
            
                                <x-badge size="xs" color="orange">From Your Post</x-badge>
                            </div>
                        </div>
                        <div class="mt-5">
                            <div class="flex items-center gap-2 justify-between">
                                <x-text variant="subtle">Renter: </x-text>
                                {{-- Commuter made the request --}}
                                <x-text variant="strong">{{ $item->user->name ?? 'Unknown' }}</x-text>
                            </div>
                            <div class="flex items-center gap-2 justify-between">
                                <x-text variant="subtle">Coverage: </x-text>
                                {{-- Mapping Pick up to Drop off from trip_request --}}
                                <x-text variant="strong" class="truncate max-w-[150px]" title="{{ $item->pick_up_location }} to {{ $item->drop_off_location }}">
                                    {{ $item->pick_up_location }} to {{ $item->drop_off_location }}
                                </x-text>
                            </div>
                        </div>
                        <div class="mt-4 flex items-center gap-2 w-full">
                            <x-button class="w-full flex gap-2 items-center justify-center cursor-pointer">
                                <flux:icon.x-mark class="text-red-500 w-5 h-5" />
                                Cancel Trip
                            </x-button>
                            <x-button class="w-full flex gap-2 items-center justify-center cursor-pointer">
                                <flux:icon.check class="text-green-500 w-5 h-5"/>
                                Complete Trip
                            </x-button>
                        </div>
                        <div class="mt-4 flex items-center justify-center w-full">
                            <x-text class="text-blue-500 hover:text-blue-700 cursor-pointer">View more details</x-text>
                        </div>
                    </x-card>
                @endforeach
            @endif
        
        </div>
    </div>
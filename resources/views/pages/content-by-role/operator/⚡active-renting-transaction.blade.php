<?php

use Livewire\Component;
use Livewire\Attributes\Computed;

use App\Models\RentalOffer;
use App\Models\TripRequest;

new class extends Component
{   

    public bool $isShowRentalOfferViewMoreModal = false;
    public bool $isShowTripRequestViewMoreModal = false;
    public bool $isRentalOfferCompleteModal = false;
    public bool $isTripRequestConfirmModal = false;

    public string $rentalOfferModalType = '';
    public string $tripRequestModalType = '';

    public $rentalOfferData = null;
    public $tripRequestData = null;

    public function tripRequestConfirmModal($id, $type) {

        $this->isTripRequestConfirmModal = false;
        $this->isTripRequestConfirmModal = true;

        $this->tripRequestModalType = $type;

        $this->tripRequestData = null;
        $this->tripRequestData = TripRequest::with('user', 'post.user')->find($id);

    }

    public function rentalOfferCompleteModal($id, $type) {

        $this->isRentalOfferCompleteModal = false;
        $this->isRentalOfferCompleteModal = true;

        $this->rentalOfferModalType = $type;

        $this->rentalOfferData = null;
        $this->rentalOfferData = RentalOffer::with('post.user')->find($id);
    }

    public function showTripRequestViewMoreModal($id) {

        $this->tripRequestData = TripRequest::with('user', 'post.user')->find($id);

        $this->isShowTripRequestViewMoreModal = true;
    }

    public function showRentalOfferViewMoreModal($id) {

        $this->rentalOfferData = RentalOffer::with('vehicle', 'user', 'post.user')->find($id);

        $this->isShowRentalOfferViewMoreModal = true;
    }

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
                            <x-badge size="xs" color="blue">From Client's Post</x-badge>
                        </div>
                        <div class="flex flex-col items-end gap-1">
                             
                            <x-badge size="sm" color="green">Active</x-badge>
                        </div>
                    </div>
                    <div class="mt-5">
                        <div class="flex items-center gap-2 justify-between">
                            <x-text variant="subtle">Requested Vehicle</x-text>
                            <x-text variant="strong">{{ $item->post->metadata['vehicle_type'] }}</x-text>
                        </div>
                        <div class="flex items-center gap-2 justify-between">
                            <x-text variant="subtle">Renter: </x-text>
                            
                            <x-text variant="strong">{{ $item->post->user->name ?? 'Unknown' }}</x-text>
                        </div>
                        <div class="flex items-center gap-2 justify-between">
                            <x-text variant="subtle">Coverage: </x-text>
                           
                            <x-text variant="strong" class="truncate max-w-[150px]">{{ $item->destination_coverage }}</x-text>
                        </div>
                    </div>
                        <div class="mt-4 flex items-center gap-2 w-full">
                            <x-button 
                                wire:click="rentalOfferCompleteModal({{ $item->id }}, 'cancel-trip')" 
                                size="sm" 
                                class="w-full justify-center cursor-pointer"
                            >
                                <span class="flex items-center gap-2">
                                    <flux:icon.x-mark class="text-red-500 w-5 h-5" />
                                    Cancel Trip
                                </span>
                            </x-button>

                            <x-button 
                                wire:click="rentalOfferCompleteModal({{ $item->id }}, 'complete-trip')" 
                                size="sm" 
                                class="w-full justify-center cursor-pointer"
                            >
                                <span class="flex items-center gap-2">
                                    <flux:icon.check class="text-green-500 w-5 h-5"/>
                                    Complete Trip
                                </span>
                            </x-button>
                        </div>
                    <div class="mt-4 flex items-center justify-center w-full">
                        <x-text 
                            wire:click="showRentalOfferViewMoreModal({{ $item->id }})" 
                            class="text-blue-500 hover:text-blue-700 cursor-pointer flex items-center gap-1"
                        >
                            <span wire:loading.remove wire:target="showRentalOfferViewMoreModal({{ $item->id }})">
                                View more details
                            </span>

                            <span wire:loading wire:target="showRentalOfferViewMoreModal({{ $item->id }})" class="flex items-center gap-1">
                                <flux:icon.arrow-path class="animate-spin size-4" /> 
                            </span>
                        </x-text>                    
                    </div>
                </x-card>
            @endforeach
        @endif
        
        @if ($this->tripRequests)
            @foreach ($this->tripRequests as $item)
                <x-card class="w-full border-t-4 border-t-orange-500">
                    <div class="flex items-center justify-between gap-2">
                        <div>
                            <x-badge size="xs" color="orange">From Your Post</x-badge>
                        </div>
                        <div class="flex flex-col items-end gap-1">
                            <x-badge size="sm" color="green">Active</x-badge>
                        </div>
                    </div>
                    <div class="mt-5">
                        <div class="flex items-center gap-2 justify-between">
                            <x-text variant="subtle">Requested Vehicle</x-text>
                            <x-text variant="strong">{{ $item->post->metadata['vehicle_type']}}</x-text>
                        </div>
                        <div class="flex items-center gap-2 justify-between">
                            <x-text variant="subtle">Renter: </x-text>
                            <x-text variant="strong">{{ $item->user->name ?? 'Unknown' }}</x-text>
                        </div>
                        <div class="flex items-center gap-2 justify-between">
                            <x-text variant="subtle">Coverage: </x-text>
                            <x-text variant="strong" class="truncate max-w-[150px]" title="{{ $item->pick_up_location }} to {{ $item->drop_off_location }}">
                                {{ $item->pick_up_location }} to {{ $item->drop_off_location }}
                            </x-text>
                        </div>
                    </div>
                    <div class="mt-4 flex items-center gap-2 w-full">
                        <x-button wire:click="tripRequestConfirmModal({{ $item->id }}, 'cancel-trip')" size="sm" class="w-full flex gap-2 items-center justify-center cursor-pointer">
                            <span class="flex items-center gap-2">
                                <flux:icon.x-mark class="text-red-500 w-5 h-5" />
                                Cancel Trip
                            </span>
                        </x-button>
                        <x-button wire:click="tripRequestConfirmModal({{ $item->id }}, 'complete-trip')" size="sm" class="w-full flex gap-2 items-center justify-center cursor-pointer">
                            <span class="flex items-center gap-2">
                                <flux:icon.check class="text-green-500 w-5 h-5"/>
                                Complete Trip
                            </span>
                        </x-button>
                    </div>
                    <div class="mt-4 flex items-center justify-center w-full">
                        <x-text 
                            wire:click="showTripRequestViewMoreModal({{ $item->id }})" 
                            class="text-blue-500 hover:text-blue-700 cursor-pointer flex items-center gap-1"
                        >
                            {{-- 1. This text shows by default, but hides when clicked --}}
                            <span wire:loading.remove wire:target="showTripRequestViewMoreModal({{ $item->id }})">
                                View more details
                            </span>

                            {{-- 2. This spinner hides by default, but shows when clicked --}}
                            <span wire:loading wire:target="showTripRequestViewMoreModal({{ $item->id }})" class="flex items-center gap-1">
                                <flux:icon.arrow-path class="animate-spin size-4" /> 
                            </span>
                        </x-text>  
                    </div>
                </x-card>
            @endforeach
        @endif

    </div>

    <flux:modal wire:model="isTripRequestConfirmModal" class="w-96" >
        @if ($tripRequestData)
            <livewire:pages::content-by-role.operator.trip-request-confirm-modal
                :tripRequest="$tripRequestData"
                :modalType="$tripRequestModalType"
                wire:key="{{$tripRequestModalType}}-trip-request-confirm-modal-{{ $tripRequestData->id }}"
            />
        @endif
    </flux:modal>

    <flux:modal wire:model="isRentalOfferCompleteModal" class="w-96" >
        @if ($rentalOfferData)
            <livewire:pages::content-by-role.operator.rental-offer-complete-modal
                :rentalOffer="$rentalOfferData"
                :modalType="$rentalOfferModalType"
                wire:key="{{$rentalOfferModalType}}-rental-offer-complete-modal-{{ $rentalOfferData->id }}"
            />
        @endif
    </flux:modal>

    <flux:modal wire:model="isShowTripRequestViewMoreModal" class="w-auto" >
        @if ($tripRequestData)
            <livewire:pages::content-by-role.operator.trip-request-modal
                :tripRequest="$tripRequestData"
                wire:key="trip-request-modal-{{ $tripRequestData->id }}"
            />
        @endif
    </flux:modal>

    <flux:modal wire:model="isShowRentalOfferViewMoreModal" class="w-auto" >
        @if ($rentalOfferData)
            <livewire:pages::content-by-role.operator.rental-offer-modal
                :rentalOffer="$rentalOfferData"
                wire:key="rental-offer-modal-{{ $rentalOfferData->id }}"
            />
        @endif
    </flux:modal>

</div>
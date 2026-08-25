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
        return RentalOffer::with('post')->whereHas('post', function ($q) {
            $q->where('user_id', auth()->id());
        })
        ->where('status', 'accept')
        ->get();
    }

    #[Computed]
    public function tripRequests() {
        return TripRequest::with('post.user', 'user')
            ->where('user_id', auth()->id())
            ->where('status', 'accept')
            ->get();
    }

    // public function mount() {
    //     dd($this->rentalOffers);
    // }
};
?>

<div>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        @if ($this->rentalOffers && $this->rentalOffers->count())
            @foreach ($this->rentalOffers as $item)
                <flux:card class="border-t-4 border-t-blue-500 !p-4">
                    <div class="flex items-center justify-between gap-2">
                        <flux:badge size="xs" color="blue">From your post</flux:badge>
                        <flux:badge size="sm" color="green">Active</flux:badge>
                    </div>
                    <div class="mt-4 space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="font-secondary text-xs text-light-txt-muted dark:text-dark-txt-muted">Offered Vehicle</span>
                            <span class="font-secondary text-sm font-medium text-light-txt-primary dark:text-dark-txt-primary">{{ $item->post->metadata['vehicle_type'] }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="font-secondary text-xs text-light-txt-muted dark:text-dark-txt-muted">Operator Name</span>
                            <span class="font-secondary text-sm font-medium text-light-txt-primary dark:text-dark-txt-primary">{{ $item->user->name ?? 'Unknown' }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="font-secondary text-xs text-light-txt-muted dark:text-dark-txt-muted">Coverage</span>
                            <span class="font-secondary text-sm font-medium text-light-txt-primary dark:text-dark-txt-primary truncate max-w-[150px]">{{ $item->destination_coverage }}</span>
                        </div>
                    </div>
                    <div class="mt-4 flex gap-2">
                        <flux:button
                            wire:click="rentalOfferCompleteModal({{ $item->id }}, 'cancel-trip')"
                            size="sm"
                            variant="ghost"
                            class="flex-1 justify-center !font-secondary"
                        >
                            <flux:icon.x-mark class="w-4 h-4 text-danger" />
                            Cancel Trip
                        </flux:button>
                        <flux:button
                            wire:click="rentalOfferCompleteModal({{ $item->id }}, 'complete-trip')"
                            size="sm"
                            variant="primary"
                            class="flex-1 justify-center !font-secondary"
                        >
                            <flux:icon.check class="w-4 h-4" />
                            Complete Trip
                        </flux:button>
                    </div>
                    <div class="mt-4 flex justify-center">
                        <button
                            wire:click="showRentalOfferViewMoreModal({{ $item->id }})"
                            class="font-secondary text-sm text-primary hover:text-primary-hover dark:text-primary dark:hover:text-primary-hover flex items-center gap-1 cursor-pointer"
                        >
                            <span wire:loading.remove wire:target="showRentalOfferViewMoreModal({{ $item->id }})">
                                View more details
                            </span>
                            <span wire:loading wire:target="showRentalOfferViewMoreModal({{ $item->id }})" class="flex items-center gap-1">
                                <flux:icon.arrow-path class="animate-spin size-4" />
                            </span>
                        </button>
                    </div>
                </flux:card>
            @endforeach
        @endif

        @if ($this->tripRequests && $this->tripRequests->count())
            @foreach ($this->tripRequests as $item)
                <flux:card class="border-t-4 border-t-orange-500 !p-4">
                    <div class="flex items-center justify-between gap-2">
                        <flux:badge size="xs" color="orange">From Operator's Post</flux:badge>
                        <flux:badge size="sm" color="green">Active</flux:badge>
                    </div>
                    <div class="mt-4 space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="font-secondary text-xs text-light-txt-muted dark:text-dark-txt-muted">Offered Vehicle</span>
                            <span class="font-secondary text-sm font-medium text-light-txt-primary dark:text-dark-txt-primary">{{ $item->post->metadata['vehicle_type'] }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="font-secondary text-xs text-light-txt-muted dark:text-dark-txt-muted">Operator Name</span>
                            <span class="font-secondary text-sm font-medium text-light-txt-primary dark:text-dark-txt-primary">{{ $item->post->user->name ?? 'Unknown' }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="font-secondary text-xs text-light-txt-muted dark:text-dark-txt-muted">Coverage</span>
                            <span class="font-secondary text-sm font-medium text-light-txt-primary dark:text-dark-txt-primary truncate max-w-[150px]">{{ $item->pick_up_location }} to {{ $item->drop_off_location }}</span>
                        </div>
                    </div>
                    <div class="mt-4 flex gap-2">
                        <flux:button
                            wire:click="tripRequestConfirmModal({{ $item->id }}, 'cancel-trip')"
                            size="sm"
                            variant="ghost"
                            class="flex-1 justify-center !font-secondary"
                        >
                            <flux:icon.x-mark class="w-4 h-4 text-danger" />
                            Cancel Trip
                        </flux:button>
                        <flux:button
                            wire:click="tripRequestConfirmModal({{ $item->id }}, 'complete-trip')"
                            size="sm"
                            variant="primary"
                            class="flex-1 justify-center !font-secondary"
                        >
                            <flux:icon.check class="w-4 h-4" />
                            Complete Trip
                        </flux:button>
                    </div>
                    <div class="mt-4 flex justify-center">
                        <button
                            wire:click="showTripRequestViewMoreModal({{ $item->id }})"
                            class="font-secondary text-sm text-primary hover:text-primary-hover dark:text-primary dark:hover:text-primary-hover flex items-center gap-1 cursor-pointer"
                        >
                            <span wire:loading.remove wire:target="showTripRequestViewMoreModal({{ $item->id }})">
                                View more details
                            </span>
                            <span wire:loading wire:target="showTripRequestViewMoreModal({{ $item->id }})" class="flex items-center gap-1">
                                <flux:icon.arrow-path class="animate-spin size-4" />
                            </span>
                        </button>
                    </div>
                </flux:card>
            @endforeach
        @endif
    </div>

    {{-- Modals with consistent design --}}
    <flux:modal
        wire:model="isTripRequestConfirmModal"
        :closable="false"
        class="w-full max-w-[95vw] sm:max-w-md md:max-w-lg mx-auto max-h-[80vh] sm:max-h-[90vh] overflow-hidden rounded-xl"
        x-on:close-modal.window="$wire.set('isTripRequestConfirmModal', false)"
    >
        @if ($tripRequestData)
            <div class="flex flex-col max-h-[calc(80vh-2rem)] sm:max-h-[calc(90vh-2rem)] overflow-y-auto overscroll-contain p-4 sm:p-6 !pr-4 sm:!pr-6 space-y-5">
                <livewire:pages::content-by-role.operator.trip-request-confirm-modal
                    :tripRequest="$tripRequestData"
                    :modalType="$tripRequestModalType"
                    wire:key="{{$tripRequestModalType}}-trip-request-confirm-modal-{{ $tripRequestData->id }}"
                />
            </div>
        @endif
    </flux:modal>

    <flux:modal
        wire:model="isRentalOfferCompleteModal"
        :closable="false"
        class="w-full max-w-[95vw] sm:max-w-md md:max-w-lg mx-auto max-h-[80vh] sm:max-h-[90vh] overflow-hidden rounded-xl"
        x-on:close-modal.window="$wire.set('isRentalOfferCompleteModal', false)"
    >
        @if ($rentalOfferData)
            <div class="flex flex-col max-h-[calc(80vh-2rem)] sm:max-h-[calc(90vh-2rem)] overflow-y-auto overscroll-contain p-4 sm:p-6 !pr-4 sm:!pr-6 space-y-5">
                <livewire:pages::content-by-role.operator.rental-offer-complete-modal
                    :rentalOffer="$rentalOfferData"
                    :modalType="$rentalOfferModalType"
                    wire:key="{{$rentalOfferModalType}}-rental-offer-complete-modal-{{ $rentalOfferData->id }}"
                />
            </div>
        @endif
    </flux:modal>

    <flux:modal
        wire:model="isShowTripRequestViewMoreModal"
        :closable="false"
        class="w-full max-w-[95vw] sm:max-w-md md:max-w-lg mx-auto max-h-[80vh] sm:max-h-[90vh] overflow-hidden rounded-xl"
        x-on:close-modal.window="$wire.set('isShowTripRequestViewMoreModal', false)"
    >
        @if ($tripRequestData)
            <div class="flex flex-col max-h-[calc(80vh-2rem)] sm:max-h-[calc(90vh-2rem)] overflow-y-auto overscroll-contain p-4 sm:p-6 !pr-4 sm:!pr-6 space-y-5">
                <livewire:pages::content-by-role.commuter.trip-request-view-more-modal
                    :tripRequest="$tripRequestData"
                    wire:key="trip-request-modal-{{ $tripRequestData->id }}"
                />
            </div>
        @endif
    </flux:modal>

    <flux:modal
        wire:model="isShowRentalOfferViewMoreModal"
        :closable="false"
        class="w-full max-w-[95vw] sm:max-w-md md:max-w-lg mx-auto max-h-[80vh] sm:max-h-[90vh] overflow-hidden rounded-xl"
        x-on:close-modal.window="$wire.set('isShowRentalOfferViewMoreModal', false)"
    >
        @if ($rentalOfferData)
            <div class="flex flex-col max-h-[calc(80vh-2rem)] sm:max-h-[calc(90vh-2rem)] overflow-y-auto overscroll-contain p-4 sm:p-6 !pr-4 sm:!pr-6 space-y-5">
                <livewire:pages::content-by-role.commuter.active-transaction-view-more-modal
                    :rentalOffer="$rentalOfferData"
                    wire:key="rental-offer-modal-{{ $rentalOfferData->id }}"
                />
            </div>
        @endif
    </flux:modal>
</div>
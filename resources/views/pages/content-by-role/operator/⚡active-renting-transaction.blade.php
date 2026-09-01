<?php

use Livewire\Component;
use Livewire\Attributes\Computed;

use App\Models\TripRequest;

new class extends Component
{   

    public bool $isShowTripRequestViewMoreModal = false;
    public bool $isTripRequestConfirmModal = false;

    public string $tripRequestModalType = '';

    public $tripRequestData = null;

    public function tripRequestConfirmModal($id, $type) {

        // Only trip requests made against one of my own posts can be
        // cancelled/completed from here.
        $tripRequest = TripRequest::with('user', 'post.user')
            ->whereHas('post', fn ($q) => $q->where('user_id', auth()->id()))
            ->find($id);

        if (! $tripRequest) {
            return;
        }

        $this->isTripRequestConfirmModal = false;
        $this->isTripRequestConfirmModal = true;

        $this->tripRequestModalType = $type;

        $this->tripRequestData = $tripRequest;

    }

    public function showTripRequestViewMoreModal($id) {

        $tripRequest = TripRequest::with('user', 'post.user')
            ->whereHas('post', fn ($q) => $q->where('user_id', auth()->id()))
            ->find($id);

        if (! $tripRequest) {
            return;
        }

        $this->tripRequestData = $tripRequest;

        $this->isShowTripRequestViewMoreModal = true;
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
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6">
        {{-- Trip Requests --}}
        @forelse ($this->tripRequests as $item)
            <flux:card class="!p-4 !rounded-xl !border !border-light-bd-default dark:!border-dark-bd-default !shadow-sm hover:!shadow-md transition-shadow">
                {{-- Header --}}
                <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <flux:badge size="sm" color="orange">From Your Post</flux:badge>
                    </div>
                    <flux:badge size="sm" color="green" icon="check-circle">Active</flux:badge>
                </div>

                {{-- Details --}}
                <div class="mt-4 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">Vehicle</span>
                        <span class="font-secondary text-sm font-medium text-light-txt-primary dark:text-dark-txt-primary">
                            {{ $item->post->metadata['vehicle_type'] ?? '—' }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">Renter</span>
                        <span class="font-secondary text-sm font-medium text-light-txt-primary dark:text-dark-txt-primary">
                            {{ $item->user->name ?? 'Unknown' }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">Coverage</span>
                        <span class="font-secondary text-sm font-medium text-light-txt-primary dark:text-dark-txt-primary truncate max-w-[140px] sm:max-w-[180px]" title="{{ $item->pick_up_location }} → {{ $item->drop_off_location }}">
                            {{ $item->pick_up_location }} → {{ $item->drop_off_location }}
                        </span>
                    </div>
                </div>

                {{-- Actions --}}
                <div class="mt-4 flex flex-col sm:flex-row gap-2">
                    <flux:button
                        wire:click="tripRequestConfirmModal({{ $item->id }}, 'cancel-trip')"
                        size="sm"
                        variant="danger"
                        icon="x-mark"
                        class="w-full justify-center font-secondary"
                    >
                        Cancel Trip
                    </flux:button>
                    <flux:button
                        wire:click="tripRequestConfirmModal({{ $item->id }}, 'complete-trip')"
                        size="sm"
                        variant="primary"
                        icon="check"
                        class="w-full justify-center font-secondary"
                    >
                        Complete Trip
                    </flux:button>
                </div>

                {{-- View more --}}
                <div class="mt-3 text-center">
                    <x-text
                        wire:click="showTripRequestViewMoreModal({{ $item->id }})"
                        class="text-blue-500 hover:text-blue-700 cursor-pointer flex items-center justify-center gap-1 font-secondary text-sm"
                    >
                        <span wire:loading.remove wire:target="showTripRequestViewMoreModal({{ $item->id }})">
                            View more details
                        </span>
                        <span wire:loading wire:target="showTripRequestViewMoreModal({{ $item->id }})" class="inline-flex items-center gap-1">
                            <flux:icon.arrow-path class="animate-spin size-4" />
                            Loading…
                        </span>
                    </x-text>
                </div>
            </flux:card>
        @empty
            <div class="col-span-full text-center py-8 text-light-txt-muted dark:text-dark-txt-muted">
                <flux:icon.inbox class="w-8 h-8 mx-auto mb-2" />
                <p class="font-secondary">No active trip requests.</p>
            </div>
        @endforelse
    </div>

    {{-- Modals --}}
    {{-- Trip Request Confirm Modal --}}
    <flux:modal
        wire:model="isTripRequestConfirmModal"
        :closable="false"
        class="w-[calc(100%-2rem)] sm:max-w-lg md:max-w-2xl mx-auto rounded-xl overflow-hidden"
    >
        @if ($tripRequestData)
            <livewire:pages::content-by-role.operator.trip-request-confirm-modal
                :tripRequest="$tripRequestData"
                :modalType="$tripRequestModalType"
                wire:key="{{$tripRequestModalType}}-trip-request-confirm-modal-{{ $tripRequestData->id }}"
            />
        @endif
    </flux:modal>

    {{-- Trip Request View More Modal --}}
    <flux:modal
        wire:model="isShowTripRequestViewMoreModal"
        :closable="false"
        class="w-[calc(100%-2rem)] sm:max-w-lg md:max-w-2xl mx-auto rounded-xl overflow-hidden"
    >
        @if ($tripRequestData)
            <livewire:pages::content-by-role.operator.trip-request-modal
                :tripRequest="$tripRequestData"
                wire:key="trip-request-modal-{{ $tripRequestData->id }}"
            />
        @endif
    </flux:modal>
</div>
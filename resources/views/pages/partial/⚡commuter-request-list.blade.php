<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;

use App\Models\Post;
use App\Models\RentalOffer;
use App\Models\RentTransaction;
use App\Models\Notification;
use App\Models\UserNotification;

use App\Events\LiveActionEvent;
use App\Events\NotificationEvent;

new class extends Component
{
    public ?Post $post = null;
    public $interested_user = null;
    public $is_show_decline_modal = false;
    public $is_show_view_more_modal = false;
    public $post_interest_info = null;

    public function showViewMoreModal($id) {
        $this->is_show_view_more_modal = false;
        $this->is_show_view_more_modal = true;

        $this->post_interest_info = RentalOffer::where('id', $id)->first();
    }


    #[Computed]
    public function activeTransaction() {
        return RentTransaction::where('post_owner_id', $this->post->user_id)
            ->where('status', 'ongoing')
            ->whereHas('rentalOffer', function ($query) {
                $query->where('post_id', $this->post->id);
            })
            ->first();
    }

    #[On('transaction-updated')]
    public function refreshRequests() {
        unset($this->getRentalOffer);
        unset($this->activeTransaction);

        $this->is_show_decline_modal = false;
    }

    public function declineThisinterested_user($id) {
        RentalOffer::where('id', $id)->update(['status' => 'decline']);

        // Let the operator know their rental offer was declined.
        if ($this->interested_user) {
            $notification = Notification::create([
                'type'    => 'Declined',
                'title'   => 'Rental Offer Declined',
                'message' => "Your rental offer was declined by {$this->post->user->name}.",
            ]);

            UserNotification::create([
                'notification_id' => $notification->id,
                'user_id'         => $this->interested_user->user_id,
            ]);

            broadcast(new NotificationEvent());
        }

        unset($this->getRentalOffer);

        $this->is_show_decline_modal = false;
        $this->interested_user = null;

        $this->dispatch('interested-list-updated');

        Flux::toast(
            duration: 0,
            variant: 'success',
            heading: 'Offer declined',
            text: 'The rental offer has been declined.',
        );
    }

    public function showDeclineModal($id) {
        $this->is_show_decline_modal = false;
        $this->is_show_decline_modal = true;
        $this->interested_user = null;
        $this->interested_user = $this->post->rentalOffer->where('id', $id)->first();

    }


    #[Computed]
    public function getRentalOffer() {
        return RentalOffer::with('user')->where('post_id', $this->post->id)->whereIn('status', ['pending', 'cancel'])->get();
    }

};
?>

<div>
    @if ($this->activeTransaction)
        <flux:callout 
            variant="warning" 
            icon="exclamation-circle" 
            heading="This vehicle already has an active rental. New requests stay pending until the current transaction ends." 
        />
        @forelse ($this->getRentalOffer as $rentalOffer)

            @if ($this->activeTransaction && $this->activeTransaction->rental_offer_id === $rentalOffer->id)
                 @continue
            @endif

            <x-card
                class="!rounded-xl !border !border-light-bd-default dark:!border-dark-bd-default !bg-light-secondary dark:!bg-dark-secondary !shadow-sm my-3 opacity-60"
                disabled
            >
                <div class="flex flex-col sm:flex-row items-start gap-3">
                    <div class="flex items-start gap-3 flex-1 min-w-0">
                        <x-avatar name="{{ $rentalOffer->user->name }}" color="lime" />
                        <div class="flex flex-col gap-1">
                            <div class="flex flex-wrap items-center gap-x-2">
                                <x-text variant="strong">{{ $rentalOffer->user->name }}</x-text>
                                <x-text size="sm" variant="subtle">
                                    Requested {{ $rentalOffer->created_at->diffForHumans(['short' => true]) }}
                                </x-text>
                            </div>
                            <div class="flex flex-wrap items-center gap-1">
                                <x-badge color="green">
                                    {{ $rentalOffer->metadata['vehicle_name'] }}
                                </x-badge>
                                <x-badge color="green" icon="map-pin">
                                    {{ $rentalOffer->destination_coverage }}
                                </x-badge>
                                <x-badge color="green" icon="calendar">
                                    {{ $rentalOffer->available_from->format('D, M j Y') }}
                                    -
                                    {{ $rentalOffer->available_until->format('D, M j Y') }}
                                </x-badge>
                            </div>
                            <div class="mt-1">
                                <x-text>{{ $post->message }}</x-text>
                            </div>
                        </div>
                    </div>
                    <div class="flex flex-col items-end gap-2 shrink-0 w-full sm:w-auto">
                        <div class="flex flex-row sm:flex-col items-center gap-2 w-full sm:w-auto">
                            <x-button wire:click="$dispatch('open-confirm-modal', { id: {{ $rentalOffer->id }} })" variant="primary" color="green" disabled class="w-full sm:w-auto">Accept</x-button>
                            <x-button wire:click="showDeclineModal({{ $rentalOffer->id }})" variant="primary" color="red" disabled class="w-full sm:w-auto">Decline</x-button>
                        </div>
                        <button
                            type="button"
                            wire:click="showViewMoreModal({{ $rentalOffer->id }})"
                            class="w-full sm:w-auto text-center text-xs font-medium font-secondary px-3 py-1.5 rounded-lg border border-light-bd-default dark:border-dark-bd-default text-light-txt-muted dark:text-dark-txt-muted hover:text-light-txt-primary dark:hover:text-dark-txt-primary hover:bg-light-subtle dark:hover:bg-dark-subtle transition cursor-pointer"
                        >
                            View more
                        </button>
                    </div>
                </div>
            </x-card>
        @empty
            {{-- Nothing else pending — the banner above already says everything that's needed here. --}}
        @endforelse
    @else
        @forelse ($this->getRentalOffer as $rentalOffer)

            @if ($this->activeTransaction && $this->activeTransaction->rental_offer_id === $rentalOffer->id)
                 @continue
            @endif

            <x-card
                class="!rounded-xl !border !border-light-bd-default dark:!border-dark-bd-default !bg-light-secondary dark:!bg-dark-secondary !shadow-sm my-3"
            >
                @if ($rentalOffer->status === 'cancel')
                    <flux:badge color="orange" size="sm" class="mb-2">Cancelled</flux:badge>
                @endif
                <div class="flex flex-col sm:flex-row items-start gap-3">
                    <div class="flex items-start gap-3 flex-1 min-w-0">
                        <x-avatar name="{{ $rentalOffer->user->name }}" color="lime" />
                        <div class="flex flex-col gap-1">
                            <div class="flex flex-wrap items-center gap-x-2">
                                <x-text variant="strong">{{ $rentalOffer->user->name }}</x-text>
                                <x-text size="sm" variant="subtle">
                                    Requested {{ $rentalOffer->created_at->diffForHumans(['short' => true]) }}
                                </x-text>
                            </div>
                            <div class="flex flex-wrap items-center gap-1">
                                <x-badge color="green">
                                    {{ $rentalOffer->metadata['vehicle_name'] }}
                                </x-badge>
                                <x-badge color="green" icon="map-pin">
                                    {{ $rentalOffer->destination_coverage }}
                                </x-badge>
                                <x-badge color="green" icon="calendar">
                                    {{ $rentalOffer->available_from->format('D, M j Y') }}
                                    -
                                    {{ $rentalOffer->available_until->format('D, M j Y') }}
                                </x-badge>
                            </div>
                            <div class="mt-1">
                                <x-text>{{ $rentalOffer->message }}</x-text>
                            </div>
                        </div>
                    </div>
                    <div class="flex flex-col items-end gap-2 shrink-0 w-full sm:w-auto">
                        <div class="flex flex-row sm:flex-col items-center gap-2 w-full sm:w-auto">
                            <x-button wire:click="$dispatch('open-confirm-modal', { id: {{ $rentalOffer->id }} })" variant="primary" color="green" class="w-full sm:w-auto">Accept</x-button>
                            <x-button wire:click="showDeclineModal({{ $rentalOffer->id }})" variant="primary" color="red" class="w-full sm:w-auto">Decline</x-button>
                        </div>
                        <button
                            type="button"
                            wire:click="showViewMoreModal({{ $rentalOffer->id }})"
                            class="w-full sm:w-auto text-center text-xs font-medium font-secondary px-3 py-1.5 rounded-lg border border-light-bd-default dark:border-dark-bd-default text-light-txt-muted dark:text-dark-txt-muted hover:text-light-txt-primary dark:hover:text-dark-txt-primary hover:bg-light-subtle dark:hover:bg-dark-subtle transition cursor-pointer"
                        >
                            View more
                        </button>
                    </div>
                </div>
            </x-card>
        @empty
            <x-card class="!rounded-xl !border !border-dashed !border-light-bd-strong dark:!border-dark-bd-strong !bg-light-secondary dark:!bg-dark-secondary !text-center !p-8">
                <flux:icon name="clipboard-document-list" class="w-8 h-8 mx-auto text-light-txt-muted dark:text-dark-txt-muted mb-2" />
                <x-text variant="subtle" class="!font-secondary block" style="font-size: var(--text-table-row)">
                    No interested operators yet
                </x-text>
            </x-card>
        @endforelse
    @endif

    <!-- ==================== -->
    <!-- DECLINE MODAL (feed style) -->
    <!-- ==================== -->
    <flux:modal
        wire:model="is_show_decline_modal"
        :closable="false"
        class="w-[calc(100%-2rem)] sm:max-w-lg md:max-w-2xl lg:max-w-3xl mx-auto max-h-[80vh] sm:max-h-[90vh] overflow-hidden rounded-xl"
    >
        <div class="flex flex-col max-h-[calc(80vh-2rem)] sm:max-h-[calc(90vh-2rem)] overflow-y-auto overscroll-contain p-4 sm:p-6 !pr-4 sm:!pr-6 space-y-5">
            <!-- Header -->
            <div class="flex items-start justify-between">
                <div>
                    <flux:heading size="xl" class="!font-primary !font-bold text-light-txt-primary dark:text-dark-txt-primary">
                        Decline this interested user?
                    </flux:heading>
                    <flux:text class="mt-1 font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">
                        This action cannot be undone.
                    </flux:text>
                </div>
                <flux:modal.close>
                    <button type="button" class="p-1 rounded-full hover:bg-light-subtle dark:hover:bg-dark-subtle text-light-txt-muted dark:text-dark-txt-muted -mt-1">
                        <flux:icon name="x-mark" class="w-5 h-5" />
                    </button>
                </flux:modal.close>
            </div>

            @if ($this->interested_user)
                <!-- Footer -->
                <div class="flex flex-col-reverse sm:flex-row justify-end items-stretch sm:items-center gap-2 pt-2 border-t border-light-bd-default dark:border-dark-bd-default">
                    <flux:modal.close>
                        <x-button type="button" variant="ghost" class="w-full sm:w-auto justify-center !font-secondary">
                            Cancel
                        </x-button>
                    </flux:modal.close>
                    <x-button
                        wire:click="declineThisinterested_user({{ $this->interested_user->id }})"
                        variant="danger"
                        class="w-full sm:w-auto justify-center !font-secondary"
                    >
                        Decline
                    </x-button>
                </div>
            @endif
        </div>
    </flux:modal>

    <!-- ==================== -->
    <!-- VIEW MORE MODAL (feed style) -->
    <!-- ==================== -->
    <flux:modal
        wire:model="is_show_view_more_modal"
        :closable="false"
        class="w-[calc(100%-2rem)] sm:max-w-lg md:max-w-2xl lg:max-w-3xl mx-auto max-h-[80vh] sm:max-h-[90vh] overflow-hidden rounded-xl"
    >
        <div class="flex flex-col max-h-[calc(80vh-2rem)] sm:max-h-[calc(90vh-2rem)] overflow-y-auto overscroll-contain p-4 sm:p-6 !pr-4 sm:!pr-6 space-y-5">
            <!-- Header -->
            <div class="flex items-start justify-between">
                <div>
                    <flux:heading size="xl" class="!font-primary !font-bold text-light-txt-primary dark:text-dark-txt-primary">
                        Operator's details
                    </flux:heading>
                    <flux:text class="mt-1 font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">
                        Full information about the operator's rental offer.
                    </flux:text>
                </div>
                <flux:modal.close>
                    <button type="button" class="p-1 rounded-full hover:bg-light-subtle dark:hover:bg-dark-subtle text-light-txt-muted dark:text-dark-txt-muted -mt-1">
                        <flux:icon name="x-mark" class="w-5 h-5" />
                    </button>
                </flux:modal.close>
            </div>

            <!-- Body -->
            @if ($this->post_interest_info)
                <div class="space-y-5">
                    <!-- Operator identity -->
                    <div class="flex items-center gap-2.5 rounded-lg border border-light-bd-default dark:border-dark-bd-default p-3">
                        <flux:avatar size="sm" name="{{ $this->post_interest_info->user->name }}" color="emerald"/>
                        <div class="flex flex-col">
                            <x-text variant="strong" class="text-sm leading-tight">{{ $this->post_interest_info->user->name }}</x-text>
                            <x-text variant="subtle" style="font-size: var(--text-timestamp)">Operator</x-text>
                        </div>
                    </div>

                    <!-- Contact details -->
                    <div class="rounded-lg border border-light-bd-default dark:border-dark-bd-default divide-y divide-light-bd-default dark:divide-dark-bd-default">
                        <div class="flex items-center justify-between gap-3 p-3">
                            <div class="flex items-center gap-1.5 text-light-txt-muted dark:text-dark-txt-muted shrink-0">
                                <flux:icon.map-pin class="w-4 h-4" />
                                <x-text class="text-inherit" style="font-size: var(--text-table-row)">Address</x-text>
                            </div>
                            <x-text variant="strong" class="text-right" style="font-size: var(--text-table-row)">{{ $this->post_interest_info->user->address }}</x-text>
                        </div>

                        <div class="flex items-center justify-between gap-3 p-3">
                            <div class="flex items-center gap-1.5 text-light-txt-muted dark:text-dark-txt-muted shrink-0">
                                <flux:icon.phone class="w-4 h-4" />
                                <x-text class="text-inherit" style="font-size: var(--text-table-row)">Phone no.</x-text>
                            </div>
                            <x-text variant="strong" style="font-size: var(--text-table-row)">{{ $this->post_interest_info->user->phone_number }}</x-text>
                        </div>
                    </div>

                    <div>
                        @if (!empty($post_interest_info->metadata['vehicle_images']))

                            <x-text variant="strong" class="block mb-2" style="font-size: var(--text-table-row)">Vehicle images</x-text>

                            @php
                                $urls = array_map(fn($path) => Storage::url($path), $post_interest_info->metadata['vehicle_images']);
                            @endphp

                            <div x-data="{ open: false, index: 0, images: @js($urls) }" class="mt-3">
                                <div class="grid grid-cols-3 gap-1.5 auto-rows-[110px]">
                                    @foreach ($urls as $i => $url)
                                        @if ($i === 0)
                                            <button type="button" @click="open = true; index = {{ $i }}" class="col-span-2 row-span-2 relative rounded-lg overflow-hidden bg-light-subtle dark:bg-dark-secondary group cursor-pointer">
                                                <img src="{{ $url }}" alt="Vehicle attachment image" class="w-full h-full object-cover" loading="lazy" />
                                                <div class="absolute inset-0 bg-black/0 group-hover:bg-black/25 transition-colors flex items-center justify-center">
                                                    <flux:icon.magnifying-glass-plus class="size-5 text-white opacity-0 group-hover:opacity-100 transition-opacity" />
                                                </div>
                                            </button>
                                        @elseif ($i < 3)
                                            <button type="button" @click="open = true; index = {{ $i }}" class="relative rounded-lg overflow-hidden bg-light-subtle dark:bg-dark-secondary group cursor-pointer">
                                                <img src="{{ $url }}" alt="Vehicle attachment image" class="w-full h-full object-cover" loading="lazy" />
                                                @if ($i === 2 && count($urls) > 3)
                                                    <div class="absolute inset-0 bg-black/45 flex items-center justify-center text-white text-sm font-medium">
                                                        +{{ count($urls) - 3 }}
                                                    </div>
                                                @else
                                                    <div class="absolute inset-0 bg-black/0 group-hover:bg-black/25 transition-colors flex items-center justify-center">
                                                        <flux:icon.magnifying-glass-plus class="size-5 text-white opacity-0 group-hover:opacity-100 transition-opacity" />
                                                    </div>
                                                @endif
                                            </button>
                                        @endif
                                    @endforeach
                                </div>
                                <div
                                    x-show="open"
                                    x-cloak
                                    class="fixed inset-0 z-50 bg-black/70 flex items-center justify-center p-6"
                                    @keydown.escape.window="open = false"
                                    >
                                    <div @click.outside="open = false" class="bg-white dark:bg-dark-primary rounded-xl overflow-hidden max-w-lg w-full">
                                        <div class="flex items-center justify-between px-4 py-2.5 border-b border-light-bd-default dark:border-dark-bd-default">
                                            <span class="text-sm text-light-txt-muted" x-text="(index + 1) + ' / ' + images.length"></span>
                                            <button @click="open = false" class="text-light-txt-muted hover:text-light-txt-primary dark:hover:text-white cursor-pointer">
                                                <flux:icon.x-mark class="size-5" />
                                            </button>
                                        </div>

                                        <div class="relative">
                                            <img :src="images[index]" class="w-full h-80 object-cover" alt="Vehicle attachment image, full size" />

                                            <button
                                                x-show="images.length > 1"
                                                @click="index = (index - 1 + images.length) % images.length"
                                                class="absolute left-2 top-1/2 -translate-y-1/2 bg-black/50 hover:bg-black/70 rounded-full size-8 flex items-center justify-center text-white cursor-pointer"
                                            >
                                                <flux:icon.chevron-left class="size-4" />
                                            </button>
                                            <button
                                                x-show="images.length > 1"
                                                @click="index = (index + 1) % images.length"
                                                class="absolute right-2 top-1/2 -translate-y-1/2 bg-black/50 hover:bg-black/70 rounded-full size-8 flex items-center justify-center text-white cursor-pointer"
                                            >
                                                <flux:icon.chevron-right class="size-4" />
                                            </button>
                                        </div>

                                        <div class="flex gap-1.5 p-3 overflow-x-auto" x-show="images.length > 1">
                                            <template x-for="(img, i) in images" :key="i">
                                                <button @click="index = i" class="shrink-0 cursor-pointer">
                                                    <img :src="img" class="w-12 h-9 object-cover rounded" :class="i === index ? 'ring-2 ring-blue-500' : 'opacity-60'" />
                                                </button>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </flux:modal>

    
    <livewire:pages::partial.create_rental_transaction
        :key="'create-rental-transaction-' . $post->id"
    />
</div>
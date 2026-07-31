<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;

use App\Models\Post;
use App\Models\RentalOffer;
use App\Models\RentTransaction;

use App\Events\LiveActionEvent;

new class extends Component
{
    public ?Post $post = null;
    public $is_show_confirm_modal = false;
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
        return RentTransaction::where('post_owner_id', $this->post->user_id)->where('status', 'ongoing')->first();
    }

    #[On('transaction-updated')]
    public function refreshRequests() {
        unset($this->getRentalOffer);
        unset($this->activeTransaction);
    }

    public function declineThisinterested_user($id) {
        RentalOffer::where('id', $id)->update(['status' => 'decline']);
    }

    public function showDeclineModal($id) {
        $this->is_show_confirm_modal = false;
        $this->is_show_decline_modal = false;
        $this->is_show_decline_modal = true;
        $this->interested_user = null;
        $this->interested_user = $this->post->rentalOffer->where('id', $id)->first();

    }

    public function showConfirmModal($id) {

        $this->is_show_confirm_modal = false;
        $this->is_show_confirm_modal = true;
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

            <x-card class="my-2" variant="subtle" disabled>

                <div class="flex items-start gap-2">
                    <div class="flex-1 flex items-start gap-2">
                        <div>
                            <x-avatar name="{{ $rentalOffer->user->name }}" color="lime"/>
                        </div>
                        <div class="flex flex-col gap-1 items-start">
                            <div>
                                <x-text variant="strong">{{ $rentalOffer->user->name }}</x-text>
                                <x-text size="sm" variant="subtle">
                                    {{ $rentalOffer->created_at->diffForHumans(['short' => true]) }}
                                </x-text>
                            </div>

                            <div class="flex items-center gap-1">

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

                            <div class="mt-2">
                                <x-text>
                                {{ $post->message }}
                                </x-text>
                            </div>

                        </div>
                    </div>
                    <div>
                        <div class="flex flex-col items-center gap-2">
                            <x-button wire:click="showConfirmModal({{ $rentalOffer->id }})" variant="primary" color="green" disabled>Accepts</x-button>
                            <x-button wire:click="showDeclineModal({{ $rentalOffer->id }})" variant="primary" color="red" disabled>Decline</x-button>
                        </div>
                        <div class="mt-4">
                            <x-text wire:click="showViewMoreModal({{ $rentalOffer->id }})" class="cursor-pointer hover:underline hover:text-gray-800 transition">View more</x-text>
                        </div>
                    </div>
                </div>
            </x-card>
        @empty
            No record found
        @endforelse
    @else
        @forelse ($this->getRentalOffer as $rentalOffer)

            @if ($this->activeTransaction && $this->activeTransaction->rental_offer_id === $rentalOffer->id)
                 @continue
            @endif

            <x-card class="my-2" variant="subtle" disabled>

                @if ($rentalOffer->status === 'cancel')
                    <flux:badge class="my-4" color="red" size="sm">Cancelled</flux:badge>
                @endif

                <div class="flex items-start gap-2">
                    <div class="flex-1 flex items-start gap-2">
                        <div>
                            <x-avatar name="{{ $rentalOffer->user->name }}" color="lime"/>
                        </div>
                        <div class="flex flex-col gap-1 items-start">
                            <div>
                                <x-text variant="strong">{{ $rentalOffer->user->name }}</x-text>
                                <x-text size="sm" variant="subtle">
                                    {{ $rentalOffer->created_at->diffForHumans(['short' => true]) }}
                                </x-text>
                            </div>

                            <div class="flex items-center gap-1">

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

                            <div class="mt-2">
                                <x-text>
                                {{ $rentalOffer->message }}
                                </x-text>
                            </div>

                        </div>
                    </div>
                    <div>
                        <div class="flex flex-col items-center gap-2">
                            <x-button wire:click="showConfirmModal({{ $rentalOffer->id }})" variant="primary" color="green">Accepts</x-button>
                            <x-button wire:click="showDeclineModal({{ $rentalOffer->id }})" variant="primary" color="red">Decline</x-button>
                        </div>
                        <div class="mt-4">
                            <x-text wire:click="showViewMoreModal({{ $rentalOffer->id }})" class="cursor-pointer hover:underline hover:text-gray-800 transition">View more</x-text>
                        </div>
                    </div>
                </div>
            </x-card>
        @empty
            No record found
        @endforelse
    @endif

    <flux:modal wire:model="is_show_decline_modal" class="min-w-96">
        @if ($this->interested_user)
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">Decline this interested_user?</flux:heading>
                    <flux:text class="mt-2">
                        You're about to decline this interested_user.<br>
                        This will be remove from the list.
                    </flux:text>
                </div>
                <div class="flex gap-2">
                    <flux:spacer />
                    <flux:modal.close>
                        <flux:button variant="ghost">Cancel</flux:button>
                    </flux:modal.close>
                    <flux:button wire:click="declineThisinterested_user({{ $this->interested_user->id }})" variant="danger">Decline</flux:button>
                </div>
            </div>
        @endif
    </flux:modal>

    <flux:modal wire:model.live="is_show_confirm_modal" class="min-w-96" name="confirm">
        @if ($this->interested_user)

            <livewire:pages::partial.create_rental_transaction
                :interested_user="$this->interested_user"            
                :key="'create-' . $this->interested_user->id"
            />
        @endif
    </flux:modal>

    <flux:modal wire:model="is_show_view_more_modal" class="min-w-196">
        @if ($this->post_interest_info)
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">Operator's details</flux:heading>
                </div>
                <div class="flex-1 flex items-center gap-2">
                    <flux:avatar size="xs" name="{{ $this->post_interest_info->user->name }}" color="emerald"/>
                    <div class="flex flex-col">
                        <x-text class="text-sm font-medium">{{ $this->post_interest_info->user->name }}</x-text>
                    </div>
                </div>

                <div class="mt-2 flex items-center">   
                    <div class="flex-1 flex items-center gap-1.5 text-zinc-500 dark:text-zinc-400">
                        <flux:icon.map-pin class="w-4 h-4" />
                        <x-text class="text-inherit">Address</x-text>
                    </div>
                    <x-text variant="strong">{{ $this->post_interest_info->user->address }}</x-text>
                </div>

                <div class="mt-2 flex items-center">   
                    <div class="flex-1 flex items-center gap-1.5 text-zinc-500 dark:text-zinc-400">
                        <flux:icon.phone class="w-4 h-4" />
                        <x-text class="text-inherit">Phone no.</x-text>
                    </div>
                    <x-text variant="strong">{{ $this->post_interest_info->user->phone_number }}</x-text>
                </div>

                <div>
                    @if (!empty($post_interest_info->metadata['vehicle_images']))

                        <x-text>Vehicle images</x-text>

                        @php
                            $urls = array_map(fn($path) => Storage::url($path), $post_interest_info->metadata['vehicle_images']);
                        @endphp
                        
                        <div x-data="{ open: false, index: 0, images: @js($urls) }" class="mt-3">
                            <div class="grid grid-cols-3 gap-1.5 auto-rows-[110px]">
                                @foreach ($urls as $i => $url)
                                    @if ($i === 0)
                                        <button type="button" @click="open = true; index = {{ $i }}" class="col-span-2 row-span-2 relative rounded-lg overflow-hidden bg-zinc-100 dark:bg-zinc-800 group cursor-pointer">
                                            <img src="{{ $url }}" alt="Vehicle attachment image" class="w-full h-full object-cover" loading="lazy" />
                                            <div class="absolute inset-0 bg-black/0 group-hover:bg-black/25 transition-colors flex items-center justify-center">
                                                <flux:icon.magnifying-glass-plus class="size-5 text-white opacity-0 group-hover:opacity-100 transition-opacity" />
                                            </div>
                                        </button>
                                    @elseif ($i < 3)
                                        <button type="button" @click="open = true; index = {{ $i }}" class="relative rounded-lg overflow-hidden bg-zinc-100 dark:bg-zinc-800 group cursor-pointer">
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
                                <div @click.outside="open = false" class="bg-white dark:bg-zinc-900 rounded-xl overflow-hidden max-w-lg w-full">
                                    <div class="flex items-center justify-between px-4 py-2.5 border-b border-zinc-200 dark:border-zinc-700">
                                        <span class="text-sm text-zinc-500" x-text="(index + 1) + ' / ' + images.length"></span>
                                        <button @click="open = false" class="text-zinc-500 hover:text-zinc-900 dark:hover:text-white cursor-pointer">
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
    </flux:modal>
</div>
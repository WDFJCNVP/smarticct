<?php

use Livewire\Component;
use Livewire\Attributes\Computed;

use App\Models\Post;
use App\Models\RentTransaction;
use App\Models\PostInterest;

new class extends Component
{
    public ?Post $post = null;

    public bool $is_show_confirm_modal = false;
    public bool $is_show_decline_modal = false;
    public $client = null;
    public bool $is_ongoing = false;
    public ?int $post_interest_id = null;

    public bool $is_show_view_more_modal = false;
    public $post_interest_info;

    #[Computed]
    public function getPostInterest() {
        return PostInterest::where('post_id', $this->post->id)->whereIn('status', ['pending', 'cancel'])->get();
    }

    public function showViewMoreModal($id) {
        $this->is_show_view_more_modal = false;
        $this->is_show_view_more_modal = true;

        $this->post_interest_info = PostInterest::where('id', $id)->first();
    }

    public function declineThisClient($id) {

        PostInterest::where('id', $id)->update(['status' => 'decline']);

    }

    public function mount() {
        $rent_transaction_record = RentTransaction::where('status', 'ongoing')->get();

        foreach($rent_transaction_record as $record) {
            if ($record->operator_id === $this->post->user_id) {
                $this->is_ongoing = true;

                $this->post_interest_id = $record->post_interest_id;
            }
        }
    }

    public function showDeclineModal($id) {

        $this->is_show_confirm_modal = false;
        $this->is_show_decline_modal = false;
        $this->is_show_decline_modal = true;
        $this->client = null;
        $this->client = $this->post->postInterest->where('id', $id)->first();

        // dd($this->client);

    }

    public function showConfirmModal($id) {

        $this->is_show_confirm_modal = false;
        $this->is_show_confirm_modal = true;
        $this->client = null;
        $this->client = $this->post->postInterest->where('id', $id)->first();

    }

    public function viewMore() {
        dd('hiiii');
    }
};
?>

<div>
    @if ($this->is_ongoing)
        <flux:callout 
            variant="warning" 
            icon="exclamation-circle" 
            heading="This vehicle already has an active rental. New requests stay pending until the current transaction ends." 
        />

        @forelse ($this->getPostInterest as $post)

            @if ($this->post_interest_id === $post->id)
                @continue
            @endif

            <x-card class="my-2" variant="subtle" disabled>
                <div class="flex items-start gap-2">
                    <div class="flex-1 flex items-start gap-2">
                        <div>
                            <x-avatar name="{{ $post->user->name }}" color="lime"/>
                        </div>
                        <div class="flex flex-col gap-1 items-start">
                            <div>
                                <x-text variant="strong">{{ $post->user->name }}</x-text>
                                <x-text size="sm" variant="subtle">Requested {{ $post->created_at->diffForHumans(['short' => true]) }}</x-text>
                            </div>

                            <div class="flex items-center gap-1">
                                <x-badge color="green" icon="arrows-right-left">{{ str($post->trip_type)->headline() }}</x-badge>
                                <x-badge color="green" icon="calendar-days">{{ $post->trip_date->format('D, M j Y') }}</x-badge>
                                <x-badge color="green" icon="users">{{ $post->body_count }} passengers</x-badge>
                            </div>

                            <div class="mt-2">
                                <x-text>
                                {{ $post->purpose }}
                                </x-text>
                            </div>

                        </div>
                    </div>
                    <div>
                        <div class="flex flex-col items-center gap-2">
                            <x-button wire:click="showConfirmModal({{ $post->id }})" variant="primary" color="green" disabled>Accepts</x-button>
                            <x-button wire:click="showDeclineModal({{ $post->id }})" variant="primary" color="red" disabled>Decline</x-button>
                        </div>
                        <div class="mt-4">
                            <x-text wire:click="viewMore" class="cursor-pointer hover:underline hover:text-gray-800 transition">View more</x-text>
                        </div>
                    </div>
                </div>
            </x-card>
        @empty
            <x-text>No interested commuters yet.</x-text>
        @endforelse
    @else

        @forelse ($this->getPostInterest as $post)

            @if ($this->post_interest_id === $post->id)
                @continue
            @endif

            <x-card class="my-2" variant="subtle" disabled>
                @if ($post->status === 'cancel')
                    <flux:badge color="orange" size="sm" class="mb-2">Cancelled</flux:badge>
                @endif

                <div class="flex items-start gap-2">
                    <div class="flex-1 flex items-start gap-2">
                        <div>
                            <x-avatar name="{{ $post->user->name }}" color="lime"/>
                        </div>
                        <div class="flex flex-col gap-1 items-start">
                            <div>
                                <x-text variant="strong">{{ $post->user->name }}</x-text>
                                <x-text size="sm" variant="subtle">Requested {{ $post->created_at->diffForHumans(['short' => true]) }}</x-text>
                            </div>

                            <div class="flex items-center gap-1">
                                <x-badge color="green" icon="arrows-right-left">{{ str($post->trip_type)->headline() }}</x-badge>
                                <x-badge color="green" icon="calendar-days">{{ $post->trip_date->format('D, M j Y') }}</x-badge>
                                <x-badge color="green" icon="users">{{ $post->body_count }} passengers</x-badge>
                            </div>

                            <div class="mt-2">
                                <x-text>
                                {{ $post->purpose }}
                                </x-text>
                            </div>

                        </div>
                    </div>
                    <div>
                        <div class="flex flex-col items-center gap-2">
                            <x-button wire:click="showConfirmModal({{ $post->id }})" variant="primary" color="green">Accept</x-button>
                            <x-button wire:click="showDeclineModal({{ $post->id }})" variant="primary" color="red">Decline</x-button>
                        </div>
                        <div class="mt-4">
                            <x-text wire:click="showViewMoreModal({{ $post->id }})" class="cursor-pointer hover:underline hover:text-gray-800 transition">View more</x-text>
                        </div>
                    </div>
                </div>
            </x-card>
        @empty
            <x-text>No interested commuters yet.</x-text>
        @endforelse
    @endif

    <flux:modal wire:model="is_show_confirm_modal" class="min-w-96">
        @if ($this->client)
            <livewire:pages::partial.create_rental_transaction
                :client="$this->client"            
                :key="'create-' . $this->client->id"
            />
        @endif
    </flux:modal>

    <flux:modal wire:model="is_show_decline_modal" class="min-w-96">
        @if ($this->client)
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">Decline this client?</flux:heading>
                    <flux:text class="mt-2">
                        You're about to decline this client.<br>
                        This will be remove from the list.
                    </flux:text>
                </div>
                <div class="flex gap-2">
                    <flux:spacer />
                    <flux:modal.close>
                        <flux:button variant="ghost">Cancel</flux:button>
                    </flux:modal.close>
                    <flux:button wire:click="declineThisClient({{ $this->client->id }})" variant="danger">Decline</flux:button>
                </div>
            </div>
        @endif
    </flux:modal>

    <flux:modal wire:model="is_show_view_more_modal" class="min-w-196">
        @if ($this->post_interest_info)
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">Client details</flux:heading>
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

                    @php
                        $url = Storage::url($this->post_interest_info->metadata['valid_ids']['user_valid_id']);
                    @endphp

                    <flux:label class="mb-4">Client's valid id</flux:label>
                    <img src="{{ $url }}" alt="">
                </div>

                <div>
                    @php
                        $hasDriver = $this->post_interest_info->metadata['valid_ids']['driver_valid_id'] ?? null;
                    @endphp

                    @if ($hasDriver)

                        <x-text variant="strong">Driver details</x-text>

                        <div class="mt-4 flex items-center">   
                            <div class="flex-1 flex items-center gap-1.5 text-zinc-500 dark:text-zinc-400">
                                <flux:icon.user class="w-4 h-4" />
                                <x-text class="text-inherit">Name</x-text>
                            </div>
                            <x-text variant="strong">{{ $this->post_interest_info->metadata['driver_name'] }}</x-text>
                        </div>

                        <div class="mt-4 flex items-center">   
                            <div class="flex-1 flex items-center gap-1.5 text-zinc-500 dark:text-zinc-400">
                                <flux:icon.identification class="w-4 h-4" />
                                <x-text class="text-inherit">Age</x-text>
                            </div>
                            <x-text variant="strong">{{ $this->post_interest_info->metadata['driver_age'] }}</x-text>
                        </div>

                        <div class="mt-4 flex items-center">   
                            <div class="flex-1 flex items-center gap-1.5 text-zinc-500 dark:text-zinc-400">
                                <flux:icon.map-pin class="w-4 h-4" />
                                <x-text class="text-inherit">Home address</x-text>
                            </div>
                            <x-text variant="strong">{{ $this->post_interest_info->metadata['driver_home_address'] }}</x-text>
                        </div>

                        <div class="mt-4 flex items-center">   
                            <div class="flex-1 flex items-center gap-1.5 text-zinc-500 dark:text-zinc-400">
                                <flux:icon.phone class="w-4 h-4" />
                                <x-text class="text-inherit">Phone no.</x-text>
                            </div>
                            <x-text variant="strong">{{ $this->post_interest_info->metadata['driver_contact_number'] }}</x-text>
                        </div>

                        <div>

                            @php
                                $url = Storage::url($this->post_interest_info->metadata['valid_ids']['driver_valid_id']);
                            @endphp

                            <flux:label class="my-4">Driver's valid id</flux:label>
                            <img src="{{ $url }}" alt="">
                        </div>

                    @else

                    <flux:badge color="orange" size="sm"> Client has no driver. </flux:badge>

                    @endif
                </div>
             </div>
        @endif
    </flux:modal>

</div> 
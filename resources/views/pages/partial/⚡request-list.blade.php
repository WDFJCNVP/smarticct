<?php

use Livewire\Component;

use App\Models\Post;
new class extends Component
{
    public Post $post;

    public bool $is_show_confirm_modal = false;
    public $client = null;

    public function showConfirmModal($id) {

        $this->is_show_confirm_modal = false;
        $this->is_show_confirm_modal = true;
        $this->client = null;
        $this->client = $this->post->postInterest->where('id', $id)->first();

    }

    // public function viewMore() {
    //     dd('hiiii');
    // }
};
?>

<div>
    @forelse ($this->post->postInterest as $post)
        <x-card class="mb-2" variant="subtle">
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
                        <x-button variant="primary" color="red">Decline</x-button>
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

    {{-- Modals --}}
    
    <flux:modal wire:model="is_show_confirm_modal" class="min-w-96">
        @if ($this->client)
            <livewire:pages::partial.create_rental_transaction
                :client="$this->client"            
                :key="'create-' . $this->client->id"
            />
        @endif
    </flux:modal>

</div> 
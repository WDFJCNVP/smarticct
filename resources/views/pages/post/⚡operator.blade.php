<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;

use App\Models\Post;

new  #[Layout('layouts.operator-layout')] class extends Component
{
    public Post $post;
    public $post_interest_count;
};
?>

<div>
    <x-pages-heading heading="My Post" />
    <x-card>
        <div class="flex items-start gap-2">
            <x-avatar name="{{ $this->post->user->name }}" />
            <div class="flex flex-col gap-1 items-start">
                <div class="flex items-center gap-1">
                    <x-text variant="strong">{{ $this->post->user->name }}</x-text>
                    &middot;
                    <x-text size="sm" variant="subtle">{{ $this->post->created_at->diffForHumans(['short' => true]) }}</x-text>
                </div>
                <div class="flex items-center gap-1">

                    @if ($this->post->status === 'published' && $this->post->type === 'rental')
                        <x-badge size="sm" color="green">Available to rent</x-badge>
                         <x-badge size="sm" color="blue">{{ $this->post->metadata['vehicle_type'] }}</x-badge>
                    @endif
                   
                </div>
                <div>
                    <x-text variant="strong">{{ $post->body }}</x-text>
                </div>
            </div>
        </div>

        <x-separator />

        <div class="flex items-center gap-2">
            <flux:icon.users class="size-4" />
            <x-text>{{ $this->post_interest_count }} interested</x-text>
        </div>

    </x-card>

    <div x-data="{ tab: 'requests' }" class="space-y-6 mt-6">
        <div class="flex gap-6 border-b border-zinc-200 dark:border-zinc-700 text-sm">
            <button
                type="button"
                @click="tab = 'requests'"
                :class="tab === 'requests'
                    ? 'border-indigo-600 text-indigo-700 dark:text-indigo-400 font-medium'
                    : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300'"
                class="pb-3 border-b-2 transition-colors flex items-center gap-1.5"
            >
                Interested commuters
            </button>

            <button
                type="button"
                @click="tab = 'active'"
                :class="tab === 'active'
                    ? 'border-indigo-600 text-indigo-700 dark:text-indigo-400 font-medium'
                    : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300'"
                class="pb-3 border-b-2 transition-colors"
            >
                Active transaction
            </button>

            <button
                type="button"
                @click="tab = 'history'"
                :class="tab === 'history'
                    ? 'border-indigo-600 text-indigo-700 dark:text-indigo-400 font-medium'
                    : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300'"
                class="pb-3 border-b-2 transition-colors"
            >
                History
            </button>
        </div>

        <div x-show="tab === 'requests'" x-cloak class="space-y-3">

            <livewire:pages::partial.request-list :post="$this->post" :key="'request-list-' . $this->post->id" />

        </div>

        <div x-show="tab === 'active'" x-cloak class="space-y-4">

            <x-card>
                <div class="flex items-center">
                    <div class="flex-1">
                        <x-text size="sm">Client's name</x-text>
                        <x-text variant="strong" size="xl">Lexos Dacleson</x-text>
                        <x-text variant="strong" size="lg" color="blue">09463637401</x-text>
                    </div>
                    <div>
                        <x-badge color="orange">On going</x-badge>
                    </div>
                </div>
                <div class="mt-6 grid grid-cols-2 gap-4">
                    <div>
                        <div>
                            <x-text size="sm">Trip date:</x-text>
                            <x-text size="lg" variant="strong">Mon, Jan 1st 2024</x-text>
                        </div>
                        <div class="my-4">
                            <x-badge size="sm" color="emerald" icon="arrows-right-left">Round trip</x-badge>
                        </div>
                        <div class="mt-2">
                            <x-text size="sm">Pick-up location:</x-text>
                            <x-text size="lg" variant="strong">Nabua</x-text>
                        </div>
                        <div class="mt-2">
                            <x-text size="sm">Return location:</x-text>
                            <x-text size="lg" variant="strong">Nabua</x-text>
                        </div>
                    </div>
                    <div>
                        <div class="mt-2">
                            <x-text size="sm">Total passenger/s:</x-text>
                            <x-text size="lg" variant="strong">18</x-text>
                        </div>
                        <div class="mt-2">
                            <x-text size="sm">Destination:</x-text>
                            <x-text size="lg" variant="strong">Legaspi</x-text>
                        </div>
                    </div>
                </div>

                <x-separator />

                <div class="flex items-center gap-4">
                    <x-button variant="primary" color="green">Mark as Completed</x-button>
                    <x-button variant="primary" color="red">Cancel transaction</x-button>
                </div>

            </x-card>

        </div>

        <div x-show="tab === 'history'" x-cloak>
            {{-- @include('livewire.transactions.partials.history-table', ['history' => $post->completedTransactions]) --}}
        </div>

    </div>
</div>
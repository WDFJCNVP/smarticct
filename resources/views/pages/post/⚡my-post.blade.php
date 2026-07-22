<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;

use App\Models\Post;

new class extends Component
{
    public Post $post;
    public $count;

    public function render() {
        $role = auth()->user()->role;

        return $this->view()->layout('layouts.' . $role . '-layout');
    }
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
            
            @if (auth()->user()->role === 'operator')

                <x-text>{{ $this->count }} interested</x-text>

            @elseif(auth()->user()->role === 'commuter')

                <x-text>{{ $this->count }} interested</x-text>

            @endif
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

            @if (auth()->user()->role === 'operator')

                <livewire:pages::partial.request-list :post="$this->post" :key="'request-list-' . $this->post->id" />

            @elseif(auth()->user()->role === 'commuter')

                <livewire:pages::partial.commuter-request-list :post="$this->post" :key="'request-list-' . $this->post->id" />

            @endif

        </div>

        <div x-show="tab === 'active'" x-cloak class="space-y-4">

            @if (auth()->user()->role === 'operator')

                <livewire:pages::partial.active-transaction :post="$this->post" :key="'active-transaction-' . $this->post->id" />

            @elseif(auth()->user()->role === 'commuter')

                <livewire:pages::partial.commuter-active-transaction :post="$this->post" :key="'active-transaction-' . $this->post->id" />

            @endif
        </div>

        <div x-show="tab === 'history'" x-cloak>

            @if (auth()->user()->role === 'operator')

               <livewire:pages::partial.transaction-history />

            @elseif(auth()->user()->role === 'commuter')

                <livewire:pages::partial.commuter-transaction-history />

            @endif
        </div>

    </div>
</div>
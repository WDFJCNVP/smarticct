<?php

namespace App\Livewire\Pages;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Layout;

use App\Models\Post;
use App\Models\TripRequest;
use App\Models\RentalOffer;

new class extends Component
{
    public Post $post;

    #[Computed]
    public function count() {
        $role = auth()->user()->role;

        if ($role === 'operator') {
            // Operator's post receives TripRequests from commuters
            return TripRequest::where('post_id', $this->post->id)
                ->whereIn('status', ['pending', 'cancel'])
                ->count();
        }

        // Commuter's post receives RentalOffers from operators
        return RentalOffer::where('post_id', $this->post->id)
            ->whereIn('status', ['pending', 'cancel'])
            ->count();
    }

    #[On('transaction-updated')]
    #[On('interested-list-updated')]
    public function refreshMyPost() {
        unset($this->count);
    }

    public function render() {
        $role = auth()->user()->role;
        return $this->view()->layout('layouts.' . $role . '-layout');
    }
};
?>

<div>
    <div class="flex flex-wrap items-start justify-between gap-2 sm:gap-3 mb-4 sm:mb-6">
        <div>
            <x-heading
                size="xl"
                class="!font-primary !font-bold !text-light-txt-primary dark:!text-dark-txt-primary"
                style="font-size: var(--text-page-title)"
            >
                My Post
            </x-heading>
            <x-text variant="subtle" class="!font-secondary mt-1 block" style="font-size: var(--text-helper)">
                View and manage your post.
            </x-text>
        </div>

        <div class="flex items-center gap-1 sm:gap-2 flex-wrap mt-1 sm:mt-0">
            <x-button
                href="{{ route('post.my-posts') }}"
                wire:navigate
                variant="ghost"
                icon="arrow-left"
                class="!font-secondary text-sm sm:text-base !px-2 sm:!px-3 !py-1 sm:!py-2"
            >
                <span class="hidden sm:inline">Back to My Posts</span>
                <span class="sm:hidden">Back</span>
            </x-button>
        </div>
    </div>

    <x-card
        class="!rounded-xl !border !border-light-bd-default dark:!border-dark-bd-default !bg-light-secondary dark:!bg-dark-secondary !shadow-sm"
    >
        <div class="flex items-start gap-3">
            <x-avatar name="{{ $this->post->user->name }}" />

            <div class="flex-1 min-w-0">
                <div class="flex flex-wrap items-center gap-1.5">
                    <x-text variant="strong" class="text-light-txt-primary dark:text-dark-txt-primary">
                        {{ $this->post->user->name }}
                    </x-text>
                    <span class="text-light-txt-muted dark:text-dark-txt-muted">·</span>
                    <x-text size="sm" variant="subtle">
                        {{ $this->post->created_at->diffForHumans(['short' => true]) }}
                    </x-text>
                </div>

                <div class="flex flex-wrap items-center gap-1.5 mt-1.5">
                    @if ($this->post->status === 'published' && $this->post->type === 'rental')
                        <flux:badge size="sm" color="green">Available to rent</flux:badge>
                        @if (!empty($this->post->metadata['vehicle_type']))
                            <flux:badge size="sm" color="blue">{{ $this->post->metadata['vehicle_type'] }}</flux:badge>
                        @endif
                    @endif
                </div>

                <div class="mt-2">
                    <x-text variant="strong" class="block text-light-txt-primary dark:text-dark-txt-primary">
                        {{ $post->body }}
                    </x-text>
                </div>
            </div>
        </div>

        <div class="mt-4 pt-4 border-t border-light-bd-default dark:border-dark-bd-default">
            <div class="flex items-center gap-2">
                <flux:icon.users class="size-4 text-light-txt-muted dark:text-dark-txt-muted" />
                <x-text class="text-light-txt-body dark:text-dark-txt-body">
                    {{ $this->count }} interested
                </x-text>
            </div>
        </div>
    </x-card>

    <div x-data="{ tab: 'requests' }" class="mt-6">
        <div class="flex flex-wrap gap-1 bg-light-subtle dark:bg-dark-subtle rounded-full p-1 w-fit">
            <button
                type="button"
                @click="tab = 'requests'"
                :class="tab === 'requests'
                    ? 'bg-white dark:bg-dark-secondary shadow-sm text-light-txt-primary dark:text-dark-txt-primary'
                    : 'text-light-txt-muted dark:text-dark-txt-muted hover:text-light-txt-body dark:hover:text-dark-txt-body'"
                class="px-3 py-1.5 text-sm rounded-full transition-colors whitespace-nowrap"
            >
                Interested {{ auth()->user()->role === 'operator' ? 'commuters' : 'operators' }}
            </button>

            <button
                type="button"
                @click="tab = 'active'"
                :class="tab === 'active'
                    ? 'bg-white dark:bg-dark-secondary shadow-sm text-light-txt-primary dark:text-dark-txt-primary'
                    : 'text-light-txt-muted dark:text-dark-txt-muted hover:text-light-txt-body dark:hover:text-dark-txt-body'"
                class="px-3 py-1.5 text-sm rounded-full transition-colors whitespace-nowrap"
            >
                Active transaction
            </button>

            <button
                type="button"
                @click="tab = 'history'"
                :class="tab === 'history'
                    ? 'bg-white dark:bg-dark-secondary shadow-sm text-light-txt-primary dark:text-dark-txt-primary'
                    : 'text-light-txt-muted dark:text-dark-txt-muted hover:text-light-txt-body dark:hover:text-dark-txt-body'"
                class="px-3 py-1.5 text-sm rounded-full transition-colors whitespace-nowrap"
            >
                Transaction history
            </button>
        </div>

        <div class="mt-4">
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

            <div x-show="tab === 'history'" x-cloak class="space-y-4">
                @if (auth()->user()->role === 'operator')
                    <livewire:pages::partial.transaction-history :key="'transaction-history-' . $this->post->id" />
                @elseif(auth()->user()->role === 'commuter')
                    <livewire:pages::partial.commuter-transaction-history :key="'transaction-history-' . $this->post->id" />
                @endif
            </div>
        </div>
    </div>
</div>
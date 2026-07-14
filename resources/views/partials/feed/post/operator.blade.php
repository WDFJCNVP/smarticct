<x-layouts::commuter-layout>
    <div>
        <x-pages-heading heading="My Post" />
        <x-card>
            <div class="flex items-start gap-2">
                <x-avatar name="{{ $post->user->name }}" />
                <div class="flex flex-col gap-1 items-start">
                    <div class="flex items-center gap-1">
                        <x-text variant="strong">{{ $post->user->name }}</x-text>
                        &middot;
                        <x-text>7m ago</x-text>
                    </div>
                    <div class="flex items-center gap-1">
                        <x-badge size="sm" color="green">Available to rent</x-badge>
                        <x-badge size="sm" color="blue">UV express</x-badge>
                    </div>
                    <div>
                        <x-text variant="strong">Offering my vehicle for renting. Please call me to this number: 123-456-7890 for further info</x-text>
                    </div>
                </div>
            </div>

            <x-separator />

            <div class="flex items-center gap-2">
                <flux:icon.users class="size-4" />
                <x-text>3 interested</x-text>
            </div>

        </x-card>

        <div x-data="{ tab: 'requests' }" class="space-y-6 mt-6">

            {{-- Tab bar --}}
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
                    {{-- @if($post->interestedRequests->count())
                        <flux:badge size="sm" color="indigo">{{ $post->interestedRequests->count() }}</flux:badge>
                    @endif --}}
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

                <livewire:pages::partial.request-list :post="$post" :key="'request-list-' . $post->id" />

            </div>

            <div x-show="tab === 'active'" x-cloak class="space-y-4">
                {{-- @if($post->activeTransaction)
                    @include('livewire.transactions.partials.active-transaction', ['transaction' => $post->activeTransaction])
                @else
                    <div class="text-center py-16 text-zinc-400 text-sm">
                        <flux:icon name="document-text" class="w-8 h-8 mx-auto mb-2" />
                        No active transaction yet. Accept a request to start one.
                    </div>
                @endif --}}
            </div>

            <div x-show="tab === 'history'" x-cloak>
                {{-- @include('livewire.transactions.partials.history-table', ['history' => $post->completedTransactions]) --}}
            </div>

        </div>
    </div>
</x-layouts::commuter-layout>

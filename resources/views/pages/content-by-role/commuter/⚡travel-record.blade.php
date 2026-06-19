<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;

use App\Models\TravelRecord;
use App\Models\CardTransaction;

new #[Layout('layouts.commuter-layout')] class extends Component
{
    #[Computed]
    public function getTravelRecords()
    {
        return TravelRecord::with('queue', 'user.card')
            ->where('user_id', auth()->id())
            ->latest()
            ->paginate(10);
    }

    #[Computed]
    public function stats()
    {
        $base = $this->getTravelRecords;

        return [
            'total' => $base->total(),
        ];
    }

    public function fareForRecord($card)
    {   

        if (!$card) {
            return null;
        }

        return CardTransaction::where('card_id', $card->id)
            ->where('transaction_type', 'fare_payment')
            ->first();
    }

    // public function mount () {
    //    dd($this->getTravelRecords);
    // }
};
?>

<div>
    <div class="flex items-start justify-between gap-4 flex-wrap">
        <x-pages-heading heading="Travel Record" description="You can monitor your travel history here." />
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-6">
        <div class="rounded-lg bg-zinc-100 dark:bg-zinc-800 p-4">
            <div class="text-sm text-zinc-500 dark:text-zinc-400">Total trips</div>
            <div class="text-2xl font-medium mt-1">{{ $this->stats['total'] }}</div>
        </div>
    </div>

    <div class="mt-6">
        <x-table>
            <x-table-columns>
                <x-table-column>Destination</x-table-column>
                <x-table-column>Vehicle Type</x-table-column>
                <x-table-column>Plate No.</x-table-column>
                <x-table-column>Fare Paid</x-table-column>
                <x-table-column>Boarded</x-table-column>
                <x-table-column>Departed</x-table-column>
                <x-table-column>Status</x-table-column>
            </x-table-columns>

            <x-table-rows>
                @forelse ($this->getTravelRecords as $record)
                    @php
                        $fare = $this->fareForRecord($record->user->card);
                    @endphp

                    <x-table-row>

                        <x-table-cell class="font-medium">
                            {{ $record->queue->destination ?? '—' }}
                        </x-table-cell>

                        <x-table-cell>
                            <flux:badge size="sm" color="{{ $record->queue->vehicle_type === 'Bus' ? 'blue' : 'amber' }}">
                                {{ $record->queue->vehicle_type }}
                            </flux:badge>
                        </x-table-cell>

                        <x-table-cell>{{ $record->queue->plate_number ?? '—' }}</x-table-cell>

                        <x-table-cell class="text-zinc-700 dark:text-zinc-300">
                            {{ $fare ? '₱' . number_format($fare->amount, 2) : '—' }}
                        </x-table-cell>

                        <x-table-cell class="text-zinc-500">
                            {{ $record->queue->time_queued?->format('M d, Y \a\t g:i a') ?? '—' }}
                        </x-table-cell>

                        <x-table-cell class="text-zinc-500">
                            {{ $record->queue->time_departed?->format('M d, Y \a\t g:i a') ?? '—' }}
                        </x-table-cell>

                        <x-table-cell>
                            @if ($record->queue->time_departed)
                                <flux:badge size="sm" color="green" icon="check">Completed</flux:badge>
                            @else
                                <flux:badge size="sm" color="amber" icon="clock">In Transit</flux:badge>
                            @endif
                        </x-table-cell>

                    </x-table-row>
                @empty
                    <x-table-row>
                        <x-table-cell colspan="7" class="text-center text-zinc-500 py-8">
                            No travel records yet.
                        </x-table-cell>
                    </x-table-row>
                @endforelse
            </x-table-rows>
        </x-table>
    </div>

    <div class="mt-10">
        {{ $this->getTravelRecords->links() }}
    </div>
</div>
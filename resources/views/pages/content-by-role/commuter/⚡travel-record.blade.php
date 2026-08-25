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


    // public function mount () {
    //    dd($this->getTravelRecords);
    // }
};
?>

<div>
    <div class="flex items-start justify-between gap-4 mb-6">
        <div>
            <x-heading
                size="xl"
                class="!font-primary !font-bold !text-light-txt-primary dark:!text-dark-txt-primary"
                style="font-size: var(--text-page-title)"
            >
                Travel Record
            </x-heading>
            <x-text variant="subtle" class="!font-secondary mt-1 block" style="font-size: var(--text-helper)">
                You can monitor your travel history here.
            </x-text>
        </div>
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 sm:gap-3 mb-6">
        <flux:card class="p-3 sm:p-4">
            <div class="flex items-center gap-1.5 sm:gap-2 mb-1.5">
                <div class="flex items-center justify-center w-6 h-6 sm:w-7 sm:h-7 rounded-lg bg-primary/10 dark:bg-primary/20 shrink-0">
                    <flux:icon.truck class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-primary dark:text-dark-txt-primary" />
                </div>
                <x-text class="font-secondary text-xs sm:text-stat-label text-light-txt-muted dark:text-dark-txt-muted">
                    Total trips
                </x-text>
            </div>
            <x-text class="font-primary text-stat-value font-bold text-light-txt-primary dark:text-dark-txt-primary block">
                {{ $this->stats['total'] }}
            </x-text>
        </flux:card>
    </div>

    <flux:card class="overflow-hidden p-0">
        <flux:table>
            <flux:table.columns sticky class="bg-white dark:bg-zinc-900">
                <flux:table.column align="center" class="px-1! sm:px-2! md:px-4! py-2">Destination</flux:table.column>
                <flux:table.column align="center" class="px-1 sm:px-2 md:px-4 py-2">Vehicle Type</flux:table.column>
                <flux:table.column align="center" class="px-1 sm:px-2 md:px-4 py-2">Plate No.</flux:table.column>
                <flux:table.column align="center" class="px-1 sm:px-2 md:px-4 py-2">Fare Paid</flux:table.column>
                <flux:table.column align="center" class="px-1 sm:px-2 md:px-4 py-2">Boarded</flux:table.column>
                <flux:table.column align="center" class="px-1 sm:px-2 md:px-4 py-2">Departed</flux:table.column>
                <flux:table.column align="center" class="px-1 sm:px-2 md:px-4 py-2">Status</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->getTravelRecords as $record)
                    <flux:table.row :key="$record->id">
                        <flux:table.cell align="center" class="px-1 sm:px-2 md:px-4 py-1.5 md:py-2 font-secondary text-xs md:text-table-row text-light-txt-body dark:text-dark-txt-body">
                            {{ $record->queue->destination ?? '—' }}
                        </flux:table.cell>

                        <flux:table.cell align="center" class="px-1 sm:px-2 md:px-4 py-1.5 md:py-2">
                            <flux:badge size="sm" color="{{ $record->queue->vehicle_type === 'Bus' ? 'blue' : 'amber' }}">
                                {{ $record->queue->vehicle_type }}
                            </flux:badge>
                        </flux:table.cell>

                        <flux:table.cell align="center" class="px-1 sm:px-2 md:px-4 py-1.5 md:py-2 font-secondary text-xs md:text-table-row text-light-txt-body dark:text-dark-txt-body">
                            {{ $record->queue->plate_number ?? '—' }}
                        </flux:table.cell>

                        <flux:table.cell align="center" class="px-1 sm:px-2 md:px-4 py-1.5 md:py-2 font-secondary text-xs md:text-table-row tabular-nums font-medium text-light-txt-primary dark:text-dark-txt-primary">
                            ₱{{ number_format($record->fare_amount, 2) ?? 0 }}
                        </flux:table.cell>

                        <flux:table.cell align="center" class="px-1 sm:px-2 md:px-4 py-1.5 md:py-2 font-secondary text-xs md:text-table-row text-light-txt-muted dark:text-dark-txt-muted tabular-nums">
                            {{ $record->queue->time_queued?->format('M d, Y \a\t g:i a') ?? '—' }}
                        </flux:table.cell>

                        <flux:table.cell align="center" class="px-1 sm:px-2 md:px-4 py-1.5 md:py-2 font-secondary text-xs md:text-table-row text-light-txt-muted dark:text-dark-txt-muted tabular-nums">
                            {{ $record->queue->time_departed?->format('M d, Y \a\t g:i a') ?? '—' }}
                        </flux:table.cell>

                        <flux:table.cell align="center" class="px-1 sm:px-2 md:px-4 py-1.5 md:py-2">
                            @if ($record->queue->time_departed)
                                <flux:badge size="sm" color="green" icon="check">Completed</flux:badge>
                            @else
                                <flux:badge size="sm" color="amber" icon="clock">In Transit</flux:badge>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7" class="text-center py-12">
                            <div class="flex flex-col items-center justify-center gap-2">
                                <flux:icon.document-text class="w-8 h-8 text-zinc-300" />
                                <p class="font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">No travel records yet.</p>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <div class="mt-4">
        {{ $this->getTravelRecords->links() }}
    </div>
</div>
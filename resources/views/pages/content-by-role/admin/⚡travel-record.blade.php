<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Livewire\WithPagination;

use App\Models\Queue;

new #[Layout('layouts.admin-layout')] class extends Component
{
    use WithPagination;

    public string $vehicleTypeFilter = '';
    public string $statusFilter = '';


    public function updatedVehicleTypeFilter()
    {
        $this->resetPage();
    }

    public function updatedStatusFilter()
    {
        $this->resetPage();
    }

    #[Computed]
    public function travelRecords()
    {
        return Queue::query()
            ->when($this->vehicleTypeFilter, fn ($q) => $q->where('vehicle_type', $this->vehicleTypeFilter))
            ->when($this->statusFilter === 'departed', fn ($q) => $q->whereNotNull('time_departed'))
            ->when($this->statusFilter === 'queued', fn ($q) => $q->whereNull('time_departed'))
            ->latest()
            ->paginate(10);
    }

    #[Computed]
    public function stats()
    {
        $base = Queue::query()
            ->when($this->vehicleTypeFilter, fn ($q) => $q->where('vehicle_type', $this->vehicleTypeFilter));

        return [
            'total' => $base->count(),
            'departed' => $base->clone()->whereNotNull('time_departed')->count(),
            'queued' => $base->clone()->whereNull('time_departed')->count(),
            'overbooked' => $base->clone()->whereColumn('seat_count', '>', 'seat_capacity')->count(),
        ];
    }

    #[Computed]
    public function vehicleTypes()
    {
        return Queue::query()->distinct()->pluck('vehicle_type');
    }
};
?>
<div>
    <div class="flex items-start justify-between gap-4 flex-wrap">
        <x-pages-heading heading="Travel Record" description="You can monitor travel records here." />

        <div class="flex gap-2">
            <flux:select wire:model.live="vehicleTypeFilter" placeholder="All vehicle types" class="w-40">
                <flux:select.option value="">All vehicle types</flux:select.option>
                @foreach ($this->vehicleTypes as $type)
                    <flux:select.option value="{{ $type }}">{{ $type }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="statusFilter" placeholder="All statuses" class="w-36">
                <flux:select.option value="">All statuses</flux:select.option>
                <flux:select.option value="departed">Departed</flux:select.option>
                <flux:select.option value="queued">Queued</flux:select.option>
            </flux:select>
        </div>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mt-6">
        <flux:card>
            <div class="text-sm text-zinc-500 dark:text-zinc-400">Total trips</div>
            <div class="text-2xl font-medium mt-1">{{ $this->stats['total'] }}</div>
        </flux:card>
        <flux:card>
            <div class="text-sm text-zinc-500 dark:text-zinc-400">Departed</div>
            <div class="text-2xl font-medium mt-1 text-green-600 dark:text-green-400">{{ $this->stats['departed'] }}</div>
        </flux:card>
        <flux:card>
            <div class="text-sm text-zinc-500 dark:text-zinc-400">Still queued</div>
            <div class="text-2xl font-medium mt-1 text-amber-600 dark:text-amber-400">{{ $this->stats['queued'] }}</div>
        </flux:card>
        {{-- <div class="rounded-lg bg-zinc-100 dark:bg-zinc-800 p-4">
            <div class="text-sm text-zinc-500 dark:text-zinc-400">Overbooked</div>
            <div class="text-2xl font-medium mt-1 {{ $this->stats['overbooked'] > 0 ? 'text-red-600 dark:text-red-400' : '' }}">
                {{ $this->stats['overbooked'] }}
            </div>
        </div> --}}
    </div>

    <div class="mt-6">
        <x-table>
            <x-table-columns>
                <x-table-column>Plate No.</x-table-column>
                <x-table-column>Driver Name</x-table-column>
                <x-table-column>Vehicle Type</x-table-column>
                <x-table-column>Occupancy</x-table-column>
                <x-table-column>Queued Date</x-table-column>
                <x-table-column>Departed Date</x-table-column>
                <x-table-column>Status</x-table-column>
            </x-table-columns>

            <x-table-rows>
                @forelse ($this->travelRecords as $record)
                    @php
                        $isOverbooked = $record->seat_count > $record->seat_capacity;
                        $occupancyPct = $record->seat_capacity > 0
                            ? min(100, round(($record->seat_count / $record->seat_capacity) * 100))
                            : 0;
                    @endphp

                    <x-table-row class="{{ $isOverbooked ? 'bg-red-50 dark:bg-red-950/30' : '' }}">
                        <x-table-cell class="font-medium">{{ $record->plate_number }}</x-table-cell>
                        <x-table-cell>{{ $record->driver_name }}</x-table-cell>
                        <x-table-cell>
                            <flux:badge size="sm" color="{{ $record->vehicle_type === 'Bus' ? 'blue' : 'amber' }}">
                                {{ $record->vehicle_type }}
                            </flux:badge>
                        </x-table-cell>
                        <x-table-cell>
                            {{ $record->seat_count }}/{{ $record->seat_capacity }}
                        </x-table-cell>
                        <x-table-cell class="text-zinc-500">
                            {{ $record->time_queued?->format('M d, Y \a\t g:i a') ?? '—' }}
                        </x-table-cell>
                        <x-table-cell class="text-zinc-500">
                            {{ $record->time_departed?->format('M d, Y \a\t g:i a') ?? '—' }}
                        </x-table-cell>
                        <x-table-cell>
                            @if ($record->time_departed)
                                <flux:badge size="sm" color="green" icon="check">Departed</flux:badge>
                            @else
                                <flux:badge size="sm" color="amber" icon="clock">Staging</flux:badge>
                            @endif
                        </x-table-cell>
                    </x-table-row>
                @empty
                    <x-table-row>
                        <x-table-cell colspan="7" class="text-center text-zinc-500 py-8">
                            No travel records match the current filters.
                        </x-table-cell>
                    </x-table-row>
                @endforelse
            </x-table-rows>
        </x-table>
    </div>

    <div class="mt-10">
        {{ $this->travelRecords->links() }}
    </div>
</div>


<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Livewire\WithPagination;

use App\Models\Queue;

new #[Layout('layouts.admin-layout')] class extends Component
{
    use WithPagination;

    #[Computed]
   public function getTravelRecords() {
     return Queue::latest()->paginate(10);
   }

//    public function mount() {
//    dd($this->getTravelRecords);

//    }

};
?>

<div>
   <x-pages-heading heading="Travel Record" description="You can monitor travel records here." />

   <x-table>
    <x-table-columns>
        <x-table-column>Plate No.</x-table-column>
        <x-table-column>Driver Name</x-table-column>
        <x-table-column>Vehicle Type</x-table-column>
        <x-table-column>Seat Capacity</x-table-column>
        <x-table-column>Seat Occupied</x-table-column>
        <x-table-column>Queued Date</x-table-column>
        <x-table-column>Departed Date</x-table-column>
        <x-table-column></x-table-column>
    </x-table-columns>

    <x-table-rows>

        @foreach ($this->getTravelRecords as $record)

            <x-table-row>
            <x-table-cell>{{ $record->plate_number }}</x-table-cell>
            <x-table-cell>{{ $record->driver_name }}</x-table-cell>
            <x-table-cell>{{ $record->vehicle_type }}</x-table-cell>
            <x-table-cell>{{ $record->seat_capacity }}</x-table-cell>
            <x-table-cell>{{ $record->seat_count }}</x-table-cell>
            <x-table-cell>{{ $record->time_queued->format('M, d, Y \a\t g:i a') ?? null }}</x-table-cell>
            <x-table-cell>{{ $record->time_departed?->format('M, d, Y \a\t g:i a') ?? 'not yet departed' }}</x-table-cell>
        </x-table-row>

        @endforeach

    </x-table-rows>
</x-table>

    <div class="mt-10">
        {{ $this->getTravelRecords->links() }}
    </div>
</div>


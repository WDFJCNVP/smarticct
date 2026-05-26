<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\WithPagination;
use App\Models\Queue;

new class extends Component
{

    use WithPagination;

    public $vehicle_type;
    public $plate_number;
    public $search = '';

    #[Computed]
    public function getQueuedRecords() {
       return Queue::query()
              ->when($this->vehicle_type, function($q) {
                $q->where('vehicle_type', $this->vehicle_type);
              })
              ->when($this->plate_number, function($q) {
                $q->where('plate_number', $this->plate_number);
              })
              ->when($this->search, function($q) {
                $q->where(function($q2) {
                    $q2->where('driver_name', 'like', '%' . $this->search . '%')
                       ->orWhere('status', 'like', '%' . $this->search . '%')
                       ->orWhere('plate_number', 'like', '%' . $this->search . '%')
                       ->orWhere('destination', 'like', '%' . $this->search . '%');
                });
              })
              ->latest()
              ->paginate(10);
    }

};
?>
<div>
    <div class="mt-6 flex items-center justify-between">
        <flux:heading class="flex items-center gap-1" size="lg"> All records
            <flux:text class="text-base" variant="subtle">
                {{ $this->getQueuedRecords->count()}}
            </flux:text>
        </flux:heading>    
        <div>
            <flux:input 
                icon="magnifying-glass" 
                placeholder="Search" 
                size="sm"
                wire:model.live.blur="search"
                />
        </div>
    </div>
    <flux:table container:class="max-h-100 mt-5">
        <flux:table.columns>
            <flux:table.column>No.</flux:table.column>
            <flux:table.column>Driver Name</flux:table.column>
            <flux:table.column>Vehicle Type</flux:table.column>
            <flux:table.column>Destination</flux:table.column>
            <flux:table.column>Plate No.</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            <flux:table.column>Seat Capacity</flux:table.column>
            <flux:table.column>Seat Occupied</flux:table.column>
            <flux:table.column>Time Queued</flux:table.column>
            <flux:table.column>Time Departed</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->getQueuedRecords as $index => $queue)
                <flux:table.row>
                    <flux:table.cell>{{$index + 1}}</flux:table.cell>
                    <flux:table.cell>{{ $queue->driver_name }}</flux:table.cell>
                    <flux:table.cell>{{ $queue->vehicle_type }}</flux:table.cell>
                    <flux:table.cell>{{ $queue->destination }}</flux:table.cell>
                    <flux:table.cell>{{ $queue->plate_number }}</flux:table.cell>
                    
                    @if ($queue->status === 'departed')
                        <flux:table.cell>
                            <flux:badge color="red" size="sm" inset="top bottom">departed</flux:badge>
                        </flux:table.cell>
                    @elseif ($queue->status === 'staging')
                        <flux:table.cell>
                            <flux:badge color="orange" size="sm" inset="top bottom">staging</flux:badge>
                        </flux:table.cell>
                    @else
                        <flux:table.cell>
                            <flux:badge color="green" size="sm" inset="top bottom">loading</flux:badge>
                        </flux:table.cell>
                    @endif
                    <flux:table.cell variant="strong">{{$queue->seat_capacity}}</flux:table.cell>
                    <flux:table.cell variant="strong">{{$queue->seat_count}}</flux:table.cell>
                    <flux:table.cell variant="strong">{{$queue->time_queued->format('M d, Y')}}</flux:table.cell>

                    @if (!$queue->time_departed)

                        <flux:table.cell variant="strong">...</flux:table.cell>

                    @else

                        <flux:table.cell variant="strong">{{$queue->time_departed->format('M d, Y')}}</flux:table.cell>

                    @endif
                    
                </flux:table.row>
            @empty
                
            @endforelse
        </flux:table.rows>
    </flux:table>
    <div class="mt-4">
        {{ $this->getQueuedRecords->links() }}
    </div>
</div>

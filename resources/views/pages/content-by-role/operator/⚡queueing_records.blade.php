<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;

use App\Models\Queue;
use App\Models\Vehicle;

new  #[Layout('layouts.operator-layout')] class extends Component
{
    use WithPagination;

    public $vehicle_type;
    public $plate_number;
    public $search = '';

    public Vehicle $vehicle;

    #[Computed]
    public function getQueuedRecords() {
        return Queue::query()
            ->where('card_id', auth()->user()->card->id) 
            ->when($this->search, function ($q) {
                $q->where(function ($q2) {
                    $q2->where('driver_name', 'like', '%' . $this->search . '%')
                    ->orWhere('status', 'like', '%' . $this->search . '%')
                    ->orWhere('plate_number', 'like', '%' . $this->search . '%')
                    ->orWhere('destination', 'like', '%' . $this->search . '%');
                });
            })
            ->latest()
            ->paginate(10);
    }

    #[Computed]
    public function vehicleStats(): array {
        $all = Queue::where('card_id', auth()->user()->card->id)->get();
        return [
            'total'      => $all->count(),
            'departed'   => $all->where('status', 'departed')->count(),
            'passengers' => $all->sum('seat_count'),
            'today'      => $all->filter(fn($queue) => \Carbon\Carbon::parse($queue->time_queued)->isToday())->count(),
        ];
    }

};
?>
<div>
    <flux:breadcrumbs>
        <flux:breadcrumbs.item href="{{ route('operator.vehicles') }}" wire:navigate>
            My vehicles
        </flux:breadcrumbs.item>
        <flux:breadcrumbs.item>Travel records</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    {{-- Page heading --}}
    <div class="mt-4 mb-4">
        <p class="text-xl font-medium text-zinc-800 dark:text-zinc-100">Travel records</p>
        <p class="text-sm text-zinc-400 mt-0.5">View all queuing and departure history for this vehicle.</p>
    </div>

    {{-- Vehicle info card --}}
    <div class="mb-4 flex overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900">
        <div class="w-1 bg-blue-500 flex-shrink-0"></div>
        <div class="flex flex-1 flex-wrap items-center justify-between gap-4 p-4">

            <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 items-center justify-center rounded-lg bg-blue-50 dark:bg-blue-950 text-blue-600 dark:text-blue-400">
                    <flux:icon.truck class="w-5 h-5" />
                </div>
                <div>
                    <p class="font-mono font-medium text-zinc-800 dark:text-zinc-100">
                        {{ $this->vehicle->plate_number }}
                    </p>
                    <p class="text-xs text-zinc-400">{{ $this->vehicle->vehicle_type }}</p>
                </div>
            </div>

            <div class="flex gap-8">
                {{-- <div>
                    <span class="block text-xs text-zinc-400 uppercase tracking-wider mb-1">Route</span>
                    <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">
                        {{ $this->vehicle->destination ?? '—' }}
                    </span>
                </div> --}}
                <div>
                    <span class="block text-xs text-zinc-400 uppercase tracking-wider mb-1">Seat capacity</span>
                    <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">
                        {{ $this->vehicle->total_seats }}
                    </span>
                </div>
                <div>
                    <span class="block text-xs text-zinc-400 uppercase tracking-wider mb-1">Operator</span>
                    <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">
                        {{ $this->vehicle->user->name }}
                    </span>
                </div>
            </div>

        </div>
    </div>

    {{-- Stat tiles --}}
    <div class="grid grid-cols-4 gap-3 mb-5">
        <div class="rounded-lg bg-zinc-50 dark:bg-zinc-800 p-4">
            <p class="text-xs text-zinc-400 mb-1">Total trips</p>
            <p class="text-2xl font-medium text-blue-700 dark:text-blue-400">
                {{ $this->vehicleStats['total'] }}
            </p>
        </div>
        <div class="rounded-lg bg-zinc-50 dark:bg-zinc-800 p-4">
            <p class="text-xs text-zinc-400 mb-1">Departed</p>
            <p class="text-2xl font-medium text-red-600 dark:text-red-400">
                {{ $this->vehicleStats['departed'] }}
            </p>
        </div>
        <div class="rounded-lg bg-zinc-50 dark:bg-zinc-800 p-4">
            <p class="text-xs text-zinc-400 mb-1">Total passengers</p>
            <p class="text-2xl font-medium text-green-700 dark:text-green-400">
                {{ $this->vehicleStats['passengers'] }}
            </p>
        </div>
        <div class="rounded-lg bg-zinc-50 dark:bg-zinc-800 p-4">
            <p class="text-xs text-zinc-400 mb-1">Trips today</p>
            <p class="text-2xl font-medium text-amber-700 dark:text-amber-400">
                {{ $this->vehicleStats['today'] }}
            </p>
        </div>
    </div>

    {{-- Toolbar --}}
    <div class="flex items-center gap-3 mb-4">
        <flux:input
            icon="magnifying-glass"
            placeholder="Search driver, status, plate…"
            size="sm"
            wire:model.live.debounce.300ms="search"
        />
    </div>

    {{-- Table --}}
    <flux:table container:class="max-h-160">
        <flux:table.columns sticky class="bg-white dark:bg-zinc-900">
            <flux:table.column>#</flux:table.column>
            <flux:table.column>Driver</flux:table.column>
            <flux:table.column>Type</flux:table.column>
            <flux:table.column>Destination</flux:table.column>
            <flux:table.column>Plate no.</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            <flux:table.column>Seats</flux:table.column>
            <flux:table.column>Time queued</flux:table.column>
            <flux:table.column>Time departed</flux:table.column>
            <flux:table.column>Duration</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->getQueuedRecords as $index => $queue)
                <flux:table.row :key="$queue->id">

                    <flux:table.cell class="text-zinc-400">
                        {{ $index + 1 }}
                    </flux:table.cell>

                    <flux:table.cell>
                        {{ $queue->driver_name ?? '—' }}
                    </flux:table.cell>

                    <flux:table.cell class="text-zinc-500">
                        {{ $queue->vehicle_type }}
                    </flux:table.cell>

                    <flux:table.cell class="text-zinc-500">
                        {{ $queue->destination }}
                    </flux:table.cell>

                    <flux:table.cell class="font-mono text-xs text-zinc-500">
                        {{ $queue->plate_number }}
                    </flux:table.cell>

                    <flux:table.cell>
                        @if ($queue->status === 'departed')
                            <flux:badge color="red" size="sm">Departed</flux:badge>
                        @elseif ($queue->status === 'staging')
                            <flux:badge color="orange" size="sm">Staging</flux:badge>
                        @else
                            <flux:badge color="green" size="sm">Loading</flux:badge>
                        @endif
                    </flux:table.cell>

                    {{-- Seats with occupancy bar --}}
                    <flux:table.cell>
                        <div class="flex items-center gap-2">
                            <span class="text-sm tabular-nums">
                                {{ $queue->seat_count }} / {{ $queue->seat_capacity }}
                            </span>
                            <div class="w-12 h-1.5 rounded-full bg-zinc-200 dark:bg-zinc-700 overflow-hidden">
                                <div
                                    class="h-full rounded-full {{ $queue->seat_count >= $queue->seat_capacity ? 'bg-green-500' : 'bg-blue-400' }}"
                                    style="width: {{ $queue->seat_capacity > 0 ? ($queue->seat_count / $queue->seat_capacity * 100) : 0 }}%"
                                ></div>
                            </div>
                        </div>
                    </flux:table.cell>

                    <flux:table.cell>
                        <p class="text-xs text-zinc-500">{{ $queue->time_queued->format('M d, Y') }}</p>
                        <p class="text-xs text-zinc-400">{{ $queue->time_queued->format('g:i A') }}</p>
                    </flux:table.cell>

                    <flux:table.cell>
                        @if ($queue->time_departed)
                            <p class="text-xs text-zinc-500">{{ $queue->time_departed->format('M d, Y') }}</p>
                            <p class="text-xs text-zinc-400">{{ $queue->time_departed->format('g:i A') }}</p>
                        @else
                            <span class="text-xs text-zinc-300">—</span>
                        @endif
                    </flux:table.cell>

                    {{-- Duration --}}
                    <flux:table.cell class="text-xs text-zinc-400">
                        @if ($queue->time_departed)
                            @php $mins = $queue->time_queued->diffInMinutes($queue->time_departed); @endphp
                            {{ $mins < 60 ? $mins . ' min' : floor($mins / 60) . 'h ' . ($mins % 60) . 'm' }}
                        @else
                            <span class="text-zinc-300">—</span>
                        @endif
                    </flux:table.cell>

                </flux:table.row>

            @empty
                <flux:table.row>
                    <flux:table.cell colspan="10">
                        <div class="flex flex-col items-center justify-center py-12 gap-2">
                            <flux:icon.archive-box class="w-8 h-8 text-zinc-300" />
                            <p class="text-sm text-zinc-400">No travel records found.</p>
                            @if ($search)
                                <p class="text-xs text-zinc-400">Try a different search term.</p>
                            @endif
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <div class="mt-4">
        {{ $this->getQueuedRecords->links() }}
    </div>
</div>

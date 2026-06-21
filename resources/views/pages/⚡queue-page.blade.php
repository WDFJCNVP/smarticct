<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Illuminate\Support\Carbon;
use App\Jobs\ProcessAfterDepart;
use App\Models\Queue;

new class extends Component {

    public string $search = '';
    public string $vehicleType = '';


    public function departEarly($queueId)
    {
        $queue = Queue::where('id', $queueId)
            ->where('user_id', auth()->id())
            ->where('status', 'loading')
            ->first();

        if (!$queue) {
            return;
        }

        $queue->update([
            'departs_at' => now()
        ]);

        ProcessAfterDepart::dispatch($queue->id);

        $this->refreshQueuedVehicleList();
    }

    #[Computed]
    public function getQueuedUserVehicle() {
        return Queue::where('user_id', auth()->user()->id)
            ->whereIn('status', ['staging', 'loading'])
            ->get();
    }

    public function getQueuePosition($queue)
    {
        $orderedIds = Queue::where('vehicle_type', $queue->vehicle_type)
            ->where('destination', $queue->destination)
            ->whereIn('status', ['staging', 'loading'])
            ->orderByRaw("FIELD(status, 'loading', 'staging')")
            ->orderBy('slot_position')
            ->orderBy('time_queued')
            ->pluck('id');

        $position = $orderedIds->search($queue->id);

        return $position !== false ? $position + 1 : 'N/A';
    }

    #[Computed]
    public function groupVehicles()
    {
        return Queue::whereIn('status', ['staging', 'loading'])
            ->when(
                $this->vehicleType,
                fn($q) => $q->where('vehicle_type', $this->vehicleType)
            )
            ->when(
                $this->search,
                fn($q) => $q->where(function($q2) {
                    $q2->where('destination', 'like', '%' . $this->search . '%')
                       ->orWhere('vehicle_type', 'like', '%' . $this->search . '%');
                })
            )
            
            ->orderByRaw("FIELD(status, 'loading', 'staging')")
            ->orderBy('slot_position')
            ->orderBy('time_queued')
            ->get()
            ->groupBy(['vehicle_type', 'destination']);
    }

    #[On('echo:vehicle-queue,.QueuedVehicleEvent')]
    public function refreshQueuedVehicleList() {
        unset($this->groupVehicles);
    }

    #[On('echo:trigger-depart-event,.TriggerDepartingEvent')]
    public function triggerDepartEvent($payload)
    {
        $queueId = $payload['vehicle']['id'] ?? $payload['id'] ?? null;
        if (!$queueId) return;  

        $queue = Queue::where('id', $queueId)->lockForUpdate()->first();
        if (!$queue) return;

        if (($queue->vehicle_type === 'Jeep' && ($queue->destination === 'Buhi' || $queue->destination === 'Mountain-unit')) && $queue->id === $queue->id) {
            $queue->update(['departs_at' => Carbon::now()]);
            ProcessAfterDepart::dispatch($queue->id);

        } elseif ($queue->vehicle_type === 'UV-express') {

            $queue->update(['departs_at' => Carbon::now()->addMinutes(30)]);
            ProcessAfterDepart::dispatch($queue->id)->delay($queue->departs_at);

            $this->refreshQueuedVehicleList();
        }

        $this->refreshQueuedVehicleList();
    }

    public function render() {

        if(!auth()->user()) {
            return $this->view()->layout('layouts.public-layout');
        }

        $role = auth()->user()->role;

        return $this->view()->layout('layouts.' . $role . '-layout');
    }

};
?>

<div class="w-full max-w-5xl mx-auto px-4">

    @auth
        @if ( auth()->user()->role === 'cashier' && auth()->id())
            <div class="flex justify-end gap-2 mb-6 items-center">
                <x-button size="sm" icon="plus" variant="primary" href="{{ route('cashier.queue.vehicle') }}" wire:navigate>Queue Vehicle</x-button>
                <x-button size="sm" href="{{ route('cashier.active-group') }}" wire:navigate>View Active Groups</x-button>
            </div>
        @endif
    @endauth

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
        <div class="flex items-center gap-2.5">
            <span class="relative flex h-2.5 w-2.5">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-red-500"></span>
            </span>
            <h1 class="text-sm font-semibold tracking-widest uppercase text-gray-700">Live Queue</h1>
        </div>

        <div class="flex items-center gap-2">

            <flux:input size="sm" type="text" wire:model.live="search" placeholder="Search routes…" />

            <div class="relative">
                <select wire:model.live="vehicleType"
                    class="appearance-none pl-3 pr-7 py-1.5 text-sm border border-gray-200 rounded-lg bg-white text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-100 focus:border-blue-400 cursor-pointer transition">
                    <option value="">All vehicles</option>
                    <option value="Jeep">Jeep</option>
                    <option value="Bus">Bus</option>
                    <option value="Multi-cab">Multi-cab</option>
                    <option value="UV-express">UV-express</option>
                </select>
            </div>
        </div>
    </div>


    @if(auth()->user() && auth()->user()->role === 'operator')
        <x-card>
            <div class="flex items-center justify-between mb-3 px-1">
                <span class="text-[11px] font-semibold tracking-widest uppercase text-gray-400">My Vehicles</span>
                <span class="text-[11px] text-gray-400 flex items-center gap-1">
                    <flux:icon name="arrow-path" class="size-3" />
                    Updates live
                </span>
            </div>

            @if($this->getQueuedUserVehicle->isEmpty())
                <div class="px-1 py-6 text-center text-sm text-gray-400">
                    You have no vehicles in the queue right now.
                </div>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 mb-4">
                    @foreach($this->getQueuedUserVehicle as $index => $queue)

                    <flux:card class="mb-4" wire:key="mine-{{ $queue->id }}">

                        <div class="flex items-center justify-between mb-2">
                            <x-text>{{ $queue->plate_number }}</x-text>

                            @if ($queue->status === 'loading')
                                <flux:badge size="sm" color="green">LOADING</flux:badge>
                            @else
                                <flux:badge size="sm" color="orange">STAGING</flux:badge>
                            @endif
                        </div>

                        <x-text size="xl" variant="strong">
                            #{{ $this->getQueuePosition($queue) }}
                        </x-text>

                        <x-text size="sm" class="mt-1 text-blue-500">
                            {{ $queue->vehicle_type }} &middot; {{ $queue->destination }}
                        </x-text>

                        {{-- Add the early departure action trigger button --}}
                        @if ($queue->status === 'loading')
                            <div class="mt-4 pt-3 border-t border-gray-100">
                                <flux:button 
                                    size="sm" 
                                    variant="danger" 
                                    color="blue" 
                                    icon="paper-airplane"
                                    wire:click="departEarly({{ $queue->id }})" 
                                    wire:confirm="Are you sure you want to depart early right now?"
                                    class="w-full">
                                    Depart Now
                                </flux:button>
                            </div>
                        @endif

                    </flux:card>

                    @endforeach
                </div>
            @endif
        </x-card>
    @endif

    {{-- Queue list --}}
    <div class="space-y-8">
        @forelse ($this->groupVehicles as $vehicle_type => $destinations)
            <div>
                <div class="flex items-center gap-3 mb-3">
                    <x-text size="xl">{{ $vehicle_type }}</x-text>
                    <div class="flex-1 h-px bg-gray-100"></div>
                </div>

                <div class="space-y-3">
                    @foreach ($destinations as $destination => $queues)
                        <div class="rounded-xl border border-gray-100 bg-white overflow-hidden shadow-sm">

                            <div class="flex items-center justify-between px-5 pt-4 pb-3">
                                <x-text size="xl" class="font-semibold text-blue-800">{{ $destination }}</x-text>
                            </div>

                            <div class="overflow-x-auto">
                                <table class="w-full text-sm border-t border-gray-100">
                                    <thead>
                                        <tr class="bg-gray-50/70">
                                            <th class="px-5 py-2.5 text-left text-[11px] font-semibold tracking-wider uppercase text-gray-400 w-8">#</th>
                                            <th class="px-5 py-2.5 text-left text-[11px] font-semibold tracking-wider uppercase text-gray-400">Plate No.</th>
                                            <th class="px-5 py-2.5 text-left text-[11px] font-semibold tracking-wider uppercase text-gray-400">Driver</th>
                                            <th class="px-5 py-2.5 text-left text-[11px] font-semibold tracking-wider uppercase text-gray-400">Seats</th>
                                            <th class="px-5 py-2.5 text-left text-[11px] font-semibold tracking-wider uppercase text-gray-400">Departs</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-50">
                                        @foreach ($queues as $index => $queue)

                                            @if ($queue->status === 'loading')
                                                {{-- Loading row — highlighted, timer running --}}
                                                <tr class="bg-blue-50/100 border-l-[3px] border-l-blue-600" wire:key="loading-{{ $queue->id }}">
                                                    <td class="pl-4 pr-5 py-3.5 text-blue-700 font-semibold text-xs">{{ $index + 1 }}</td>
                                                    <td class="px-5 py-3.5">
                                                        <span class="font-mono text-xs tracking-widest font-semibold text-blue-900 bg-blue-100 px-2 py-0.5 rounded">
                                                            {{ $queue->plate_number }}
                                                        </span>
                                                    </td>
                                                    <td class="px-5 py-3.5 text-blue-900 font-medium text-sm">{{ $queue->driver_name }}</td>
                                                    <td class="px-5 py-3.5">
                                                        @php $pct = $queue->seat_capacity > 0 ? round(($queue->seat_count / $queue->seat_capacity) * 100) : 0; @endphp
                                                        <div class="flex items-center gap-2">
                                                            <div class="w-12 h-1 rounded-full bg-blue-100 overflow-hidden">
                                                                <div class="{{ $pct >= 75 ? 'bg-red-400' : 'bg-blue-400' }} h-full rounded-full transition-all"
                                                                    style="width: {{ $pct }}%"></div>
                                                            </div>
                                                            <span class="text-xs text-blue-700 tabular-nums">{{ $queue->seat_count }}/{{ $queue->seat_capacity }}</span>
                                                        </div>
                                                    </td>
                                                    <td class="px-5 py-3.5">
                                                        @if ($queue->departs_at)
                                                            <span
                                                                x-data="{
                                                                    endTime: {{ \Carbon\Carbon::parse($queue->departs_at)->timestamp }} * 1000,
                                                                    display: '--:--',
                                                                    urgent: false,
                                                                    intervalId: null,
                                                                    init() { this.update(); this.intervalId = setInterval(() => this.update(), 1000); },
                                                                    destroy() { clearInterval(this.intervalId); },
                                                                    update() {
                                                                        const remaining = this.endTime - Date.now();
                                                                        if (remaining <= 0) { this.display = '--:--'; this.urgent = false; clearInterval(this.intervalId); return; }
                                                                        this.urgent = remaining < 30000;
                                                                        const m = String(Math.floor(remaining / 60000)).padStart(2, '0');
                                                                        const s = String(Math.floor((remaining % 60000) / 1000)).padStart(2, '0');
                                                                        this.display = m + ':' + s;
                                                                    }
                                                                }"
                                                                x-init="init()"
                                                                :class="urgent
                                                                    ? 'font-mono text-xs font-semibold tracking-widest bg-red-50 text-red-600 border border-red-200 px-2 py-0.5 rounded'
                                                                    : 'font-mono text-xs font-semibold tracking-widest bg-amber-50 text-amber-700 border border-amber-200 px-2 py-0.5 rounded'"
                                                                x-text="display"
                                                            ></span>
                                                        @else
                                                            <span class="text-xs text-gray-400 italic">--:--</span>
                                                        @endif
                                                    </td>
                                                </tr>

                                            @elseif ($index < 3)
                                                {{-- Staging rows — show up to 3 after the loading row --}}
                                                <tr class="hover:bg-gray-50/50 transition-colors" wire:key="staging-{{ $queue->id }}">
                                                    <td class="px-5 py-3 text-gray-400 text-xs">{{ $index + 1 }}</td>
                                                    <td class="px-5 py-3">
                                                        <span class="font-mono text-xs tracking-widest text-gray-400">{{ $queue->plate_number }}</span>
                                                    </td>
                                                    <td class="px-5 py-3 text-gray-400 text-sm">{{ $queue->driver_name }}</td>
                                                    <td class="px-5 py-3">
                                                        @php $pct = $queue->seat_capacity > 0 ? round(($queue->seat_count / $queue->seat_capacity) * 100) : 0; @endphp
                                                        <div class="flex items-center gap-2">
                                                            <div class="w-12 h-1 rounded-full bg-gray-100 overflow-hidden">
                                                                <div class="bg-gray-300 h-full rounded-full" style="width: {{ $pct }}%"></div>
                                                            </div>
                                                            <span class="text-xs text-gray-400 tabular-nums">{{ $queue->seat_count }}/{{ $queue->seat_capacity }}</span>
                                                        </div>
                                                    </td>
                                                    <td class="px-5 py-3">
                                                        <span class="text-xs text-gray-300 font-mono tracking-widest">--:--</span>
                                                    </td>
                                                </tr>
                                            @endif

                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

        @empty
            <div class="rounded-xl border border-gray-100 bg-white px-6 py-14 text-center">
                <p class="text-sm text-gray-400">No active queue right now.</p>
            </div>
        @endforelse
    </div>

</div>
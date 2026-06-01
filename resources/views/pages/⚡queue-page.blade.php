<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use App\Models\Queue;


new #[Layout('components.public.layout')]class extends Component {

    public string $search = '';
    public string $vehicleType = '';

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
            ->orderBy('time_queued')
            ->get()
            ->groupBy(['vehicle_type', 'destination']);
    }

}; ?>

<div class="w-full max-w-5xl mx-auto mt-10" wire:poll.5s>

    <!-- Queue Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">

        <flux:heading class="text-sm sm:text-sm lg:text-lg xl:text-xl">
            Live Queueing
        </flux:heading>

        <div class="flex-1 sm:max-w-xs mx-auto sm:mx-0">
            <flux:field>

                <flux:input 
                    placeholder="Search Routes" 
                    wire:model.live="search"
                    type="text"
                />

            </flux:field>
        </div>

        <div class="relative shrink-0">
            <flux:select wire:model.live="vehicleType">
                <flux:select.option value="" selected>All Vehicles</flux:select.option>
                <flux:select.option value="Jeep">Jeep</flux:select.option>
                <flux:select.option value="Bus">Bus</flux:select.option>
                <flux:select.option value="Multi-cab">Multi-cab</flux:select.option>
                <flux:select.option value="Van">Van</flux:select.option>
            </flux:select>
        </div>
    </div>

    <!-- Queue List -->
    <div class="mb-10">
        @forelse ($this->groupVehicles as $vehicle_type => $destinations)

            <h2 class="text-xl font-bold text-gray-800 mb-4">
                {{ $vehicle_type }}
            </h2>

            @foreach ($destinations as $destination => $queues)
                <div class="rounded-xl border border-gray-100 shadow-sm bg-white overflow-hidden mb-4">

                    <div class="px-6 pt-5 pb-3">
                        <h3 class="text-base font-semibold text-[#181E74]">
                            {{ $destination }}
                        </h3>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-t border-gray-200">
                                    <th class="px-6 py-3 text-left text-xs text-gray-400">#</th>
                                    <th class="px-6 py-3 text-left text-xs text-gray-400">Plate No.</th>
                                    <th class="px-6 py-3 text-left text-xs text-gray-400">Driver Name</th>
                                    <th class="px-6 py-3 text-left text-xs text-gray-400">Seat</th>
                                    <th class="px-6 py-3 text-left text-xs text-gray-400">Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($queues as $index => $queue)

                                    @if ($index === 0)
                                        <tr class="bg-[#181E74]/[0.07]" wire:key="loading-{{ $queue->id }}">
                                            <td class="px-6 py-3 font-semibold">{{ $index + 1 }}</td>
                                            <td class="px-6 py-3 font-semibold">{{ $queue->plate_number }}</td>
                                            <td class="px-6 py-3 font-semibold">{{ $queue->driver_name }}</td>
                                            <td class="px-6 py-3">{{ $queue->seat_count }} / {{ $queue->seat_capacity }}</td>
                                            <td class="px-6 py-3 text-red-500" wire:ignore.self>
                                                <span
                                                    x-data="{
                                                        endTime: {{ \Carbon\Carbon::parse($queue->departs_at)->timestamp }} * 1000,
                                                        display: '',
                                                        intervalId: null,
                                                        init() {
                                                            this.update();
                                                            this.intervalId = setInterval(() => this.update(), 1000);
                                                        },
                                                        destroy() {
                                                            clearInterval(this.intervalId);
                                                        },
                                                        update() {
                                                            const remaining = this.endTime - Date.now();
                                                            if (remaining <= 0) {
                                                                this.display = 'Departing';
                                                                clearInterval(this.intervalId);
                                                                return;
                                                            }
                                                            const m = String(Math.floor(remaining / 60000)).padStart(2, '0');
                                                            const s = String(Math.floor((remaining % 60000) / 1000)).padStart(2, '0');
                                                            this.display = m + ':' + s;
                                                        }
                                                    }"
                                                    x-init="init()"
                                                    x-text="display"
                                                ></span>
                                            </td>
                                        </tr>

                                    @elseif ($index < 3)
                                        {{-- Staging slots --}}
                                        <tr class="text-gray-400">
                                            <td class="px-6 py-3">{{ $index + 1 }}</td>
                                            <td class="px-6 py-3">{{ $queue->plate_number }}</td>
                                            <td class="px-6 py-3">{{ $queue->driver_name }}</td>
                                            <td class="px-6 py-3">{{ $queue->seat_count }} / {{ $queue->seat_capacity }}</td>
                                            <td class="px-6 py-3">--:--</td>
                                        </tr>
                                    @endif

                                @endforeach
                            </tbody>
                        </table>
                    </div>

                </div>
            @endforeach

        @empty
            <div class="rounded-lg border border-gray-100 bg-white p-6 text-sm text-gray-500">
                No active queue right now.
            </div>
        @endforelse
    </div>

</div>

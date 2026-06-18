<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Illuminate\Support\Carbon;
use App\Models\Queue;
use App\Models\Vehicle;

new #[Layout('layouts.operator-layout')] class extends Component
{
    public string $vehicle_type = '';

    #[Computed]
    public function getOwnQueueEntry()
    {
        return Queue::whereIn('status', ['staging', 'loading'])
            ->where('user_id', auth()->id())
            ->first();
    }

    #[Computed]
    public function getQueuedVehicle() {
        return Queue::whereIn('status', ['staging', 'loading'])
            ->when(
                $this->vehicle_type,
                fn($q) => $q->where('vehicle_type', $this->vehicle_type)
            )
            ->orderByRaw("FIELD(status, 'loading', 'staging')")
            ->get()
            ->groupBy(['vehicle_type', 'destination']);
    }

    #[Computed]
    public function getUserVehicle() {
        return Vehicle::where('user_id', auth()->id())->distinct()->pluck('vehicle_type');
    }
};
?>


<div class="flex flex-col h-full">

    <flux:breadcrumbs class="mb-8">
        <flux:breadcrumbs.item href="{{ route('operator.live.queue') }}" wire:navigate>
        Live Queue
        </flux:breadcrumbs.item>
        <flux:breadcrumbs.item>Queued Vehicle</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <x-pages-heading heading="Queued vehicle" />

    @if ($this->getOwnQueueEntry)
        @php
            $own = $this->getOwnQueueEntry;
            $ownGroupQueues = $this->getQueuedVehicle[$own->vehicle_type][$own->destination] ?? collect();
            $ownPosition = $ownGroupQueues->search(fn($q) => $q->id === $own->id) + 1;
            $aheadCount  = $ownPosition - 1;
        @endphp

        <flux:card class="flex items-center gap-4 mb-5 {{ $own->status === 'loading' ? 'border-indigo-200 bg-gradient-to-r from-indigo-50 to-blue-50 dark:border-indigo-800 dark:from-indigo-950 dark:to-blue-950' : 'border-emerald-200 bg-gradient-to-r from-emerald-50 to-teal-50 dark:border-emerald-800 dark:from-emerald-950 dark:to-teal-950' }}">

            <div class="w-12 h-12 rounded-full flex items-center justify-center text-xl font-bold shrink-0 {{ $own->status === 'loading' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-200 dark:shadow-indigo-950' : 'bg-emerald-500 text-white shadow-lg shadow-emerald-200 dark:shadow-emerald-950' }}">
                @if ($own->status === 'loading')
                    <flux:icon name="star" class="w-5 h-5" />
                @else
                    {{ $ownPosition }}
                @endif
            </div>

            <div class="flex-1 min-w-0">
                <flux:text size="sm" class="font-semibold uppercase tracking-widest {{ $own->status === 'loading' ? 'text-indigo-500 dark:text-indigo-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                    {{ $own->status === 'loading' ? 'Your vehicle is now loading' : 'Your vehicle\'s position' }}
                </flux:text>

                <flux:heading size="lg" class="font-mono {{ $own->status === 'loading' ? '!text-indigo-900 dark:!text-indigo-100' : '!text-emerald-900 dark:!text-emerald-100' }}">
                    {{ $own->plate_number }}
                </flux:heading>

                <flux:text size="sm" class="{{ $own->status === 'loading' ? 'text-indigo-600 dark:text-indigo-300' : 'text-emerald-600 dark:text-emerald-300' }}">
                    {{ $own->vehicle_type }} · Iriga → {{ $own->destination }}
                    @if ($own->status !== 'loading' && $aheadCount > 0)
                        · {{ $aheadCount }} vehicle{{ $aheadCount !== 1 ? 's' : '' }} ahead of you
                    @endif
                </flux:text>
            </div>

            @if ($own->status !== 'loading')
                <flux:button
                    size="sm"
                    variant="ghost"
                    icon="cursor-arrow-rays"
                    onclick="document.getElementById('own-row-{{ $own->id }}')?.scrollIntoView({behavior:'smooth', block:'center'})"
                >
                    Jump to row
                </flux:button>
            @endif
        </flux:card>
    @endif

    <div class="mb-5">
        <flux:select wire:model.live="vehicle_type" placeholder="Choose vehicle type...">
            <flux:select.option value="">All Vehicles</flux:select.option>
            @foreach ($this->getUserVehicle as $vehicle)
                <flux:select.option>{{ $vehicle }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <div class="space-y-8">
        @forelse ($this->getQueuedVehicle as $vehicle_type => $destinations)
            <div>
                <div class="flex items-center gap-3 mb-3">
                    <flux:heading size="lg">{{ $vehicle_type }}</flux:heading>
                    <flux:separator class="flex-1" />
                </div>

                <div>
                    @foreach ($destinations as $destination => $queues)
                        <flux:card>

                            <div class="px-5 pt-4 pb-3">
                                <flux:heading size="lg" class="!text-blue-800 dark:!text-blue-300">
                                    {{ $destination }}
                                </flux:heading>
                            </div>

                            <flux:separator />

                            <div>
                                <flux:table >
                                    <flux:table.columns>
                                        <flux:table.column class="w-10">#</flux:table.column>
                                        <flux:table.column class="w-28">Plate No.</flux:table.column>
                                        <flux:table.column class="w-40">Driver</flux:table.column>
                                        <flux:table.column class="w-28">Seats</flux:table.column>
                                        <flux:table.column class="w-24">Departs</flux:table.column>
                                    </flux:table.columns>

                                    <flux:table.rows>
                                        @foreach ($queues as $index => $queue)
                                            @php $isOwnVehicle = $queue->user_id === auth()->user()->id;
                                                
                                            @endphp

                                            @if ($queue->status === 'loading')
                                                <flux:table.row
                                                    id="own-row-{{ $queue->id }}"
                                                    :key="$queue->id"
                                                    class="!bg-blue-50/70 dark:!bg-blue-950/40 border-l-[4px] {{ $isOwnVehicle ? '!border-l-indigo-600 !bg-indigo-50/40 dark:!bg-indigo-950/40 relative shadow-inner font-semibold' : '!border-l-blue-600' }}"
                                                >
                                                    <flux:table.cell>
                                                        {{ $index + 1 }}
                                                    </flux:table.cell>

                                                    <flux:table.cell>
                                                        <flux:badge :color="$isOwnVehicle ? 'indigo' : 'blue'" size="sm" class="font-mono tracking-widest {{ $isOwnVehicle ? 'ring-2 ring-indigo-300 dark:ring-indigo-700' : '' }}">
                                                            {{ $queue->plate_number }}
                                                        </flux:badge>
                                                    </flux:table.cell>

                                                    <flux:table.cell class="text-blue-900 dark:text-blue-200 font-medium overflow-hidden">
                                                        <div class="flex items-center gap-1.5 min-w-0">
                                                            <span class="truncate" title="{{ $queue->driver_name }}">
                                                                {{ $queue->driver_name }}
                                                            </span>
                                                            @if ($isOwnVehicle)
                                                                <flux:badge size="sm" color="indigo" class="shrink-0">You</flux:badge>
                                                            @endif
                                                        </div>
                                                    </flux:table.cell>

                                                    <flux:table.cell>
                                                        @php $pct = $queue->seat_capacity > 0 ? round(($queue->seat_count / $queue->seat_capacity) * 100) : 0; @endphp
                                                        <div class="flex items-center gap-2">
                                                            <div class="w-10 h-1 rounded-full bg-blue-100 dark:bg-blue-900 overflow-hidden shrink-0">
                                                                <div class="{{ $pct >= 75 ? 'bg-red-400' : 'bg-blue-400' }} h-full rounded-full transition-all" style="width: {{ $pct }}%"></div>
                                                            </div>
                                                            <flux:text size="sm" class="text-blue-700 dark:text-blue-300 tabular-nums whitespace-nowrap">
                                                                {{ $queue->seat_count }}/{{ $queue->seat_capacity }}
                                                            </flux:text>
                                                        </div>
                                                    </flux:table.cell>

                                                    <flux:table.cell>
                                                        @if ($queue->departs_at)
                                                            <flux:badge
                                                                size="sm"
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
                                                                :color="undefined"
                                                                :class="urgent ? 'bg-red-50 text-red-600 border border-red-200 font-mono tracking-widest' : 'bg-amber-50 text-amber-700 border border-amber-200 font-mono tracking-widest'"
                                                                x-text="display"
                                                            ></flux:badge>
                                                        @else
                                                            <flux:text size="sm" class="italic text-zinc-400">--:--</flux:text>
                                                        @endif
                                                    </flux:table.cell>
                                                </flux:table.row>

                                            @elseif ($queue->status === 'staging')
                                                <flux:table.row
                                                id="own-row-{{ $queue->id }}"
                                                :key="$queue->id"
                                                class="transition-colors duration-200 {{ $isOwnVehicle ? '!bg-emerald-50/90 dark:!bg-emerald-955/40 border-y border-emerald-300 dark:border-emerald-700 !border-l-[5px] !border-l-emerald-500 font-semibold text-emerald-900 dark:text-emerald-100 relative' : 'text-zinc-400 dark:text-zinc-500' }}"
                                                >
                                                    <flux:table.cell>
                                                        {{ $index + 1 }}
                                                    </flux:table.cell>

                                                    <flux:table.cell>
                                                        @if ($isOwnVehicle)
                                                            <flux:badge color="emerald" size="sm" class="font-mono tracking-widest border border-emerald-300 dark:border-emerald-700">
                                                                {{ $queue->plate_number }}
                                                            </flux:badge>
                                                        @else
                                                            <span class="font-mono text-xs tracking-widest">{{ $queue->plate_number }}</span>
                                                        @endif
                                                    </flux:table.cell>

                                                    <flux:table.cell class="{{ $isOwnVehicle ? 'text-emerald-900 dark:text-emerald-100' : '' }} overflow-hidden">
                                                        <div class="flex items-center gap-1.5 min-w-0">
                                                            <span class="truncate" title="{{ $queue->driver_name }}">
                                                                {{ $queue->driver_name }}
                                                            </span>
                                                            @if ($isOwnVehicle)
                                                                <flux:badge size="sm" color="emerald" class="shrink-0">You are here</flux:badge>
                                                            @endif
                                                        </div>
                                                    </flux:table.cell>

                                                    <flux:table.cell>
                                                        @php $pct = $queue->seat_capacity > 0 ? round(($queue->seat_count / $queue->seat_capacity) * 100) : 0; @endphp
                                                        <div class="flex items-center gap-2">
                                                            <div class="w-10 h-1 rounded-full overflow-hidden shrink-0 {{ $isOwnVehicle ? 'bg-emerald-200 dark:bg-emerald-900' : 'bg-gray-100 dark:bg-zinc-800' }}">
                                                                <div class="{{ $isOwnVehicle ? 'bg-emerald-500' : 'bg-gray-300 dark:bg-zinc-700' }} h-full rounded-full" style="width: {{ $pct }}%"></div>
                                                            </div>
                                                            <flux:text size="sm" class="tabular-nums whitespace-nowrap {{ $isOwnVehicle ? 'text-emerald-700 dark:text-emerald-300 font-medium' : '' }}">
                                                                {{ $queue->seat_count }}/{{ $queue->seat_capacity }}
                                                            </flux:text>
                                                        </div>
                                                    </flux:table.cell>

                                                    <flux:table.cell>
                                                        <flux:text size="sm" class="font-mono tracking-widest {{ $isOwnVehicle ? 'text-emerald-600 dark:text-emerald-400' : 'text-zinc-300 dark:text-zinc-600' }}">
                                                            --:--
                                                        </flux:text>
                                                    </flux:table.cell>
                                                </flux:table.row>
                                            @endif
                                        @endforeach
                                    </flux:table.rows>
                                </flux:table>
                            </div>
                        </flux:card>
                    @endforeach
                </div>
            </div>
        @empty
            <flux:card class="px-6 py-14 text-center">
                <flux:icon name="calendar" class="w-8 h-8 mx-auto mb-2 text-zinc-300 stroke-1" />
                <flux:text size="sm" class="text-zinc-400">No active queue right now.</flux:text>
            </flux:card>
        @endforelse
    </div>

</div>
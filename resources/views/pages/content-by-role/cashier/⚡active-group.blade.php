<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Illuminate\Support\Carbon;
use App\Models\Queue;
use App\Models\DailyScheduleSlot;
use App\Services\QueueOrderService;

new #[Layout('layouts.cashier-layout')] class extends Component
{
    #[Computed]
    public function getCurrentActiveGroup()
    {
        return Queue::with('dailyScheduleSlot')
            ->whereIn('status', ['loading', 'staging', 'departed'])
            ->whereNotNull('daily_schedule_slot_id')
            ->whereHas('dailyScheduleSlot', fn ($q) => $q->where('schedule_date', today()->toDateString()))
            ->orderBy('slot_position', 'asc')
            ->get();
    }

    #[Computed]
    public function canAdvanceQueue(): bool
    {
        $first = $this->getCurrentActiveGroup->where('status', 'staging')->first();

        return $first && $first->status === 'staging';
    }

    #[On('echo:vehicle-queue,.QueuedVehicleEvent')]
    public function refresh(): void
    {
        unset($this->getCurrentActiveGroup);
        unset($this->canAdvanceQueue);
    }

    public function nextVehicle(): void
    {
        $firstWaitingVehicle = $this->getCurrentActiveGroup
            ->where('status', 'staging')
            ->first();

        if (! $firstWaitingVehicle) {
            return;
        }

        $result = app(QueueOrderService::class)->swapWithNext($firstWaitingVehicle->id);
        
        if ($result['success'] === false) {
            Flux::toast(
                variant: 'warning',
                heading: 'Cannot advance queue.',
                text: $result['message'] ?? 'An unknown error occurred.',
            );

            return;

        } else{
            $this->refresh();

            Flux::toast(
                variant: 'success',
                heading: 'Vehicle advanced.',
                text: "{$firstWaitingVehicle->plate_number} has been demoted.",
            );
        }

        // dd($result);
    }

    // public function mount() {
    //     dd($this->getCurrentActiveGroup);
    // }
};
?>

<div class="flex flex-col h-full">

    <flux:breadcrumbs class="mb-6">
        <flux:breadcrumbs.item href="{{ route('cashier.queue') }}" wire:navigate>Live Queue</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>Active Groups</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <div class="shrink-0 px-6 pb-4">
        <x-pages-heading heading="Current active group" description="Manage queue order and advance the next vehicle here." />
    </div>

    {{-- ───────────────────────── Scrollable vehicle list ───────────────────────── --}}
    <div class="flex-1 overflow-y-auto px-6 py-2 space-y-2">

        @forelse ($this->getCurrentActiveGroup as $vehicle)

            @php
                $status   = $vehicle->status;
                $isActive = $status === 'loading';
            @endphp

            <flux:card
                wire:key="vehicle-{{ $vehicle->id }}"
                size="sm"
                :class="$isActive ? 'ring-1 ring-blue-300 dark:ring-blue-700 border-blue-300 dark:border-blue-700 bg-blue-50 dark:bg-blue-950' : ($status === 'departed' || $status === 'skipped' ? 'opacity-50' : '')"
            >
                <div class="flex items-center gap-3">

                    {{-- Position / status icon --}}
                    <div @class([
                        'rounded-full flex items-center justify-center font-semibold shrink-0',
                        'w-8 h-8 text-sm bg-blue-500 text-white'                                         => $isActive,
                        'w-7 h-7 text-xs bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400'  => $status === 'waiting',
                        'w-7 h-7 text-xs bg-zinc-100 text-zinc-400 dark:bg-zinc-800 dark:text-zinc-500'  => $status === 'departed' || $status === 'skipped',
                    ])>
                        @if ($status === 'departed')
                            <flux:icon name="check" class="w-3.5 h-3.5" />
                        @elseif ($status === 'skipped')
                            <flux:icon name="x-mark" class="w-3.5 h-3.5" />
                        @else
                            {{ $vehicle->slot_position }}
                        @endif
                    </div>

                    {{-- Vehicle info --}}
                    <div class="flex-1 min-w-0">
                        <flux:text
                            variant="strong"
                            class="font-mono tracking-wide {{ $isActive ? 'text-base text-blue-900 dark:text-blue-100' : 'text-sm' }}"
                        >
                            {{ $vehicle->plate_number }}
                        </flux:text>
                        <flux:text size="sm" class="text-zinc-400 dark:text-zinc-500 block">
                            {{ $vehicle->driver_name ?: '—' }} · {{ $vehicle->vehicle_type }} · {{ $vehicle->destination }}
                        </flux:text>
                    </div>

                    {{-- Status badge --}}
                    <flux:badge size="sm" :color="match($status) {
                        'loading'  => 'blue',
                        'waiting'  => 'zinc',
                        'departed' => 'green',
                        'skipped'  => 'red',
                        default    => 'zinc',
                    }">
                        {{ ucfirst($status) }}
                    </flux:badge>

                </div>
            </flux:card>

        @empty
            <div class="flex flex-col items-center justify-center py-16 text-center text-zinc-400 dark:text-zinc-500">
                <flux:icon name="calendar" class="w-8 h-8 mb-2 stroke-1" />
                <flux:text size="sm">No active group scheduled for today.</flux:text>
            </div>
        @endforelse

    </div>

    {{-- ───────────────────────── Next card ─────────────────────────
         Only rendered when the first vehicle in the queue is "waiting".
         If something is already "loading", there's nothing to advance into —
         the queue is already active and the Next action would have no valid target.
    --}}
    @if ($this->canAdvanceQueue)
        @php 

        $next = $this->getCurrentActiveGroup->where('status', 'staging')->first(); 

        @endphp

        <div class="shrink-0 border-t border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 px-6 py-3">
            <flux:card size="sm" class="flex items-center gap-3">

                <div class="flex-1 min-w-0">
                    <flux:text size="sm" class="uppercase tracking-widest text-[10px] font-semibold text-zinc-400 dark:text-zinc-500 block mb-0.5">
                        Up next
                    </flux:text>
                    <flux:text variant="strong" class="font-mono tracking-wide">
                        {{ $next->plate_number }}
                    </flux:text>
                    <flux:text size="sm" class="text-zinc-400 dark:text-zinc-500 block">
                        {{ $next->driver_name ?: '—' }} · {{ $next->vehicle_type }} · {{ $next->destination }}
                    </flux:text>
                </div>

                <flux:button wire:click="nextVehicle" icon="arrow-right" variant="primary" size="sm">
                    Next
                </flux:button>

            </flux:card>
        </div>
    @endif

</div>
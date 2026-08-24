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

    #[Computed]
    public function groupStats()
    {
        $group = $this->getCurrentActiveGroup;

        return [
            'total'    => $group->count(),
            'loading'  => $group->where('status', 'loading')->count(),
            'waiting'  => $group->where('status', 'staging')->count(),
            'departed' => $group->where('status', 'departed')->count(),
        ];
    }

    #[On('echo:vehicle-queue,.QueuedVehicleEvent')]
    public function refresh(): void
    {
        unset($this->getCurrentActiveGroup);
        unset($this->canAdvanceQueue);
        unset($this->groupStats);
    }

    public function nextVehicle(): void
    {
        $firstWaitingVehicle = $this->getCurrentActiveGroup
            ->where('status', 'staging')
            ->first();

        if (! $firstWaitingVehicle) {
            return;
        }

        $result = app(QueueOrderService::class)->sendToBackOfQueue($firstWaitingVehicle->id);

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
    }
};
?>

<div class="flex flex-col h-full">
    <div class="shrink-0">

        <div class="flex items-start justify-between gap-4 mb-6">
            <div>
                <x-heading
                    size="xl"
                    class="!font-primary !font-bold !text-light-txt-primary dark:!text-dark-txt-primary"
                    style="font-size: var(--text-page-title)"
                >
                    Current Active Group
                </x-heading>
                <x-text variant="subtle" class="!font-secondary mt-1 block" style="font-size: var(--text-helper)">
                    Manage queue order and advance the next vehicle for today's schedule.
                </x-text>
            </div>

            <flux:breadcrumbs class="shrink-0 pt-1">
                <flux:breadcrumbs.item href="{{ route('user.queue') }}" wire:navigate>Back to Live Queue</flux:breadcrumbs.item>
                <flux:breadcrumbs.item>Active Groups</flux:breadcrumbs.item>
            </flux:breadcrumbs>
        </div>

        {{-- Overview stats --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 sm:gap-3 mb-6">
            <flux:card class="p-3 sm:p-4">
                <div class="flex items-center gap-1.5 sm:gap-2 mb-1.5">
                    <div class="flex items-center justify-center w-6 h-6 sm:w-7 sm:h-7 rounded-lg bg-primary/10 dark:bg-primary/20 shrink-0">
                        <flux:icon.truck class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-primary dark:text-dark-txt-primary" />
                    </div>
                    <x-text class="font-secondary text-xs sm:text-stat-label text-light-txt-muted dark:text-dark-txt-muted">
                        In group
                    </x-text>
                </div>
                <x-text class="font-primary text-stat-value font-bold text-light-txt-primary dark:text-dark-txt-primary block">
                    {{ $this->groupStats['total'] }}
                </x-text>
            </flux:card>

            <flux:card class="p-3 sm:p-4">
                <div class="flex items-center gap-1.5 sm:gap-2 mb-1.5">
                    <div class="flex items-center justify-center w-6 h-6 sm:w-7 sm:h-7 rounded-lg bg-success/10 dark:bg-dark-success/20 shrink-0">
                        <flux:icon.clock class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-success dark:text-dark-success" />
                    </div>
                    <x-text class="font-secondary text-xs sm:text-stat-label text-light-txt-muted dark:text-dark-txt-muted">
                        Boarding
                    </x-text>
                </div>
                <x-text class="font-primary text-stat-value font-bold text-success dark:text-dark-success block">
                    {{ $this->groupStats['loading'] }}
                </x-text>
            </flux:card>

            <flux:card class="p-3 sm:p-4">
                <div class="flex items-center gap-1.5 sm:gap-2 mb-1.5">
                    <div class="flex items-center justify-center w-6 h-6 sm:w-7 sm:h-7 rounded-lg bg-warning/10 dark:bg-dark-warning/20 shrink-0">
                        <flux:icon.users class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-warning dark:text-dark-warning" />
                    </div>
                    <x-text class="font-secondary text-xs sm:text-stat-label text-light-txt-muted dark:text-dark-txt-muted">
                        Waiting
                    </x-text>
                </div>
                <x-text class="font-primary text-stat-value font-bold text-warning dark:text-dark-warning block">
                    {{ $this->groupStats['waiting'] }}
                </x-text>
            </flux:card>

            <flux:card class="p-3 sm:p-4">
                <div class="flex items-center gap-1.5 sm:gap-2 mb-1.5">
                    <div class="flex items-center justify-center w-6 h-6 sm:w-7 sm:h-7 rounded-lg bg-light-subtle dark:bg-dark-subtle shrink-0">
                        <flux:icon.check-circle class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-light-txt-muted dark:text-dark-txt-muted" />
                    </div>
                    <x-text class="font-secondary text-xs sm:text-stat-label text-light-txt-muted dark:text-dark-txt-muted">
                        Departed
                    </x-text>
                </div>
                <x-text class="font-primary text-stat-value font-bold text-light-txt-primary dark:text-dark-txt-primary block">
                    {{ $this->groupStats['departed'] }}
                </x-text>
            </flux:card>
        </div>
    </div>

    <div class="flex-1 overflow-y-auto space-y-2">

        @forelse ($this->getCurrentActiveGroup as $index => $vehicle)

            @php
                $status   = $vehicle->status;
                $isActive = $status === 'loading';
                $isFirst  = $index === 0;

                $cardClasses = match(true) {
                    $isActive => 'ring-1 ring-primary/30 dark:ring-primary/40 !border-primary/30 dark:!border-primary/40 !bg-primary/5 dark:!bg-primary/10',
                    in_array($status, ['departed', 'skipped']) => 'opacity-60',
                    default => '',
                };

                if ($isFirst) {
                    $cardClasses .= ' !border-2 !border-success dark:!border-dark-success shadow-md';
                }
            @endphp

            <flux:card
                wire:key="vehicle-{{ $vehicle->id }}"
                size="sm"
                class="{{ $cardClasses }}"
            >
                <div class="flex items-center gap-3">

                    {{-- Position / status icon --}}
                    <div @class([
                        'rounded-full flex items-center justify-center font-secondary font-semibold shrink-0',
                        'w-8 h-8 text-sm bg-primary text-white'                                                              => $isActive,
                        'w-7 h-7 text-xs bg-light-subtle text-light-txt-muted dark:bg-dark-subtle dark:text-dark-txt-muted'  => $status === 'staging',
                        'w-7 h-7 text-xs bg-light-subtle text-light-txt-muted/60 dark:bg-dark-subtle dark:text-dark-txt-muted/60' => in_array($status, ['departed', 'skipped']),
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
                        <div class="flex items-center gap-2">
                            <span class="font-mono tracking-widest font-semibold text-light-txt-primary dark:text-dark-txt-primary {{ $isActive ? 'text-base' : 'text-sm' }}">
                                {{ $vehicle->plate_number }}
                            </span>
                            @if ($isFirst)
                                <flux:badge size="sm" color="green" class="font-secondary text-xs">Up next</flux:badge>
                            @endif
                        </div>
                        <span class="font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted block">
                            {{ $vehicle->driver_name ?: '—' }} &middot; {{ $vehicle->vehicle_type }} &middot; {{ $vehicle->destination }}
                        </span>
                    </div>

                    {{-- Status badge --}}
                    <flux:badge size="sm" :color="match($status) {
                        'loading'  => 'green',
                        'staging'  => 'orange',
                        'departed' => 'zinc',
                        'skipped'  => 'red',
                        default    => 'zinc',
                    }">
                        {{ ucfirst($status) }}
                    </flux:badge>

                </div>
            </flux:card>

        @empty
            <x-card class="!rounded-xl !border !border-dashed !border-light-bd-strong dark:!border-dark-bd-strong !bg-light-secondary dark:!bg-dark-secondary !text-center !p-8">
                <flux:icon name="calendar" class="w-8 h-8 mx-auto text-light-txt-muted dark:text-dark-txt-muted mb-2" />
                <x-text variant="subtle" class="!font-secondary block" style="font-size: var(--text-table-row)">
                    No active group scheduled for today.
                </x-text>
            </x-card>
        @endforelse

    </div>

    @if ($this->canAdvanceQueue)
        @php
            $next = $this->getCurrentActiveGroup->where('status', 'staging')->first();
        @endphp

        <div class="shrink-0 border-t border-light-bd-default dark:border-dark-bd-default bg-light-secondary/50 dark:bg-dark-secondary/50 py-3">
            <flux:card size="sm" class="flex items-center gap-3">

                <div class="flex-1 min-w-0">
                    <x-text class="font-secondary uppercase tracking-widest text-[10px] font-semibold text-light-txt-muted dark:text-dark-txt-muted block mb-0.5">
                        Up next
                    </x-text>
                    <span class="font-mono tracking-widest font-semibold text-light-txt-primary dark:text-dark-txt-primary block">
                        {{ $next->plate_number }}
                    </span>
                    <span class="font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted block">
                        {{ $next->driver_name ?: '—' }} &middot; {{ $next->vehicle_type }} &middot; {{ $next->destination }}
                    </span>
                </div>

                <flux:button wire:click="nextVehicle" icon="arrow-right" variant="primary" size="sm" class="font-secondary">
                    Next
                </flux:button>

            </flux:card>
        </div>
    @endif

</div>
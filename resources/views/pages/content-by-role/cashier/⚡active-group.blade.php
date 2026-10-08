<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use App\Models\Queue;
use App\Services\QueueOrderService;

new class extends Component
{
    #[Url(as: 'type')]
    public string $vehicleType = 'Bus';

    #[Computed]
    public function types(): array
    {
        return ['Bus', 'UV-express'];
    }

    protected function activeType(): string
    {
        return in_array($this->vehicleType, $this->types, true)
            ? $this->vehicleType
            : $this->types[0];
    }

    public function selectType(string $type): void
    {
        if (in_array($type, $this->types, true)) {
            $this->vehicleType = $type;
        }
    }

    // Everything scheduled today, both types (one query)
    #[Computed]
    public function todayQueue()
    {
        return Queue::with('dailyScheduleSlot')
            ->whereIn('status', ['loading', 'staging', 'departed', 'skipped'])
            ->whereIn('vehicle_type', $this->types)
            ->whereNotNull('daily_schedule_slot_id')
            ->whereHas('dailyScheduleSlot', fn ($q) => $q->where('schedule_date', today()->toDateString()))
            ->orderByRaw("CASE status WHEN 'loading' THEN 0 WHEN 'staging' THEN 1 ELSE 2 END")
            ->orderBy('slot_position')
            ->orderBy('id')
            ->get();
    }

    // Only the selected tab's vehicles
    #[Computed]
    public function getCurrentActiveGroup()
    {
        return $this->todayQueue->where('vehicle_type', $this->activeType())->values();
    }

    #[Computed]
    public function tabCounts(): array
    {
        return collect($this->types)->mapWithKeys(fn ($type) => [
            $type => $this->todayQueue
                ->where('vehicle_type', $type)
                ->whereIn('status', ['loading', 'staging'])
                ->count(),
        ])->all();
    }

    #[Computed]
    public function nextWaiting()
    {
        return $this->getCurrentActiveGroup
            ->where('status', 'staging')
            ->sortBy('slot_position')
            ->first();
    }

    #[Computed]
    public function canAdvanceQueue(): bool
    {
        return $this->nextWaiting !== null;
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
        unset($this->todayQueue);
        unset($this->getCurrentActiveGroup);
        unset($this->tabCounts);
        unset($this->nextWaiting);
        unset($this->canAdvanceQueue);
        unset($this->groupStats);
    }

    public function nextVehicle(): void
    {
        $next = $this->nextWaiting;

        if (! $next) {
            return;
        }

        $result = app(QueueOrderService::class)->sendToBackOfQueue($next->id);

        if ($result['success'] === false) {
            Flux::toast(
                variant: 'warning',
                duration: 4000,
                heading: 'Cannot advance queue.',
                text: $result['message'] ?? 'An unknown error occurred.',
            );

            return;
        }

        $this->refresh();

        Flux::toast(
            variant: 'success',
            duration: 4000,
            heading: 'Vehicle advanced.',
            text: "{$next->plate_number} has been demoted.",
        );
    }

    public function skipVehicle(): void
    {
        $next = $this->nextWaiting;

        if (! $next) {
            return;
        }

        $result = app(QueueOrderService::class)->skipVehicle($next->id);
        $this->refresh();

        Flux::toast(
            variant: $result['success'] ? 'success' : 'warning',
            duration: 4000,
            heading: $result['success'] ? 'Vehicle skipped.' : 'Cannot skip.',
            text: $result['message'],
        );
    }

    public function render()
    {
        $role = auth()->user()->role;

        return $this->view()->layout('layouts.' . $role . '-layout');
    }
};
?>

<div class="flex flex-col h-full">
    <div class="shrink-0">

        <x-page-header
            heading="Current Active Group"
            class="mb-3"
        >
        </x-page-header>

        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-2 sm:gap-4 mb-6">
            <flux:breadcrumbs class="order-1 sm:order-2 shrink-0 sm:pt-1">
                <flux:breadcrumbs.item href="{{ route('user.queue') }}" wire:navigate>Back to Live Queue</flux:breadcrumbs.item>
                <flux:breadcrumbs.item>Active Groups</flux:breadcrumbs.item>
            </flux:breadcrumbs>

            <div class="order-2 sm:order-1 w-full sm:w-auto">
                <x-heading
                    class="!font-primary !font-bold !text-light-txt-primary dark:!text-dark-txt-primary"
                    style="font-size: var(--text-section-heading)"
                >
                    Manage queue order and advance the next vehicle for today's schedule
                </x-heading>
            </div>
        </div>

        <div class="flex flex-wrap gap-2 mb-4">
            @foreach ($this->types as $type)
                <flux:button
                    wire:key="tab-{{ $type }}"
                    wire:click="selectType('{{ $type }}')"
                    size="sm"
                    :variant="$type === $this->activeType() ? 'primary' : 'ghost'"
                    class="font-secondary"
                >
                    {{ $type }}
                    <span class="ml-1.5 text-xs opacity-70">{{ $this->tabCounts[$type] ?? 0 }}</span>
                </flux:button>
            @endforeach
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
        
        @forelse ($this->getCurrentActiveGroup as $vehicle)

            @php
                $status   = $vehicle->status;
                $isActive = $status === 'loading';
                $isFirst  = $vehicle->id === $this->nextWaiting?->id;

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
                    No active {{ $this->activeType() }} group scheduled for today.
                </x-text>
            </x-card>
        @endforelse

    </div>

    @if ($this->canAdvanceQueue)
        @php $next = $this->nextWaiting; @endphp

        <div class="shrink-0 border-t border-light-bd-default dark:border-dark-bd-default bg-light-secondary/50 dark:bg-dark-secondary/50 py-3">
            <flux:card size="sm" class="flex items-center gap-3">

                <div class="flex-1 min-w-0">
                    <x-text class="font-secondary uppercase tracking-widest text-[10px] font-semibold text-light-txt-muted dark:text-dark-txt-muted block mb-0.5">
                        Up next &middot; {{ $next->vehicle_type }}
                    </x-text>
                    <span class="font-mono tracking-widest font-semibold text-light-txt-primary dark:text-dark-txt-primary block">
                        {{ $next->plate_number }}
                    </span>
                    <span class="font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted block">
                        {{ $next->driver_name ?: '—' }} &middot; {{ $next->destination }}
                    </span>
                </div>

                <div class="flex gap-2">
                    <flux:button wire:click="skipVehicle" wire:confirm="Skip this vehicle for this round?" icon="x-mark" variant="danger" size="sm" class="font-secondary">
                        Skip
                    </flux:button>
                    <flux:button wire:click="nextVehicle" icon="arrow-right" variant="primary" size="sm" class="font-secondary">
                        Next
                    </flux:button>
                </div>

            </flux:card>
        </div>
    @endif

</div>
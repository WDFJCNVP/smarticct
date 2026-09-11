<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Jobs\ProcessAfterDepart;
use App\Models\Queue;
use App\Http\Controllers\Api\CardController;

new class extends Component {

    public string $search = '';
    public string $vehicleType = '';

    // ===================== ACTION CONFIRMATIONS =====================
    public bool $show_depart_early_modal = false;
    public bool $show_cash_fare_modal = false;
    public bool $show_dispatch_modal = false;
    public ?int $pendingQueueId = null;
    public string $pendingQueuePlate = '';

    public function confirmDepartEarly(int $queueId): void
    {
        $this->pendingQueueId = $queueId;
        $this->show_depart_early_modal = true;
    }

    public function confirmCashFare(int $queueId): void
    {
        $this->pendingQueueId = $queueId;
        $this->show_cash_fare_modal = true;
    }

    public function confirmDispatch(int $queueId, string $plate): void
    {
        $this->pendingQueueId = $queueId;
        $this->pendingQueuePlate = $plate;
        $this->show_dispatch_modal = true;
    }

    protected function canManageQueue(): bool
    {
        return auth()->check() && in_array(auth()->user()->role, ['cashier', 'admin']);
    }

    public function departEarly($queueId)
    {
        $this->show_depart_early_modal = false;

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

        Flux::toast(
            duration: 4000,
            variant: 'success',
            heading: 'Vehicle departed',
            text: 'Your vehicle has been marked as departed early.',
        );
    }

    public function reportCashFare($queueId)
    {
        $this->show_cash_fare_modal = false;

        $request = new Request();
        $request->merge(['queue_id' => $queueId]);

        try {
            $response     = (new CardController())->operatorCashFare($request);
            $responseData = $response->getData(true);
        } catch (\Exception $e) {
            Flux::toast(
                duration: 4000,
                variant: 'warning',
                heading: 'Failed to log passenger',
                text: 'Something went wrong. Please try again.',
            );
            return;
        }

        if ($responseData['success'] === true) {
            $this->refreshQueuedVehicleList();

            Flux::toast(
                duration: 3000,
                variant: 'success',
                heading: 'Passenger logged',
                text: $responseData['message'],
            );
        } else {
            Flux::toast(
                duration: 4000,
                variant: 'warning',
                heading: 'Could not log passenger',
                text: $responseData['message'] ?? 'An error occurred.',
            );
        }
    }

    public function dispatchVehicle($queueId)
    {
        $this->show_dispatch_modal = false;

        if (!$this->canManageQueue()) {
            return;
        }

        $queue = Queue::where('id', $queueId)
            ->where('status', 'loading')
            ->first();

        if (!$queue) {
            return;
        }

        $queue->update([
            'time_departed' => now(),
            'status' =>'departed'
        ]);

        ProcessAfterDepart::dispatch($queue->id);

        $this->refreshQueuedVehicleList();

        Flux::toast(
            duration: 4000,
            variant: 'success',
            heading: 'Vehicle dispatched',
            text: 'The vehicle has been dispatched.',
        );
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
                       ->orWhere('vehicle_type', 'like', '%' . $this->search . '%')
                       ->orWhere('plate_number', 'like', '%' . $this->search . '%')
                       ->orWhere('driver_name', 'like', '%' . $this->search . '%');
                })
            )

            ->orderByRaw("FIELD(status, 'loading', 'staging')")
            ->orderBy('slot_position')
            ->orderBy('time_queued')
            ->get()
            ->groupBy(['vehicle_type', 'destination']);
    }

    #[Computed]
    public function queueStats()
    {
        $active = Queue::whereIn('status', ['staging', 'loading'])->get();

        return [
            'total'      => $active->count(),
            'boarding'   => $active->where('status', 'loading')->count(),
            'passengers' => $active->sum('seat_count'),
        ];
    }

    #[On('echo:vehicle-queue,.QueuedVehicleEvent')]
    public function refreshQueuedVehicleList() {
        unset($this->groupVehicles);
        unset($this->queueStats);
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

<div>
    @guest
        {{-- Hero (public landing only) --}}
        <div class="relative overflow-hidden bg-primary px-6 py-14 sm:px-10 sm:py-20">
            <div class="absolute -top-24 -right-24 h-72 w-72 rounded-full bg-secondary/10"></div>
            <div class="absolute -bottom-32 -left-16 h-72 w-72 rounded-full bg-white/5"></div>

            <div class="relative mx-auto max-w-5xl">
                <h1 class="mt-4 text-3xl font-extrabold text-white sm:text-4xl">Live Queue</h1>
                <p class="mt-2 max-w-xl text-body text-white/70">
                    Vehicles lined up per route, in dispatch order.
                </p>
            </div>
        </div>
    @endguest

    <div class="{{ auth()->guest() ? 'mx-auto max-w-5xl px-4 sm:px-6 py-8' : '' }}">

        @auth
            {{-- ====== PAGE HEADER (desktop only) ====== --}}
            <x-page-header
                heading="Live Queue"
                class="mb-4"
            >
                {{-- Empty slot – no buttons inside the header --}}
            </x-page-header>

            {{-- ====== ACTION BUTTONS (visible on all screens, pinned to the far right) ====== --}}
            @if (in_array(auth()->user()->role, ['cashier', 'admin']))
                <div class="flex items-center justify-end gap-2 mb-6 w-full sm:w-auto">
                    <flux:button
                        size="sm"
                        icon="plus"
                        variant="primary"
                        href="{{ route('cashier.queue.vehicle') }}"
                        wire:navigate
                        class="font-secondary flex-1 sm:flex-none justify-center"
                    >
                        Queue Vehicle
                    </flux:button>
                    <flux:button
                        size="sm"
                        href="{{ route('cashier.active-group') }}"
                        wire:navigate
                        class="font-secondary flex-1 sm:flex-none justify-center"
                    >
                        View Active Groups
                    </flux:button>
                </div>
            @endif
        @endauth

        {{-- Overview stats --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 sm:gap-3 mb-6">
            <flux:card class="p-3 sm:p-4">
                <div class="flex items-center gap-1.5 sm:gap-2 mb-1.5">
                    <div class="flex items-center justify-center w-6 h-6 sm:w-7 sm:h-7 rounded-lg bg-primary/10 dark:bg-primary/20 shrink-0">
                        <flux:icon.truck class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-primary dark:text-dark-txt-primary" />
                    </div>
                    <x-text class="font-secondary text-xs sm:text-stat-label text-light-txt-muted dark:text-dark-txt-muted">
                        Vehicles in queue
                    </x-text>
                </div>
                <x-text class="font-primary text-stat-value font-bold text-light-txt-primary dark:text-dark-txt-primary block">
                    {{ $this->queueStats['total'] }}
                </x-text>
            </flux:card>

            <flux:card class="p-3 sm:p-4">
                <div class="flex items-center gap-1.5 sm:gap-2 mb-1.5">
                    <div class="flex items-center justify-center w-6 h-6 sm:w-7 sm:h-7 rounded-lg bg-success/10 dark:bg-dark-success/20 shrink-0">
                        <flux:icon.clock class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-success dark:text-dark-success" />
                    </div>
                    <x-text class="font-secondary text-xs sm:text-stat-label text-light-txt-muted dark:text-dark-txt-muted">
                        Boarding or ready
                    </x-text>
                </div>
                <x-text class="font-primary text-stat-value font-bold text-success dark:text-dark-success block">
                    {{ $this->queueStats['boarding'] }}
                </x-text>
            </flux:card>

            <flux:card class="p-3 sm:p-4">
                <div class="flex items-center gap-1.5 sm:gap-2 mb-1.5">
                    <div class="flex items-center justify-center w-6 h-6 sm:w-7 sm:h-7 rounded-lg bg-warning/10 dark:bg-dark-warning/20 shrink-0">
                        <flux:icon.users class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-warning dark:text-dark-warning" />
                    </div>
                    <x-text class="font-secondary text-xs sm:text-stat-label text-light-txt-muted dark:text-dark-txt-muted">
                        Passengers onboard
                    </x-text>
                </div>
                <x-text class="font-primary text-stat-value font-bold text-warning dark:text-dark-warning block">
                    {{ $this->queueStats['passengers'] }}
                </x-text>
            </flux:card>
        </div>

        {{-- Search + filter --}}
        <div class="flex flex-col sm:flex-row sm:items-center gap-3 mb-6">
            <div class="flex-1">
                <flux:input
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search route, plate or driver..."
                    class="w-full font-secondary text-table-row dark:bg-dark-secondary dark:border-dark-bd-default dark:text-dark-txt-primary"
                    icon="magnifying-glass"
                />
            </div>
            <div class="w-full sm:w-48">
                <flux:select wire:model.live="vehicleType" class="w-full font-secondary text-table-row dark:bg-dark-secondary dark:border-dark-bd-default dark:text-dark-txt-primary">
                    <flux:select.option value="">All vehicles</flux:select.option>
                    <flux:select.option value="Jeep">Jeep</flux:select.option>
                    <flux:select.option value="Bus">Bus</flux:select.option>
                    <flux:select.option value="Multi-cab">Multi-cab</flux:select.option>
                    <flux:select.option value="UV-express">UV-express</flux:select.option>
                </flux:select>
            </div>
        </div>

        @if(auth()->user() && auth()->user()->role === 'operator')
            <flux:card class="mb-8 p-4 dark:bg-dark-secondary dark:border-dark-bd-default">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-primary text-card-title font-semibold text-light-txt-primary dark:text-dark-txt-primary">My Vehicles</h3>
                    <span class="font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted flex items-center gap-1">
                        <flux:icon name="arrow-path" class="size-3" />
                        Updates live
                    </span>
                </div>

                @if($this->getQueuedUserVehicle->isEmpty())
                    <div class="py-6 text-center font-secondary text-table-row text-light-txt-muted dark:text-dark-txt-muted">
                        You have no vehicles in the queue right now.
                    </div>
                @else
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                        @foreach($this->getQueuedUserVehicle as $queue)
                            <div class="rounded-lg border border-light-bd-default dark:border-dark-bd-default p-3 dark:bg-dark-surface">
                                <div class="flex items-center justify-between">
                                    <span class="font-mono text-sm font-semibold text-light-txt-primary dark:text-dark-txt-primary">{{ $queue->plate_number }}</span>
                                    @if ($queue->status === 'loading')
                                        <flux:badge color="green" size="sm" class="font-secondary text-xs">Boarding</flux:badge>
                                    @else
                                        <flux:badge color="orange" size="sm" class="font-secondary text-xs">Waiting</flux:badge>
                                    @endif
                                </div>
                                <div class="mt-1 text-sm text-light-txt-muted dark:text-dark-txt-muted">
                                    {{ $queue->vehicle_type }} &middot; {{ $queue->destination }}
                                </div>
                                <div class="mt-2 flex items-center justify-between">
                                    <span class="font-primary text-lg font-bold text-light-txt-primary dark:text-dark-txt-primary">#{{ $this->getQueuePosition($queue) }}</span>
                                    <span class="text-sm text-light-txt-muted dark:text-dark-txt-muted">Seats: {{ $queue->seat_count }}/{{ $queue->seat_capacity }}</span>
                                </div>
                                @if ($queue->status === 'loading')
                                    <div class="mt-3 pt-3 border-t border-light-bd-default dark:border-dark-bd-default flex flex-col gap-2">
                                        @if ($queue->seat_count < $queue->seat_capacity)
                                            <flux:button
                                                size="sm"
                                                variant="primary"
                                                icon="plus"
                                                wire:click="confirmCashFare({{ $queue->id }})"
                                                class="w-full font-secondary"
                                            >
                                                1 Passenger (Cash)
                                            </flux:button>
                                        @endif
                                        <flux:button
                                            size="sm"
                                            variant="danger"
                                            icon="paper-airplane"
                                            wire:click="confirmDepartEarly({{ $queue->id }})"
                                            class="w-full font-secondary"
                                        >
                                            Depart Now
                                        </flux:button>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </flux:card>
        @endif

        <div class="space-y-8">
            @forelse ($this->groupVehicles as $vehicle_type => $destinations)
                <div>
                    <div class="flex items-center gap-3 mb-4">
                        <div class="flex items-center justify-center w-7 h-7 rounded-lg bg-primary/10 dark:bg-primary/20 shrink-0">
                            <flux:icon.truck class="w-4 h-4 text-primary dark:text-dark-txt-primary" />
                        </div>
                        <h2 class="font-primary text-section-heading font-bold text-light-txt-primary dark:text-dark-txt-primary">{{ $vehicle_type }}</h2>
                        <span class="font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">({{ $destinations->sum(fn($d) => $d->count()) }} in queue)</span>
                        <div class="flex-1 h-px bg-light-bd-default dark:bg-dark-bd-default"></div>
                    </div>

                    <div class="space-y-4">
                        @foreach ($destinations as $destination => $queues)
                            <flux:card class="overflow-hidden p-0 dark:bg-dark-secondary dark:border-dark-bd-default">
                                <div class="flex items-center justify-between px-4 py-3 border-b border-light-bd-default dark:border-dark-bd-default bg-light-secondary/50 dark:bg-dark-secondary/50">
                                    <h3 class="font-primary text-card-title font-semibold text-light-txt-primary dark:text-dark-txt-primary">{{ $destination }}</h3>
                                </div>

                                <div class="overflow-x-auto">
                                    <flux:table>
                                        <flux:table.columns sticky class="bg-light-secondary/50 items-center bg-light-subtle/50 dark:bg-dark-secondary/50 font-secondary text-nav-label text-light-txt-muted dark:text-dark-txt-muted">
                                            <flux:table.column align="center" class="w-10 px-1! sm:px-2! md:px-4! py-2">#</flux:table.column>
                                            <flux:table.column align="center" class="px-1 sm:px-2 md:px-4 py-2">Plate</flux:table.column>
                                            <flux:table.column align="center" class="hidden sm:table-cell px-1 sm:px-2 md:px-4 py-2">Driver</flux:table.column>
                                            <flux:table.column align="center" class="px-1 sm:px-2 md:px-4 py-2">Seats</flux:table.column>
                                            <flux:table.column align="center" class="px-1 sm:px-2 md:px-4 py-2">Status</flux:table.column>
                                            <flux:table.column align="center" class="px-1! sm:px-2! md:px-4! py-2">Time</flux:table.column>
                                            @if ($this->canManageQueue())
                                                <flux:table.column align="center" class="px-1! sm:px-2! md:px-4! py-2">Actions</flux:table.column>
                                            @endif
                                        </flux:table.columns>

                                        <flux:table.rows>
                                            @forelse ($queues as $index => $queue)
                                                <flux:table.row :key="$queue->id" class="{{ $queue->status === 'loading' ? 'bg-success/5 dark:bg-dark-success/5' : '' }}">
                                                    <flux:table.cell align="center" class="px-1! sm:px-2! md:px-4! py-1.5 md:py-2 font-secondary text-xs md:text-table-row text-light-txt-muted dark:text-dark-txt-muted">
                                                        {{ $index + 1 }}
                                                    </flux:table.cell>

                                                    <flux:table.cell align="center" class="px-1 sm:px-2 md:px-4 py-1.5 md:py-2">
                                                        <span class="font-mono text-xs md:text-table-row tracking-widest font-semibold text-light-txt-primary dark:text-dark-txt-primary">
                                                            {{ $queue->plate_number }}
                                                        </span>
                                                    </flux:table.cell>

                                                    <flux:table.cell align="center" class="hidden sm:table-cell px-1 sm:px-2 md:px-4 py-1.5 md:py-2 font-secondary text-xs md:text-table-row text-light-txt-body dark:text-dark-txt-body">
                                                        {{ $queue->driver_name }}
                                                    </flux:table.cell>

                                                    <flux:table.cell align="center" class="px-1 sm:px-2 md:px-4 py-1.5 md:py-2">
                                                        <div class="flex items-center justify-center gap-1 sm:gap-2">
                                                            <div class="w-10 sm:w-12 h-1.5 rounded-full bg-light-bd-default dark:bg-dark-bd-default overflow-hidden">
                                                                @php $pct = $queue->seat_capacity > 0 ? round(($queue->seat_count / $queue->seat_capacity) * 100) : 0; @endphp
                                                                <div class="h-full rounded-full {{ $pct >= 75 ? 'bg-danger' : 'bg-success' }} transition-all" style="width: {{ $pct }}%"></div>
                                                            </div>
                                                            <span class="font-secondary text-xs md:text-timestamp tabular-nums text-light-txt-muted dark:text-dark-txt-muted whitespace-nowrap">
                                                                {{ $queue->seat_count }}/{{ $queue->seat_capacity }}
                                                            </span>
                                                        </div>
                                                    </flux:table.cell>

                                                    <flux:table.cell align="center" class="px-1 sm:px-2 md:px-4 py-1.5 md:py-2">
                                                        @if ($queue->status === 'loading')
                                                            <span class="inline-flex items-center gap-1.5">
                                                                <span class="relative flex h-2 w-2">
                                                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-success opacity-75"></span>
                                                                    <span class="relative inline-flex rounded-full h-2 w-2 bg-success"></span>
                                                                </span>
                                                                <span class="font-secondary text-xs md:text-table-row font-medium text-success dark:text-dark-success">Boarding</span>
                                                            </span>
                                                        @else
                                                            <span class="font-secondary text-xs md:text-table-row text-light-txt-muted dark:text-dark-txt-muted">Waiting</span>
                                                        @endif
                                                    </flux:table.cell>

                                                    <flux:table.cell align="center" class="px-1! sm:px-2! md:px-4! py-1.5 md:py-2">
                                                        @if ($queue->status === 'loading' && $queue->departs_at)
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
                                                                        if (remaining <= 0) { this.display = '00:00'; this.urgent = false; clearInterval(this.intervalId); return; }
                                                                        this.urgent = remaining < 30000;
                                                                        const m = String(Math.floor(remaining / 60000)).padStart(2, '0');
                                                                        const s = String(Math.floor((remaining % 60000) / 1000)).padStart(2, '0');
                                                                        this.display = m + ':' + s;
                                                                    }
                                                                }"
                                                                x-init="init()"
                                                                :class="urgent
                                                                    ? 'font-mono text-xs font-semibold tracking-widest bg-danger/10 text-danger border border-danger/30 px-1.5 py-0.5 rounded'
                                                                    : 'font-mono text-xs font-semibold tracking-widest bg-warning/10 text-warning border border-warning/30 px-1.5 py-0.5 rounded'"
                                                                x-text="display"
                                                            ></span>
                                                        @else
                                                            <span class="font-mono text-xs tracking-widest text-light-txt-muted dark:text-dark-txt-muted">--:--</span>
                                                        @endif
                                                    </flux:table.cell>

                                                    @if ($this->canManageQueue())
                                                        <flux:table.cell align="center" class="px-1! sm:px-2! md:px-4! py-1.5 md:py-2">
                                                            @if ($queue->status === 'loading')
                                                                <flux:button
                                                                    size="sm"
                                                                    variant="primary"
                                                                    wire:click="confirmDispatch({{ $queue->id }}, '{{ $queue->plate_number }}')"
                                                                    class="font-secondary text-xs scale-90 md:scale-100"
                                                                >
                                                                    Dispatch
                                                                </flux:button>
                                                            @else
                                                                <flux:button
                                                                    size="sm"
                                                                    variant="ghost"
                                                                    disabled
                                                                    class="font-secondary text-xs scale-90 md:scale-100 opacity-50"
                                                                >
                                                                    Dispatch
                                                                </flux:button>
                                                            @endif
                                                        </flux:table.cell>
                                                    @endif
                                                </flux:table.row>
                                            @empty
                                                <flux:table.row>
                                                    <flux:table.cell colspan="{{ $this->canManageQueue() ? 7 : 6 }}" class="px-2 md:px-4 py-4 text-center">
                                                        <span class="font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">No vehicles in this destination.</span>
                                                    </flux:table.cell>
                                                </flux:table.row>
                                            @endforelse
                                        </flux:table.rows>
                                    </flux:table>
                                </div>
                            </flux:card>
                        @endforeach
                    </div>
                </div>
            @empty
                <flux:card class="px-6 py-14 text-center dark:bg-dark-secondary dark:border-dark-bd-default">
                    <p class="font-secondary text-table-row text-light-txt-muted dark:text-dark-txt-muted">No active queue right now.</p>
                </flux:card>
            @endforelse
        </div>
    </div>

    {{-- Log cash fare confirmation --}}
    <flux:modal wire:model="show_cash_fare_modal" :closable="false" class="w-[calc(100%-2rem)] max-w-xs sm:max-w-sm">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Log cash fare?</flux:heading>
                <flux:text class="mt-2">Log 1 passenger who paid their fare to you in cash.</flux:text>
            </div>
            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">Cancel</flux:button>
                </flux:modal.close>
                <flux:button wire:click="reportCashFare({{ $pendingQueueId }})" wire:loading.attr="disabled" variant="primary">
                    Log Passenger
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Depart early confirmation --}}
    <flux:modal wire:model="show_depart_early_modal" :closable="false" class="w-[calc(100%-2rem)] max-w-xs sm:max-w-sm">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Depart early?</flux:heading>
                <flux:text class="mt-2">Are you sure you want to depart early right now?</flux:text>
            </div>
            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">Cancel</flux:button>
                </flux:modal.close>
                <flux:button wire:click="departEarly({{ $pendingQueueId }})" wire:loading.attr="disabled" variant="danger">
                    Depart Now
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Dispatch vehicle confirmation --}}
    <flux:modal wire:model="show_dispatch_modal" :closable="false" class="w-[calc(100%-2rem)] max-w-xs sm:max-w-sm">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Dispatch vehicle?</flux:heading>
                <flux:text class="mt-2">Dispatch {{ $pendingQueuePlate }} now?</flux:text>
            </div>
            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">Cancel</flux:button>
                </flux:modal.close>
                <flux:button wire:click="dispatchVehicle({{ $pendingQueueId }})" wire:loading.attr="disabled" variant="primary">
                    Dispatch
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
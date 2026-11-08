<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;

use App\Enums\RentalRequestStatus;
use App\Models\RentalRequest;
use App\Models\Vehicle;

new #[Layout('layouts.operator-layout')] class extends Component
{
    use WithPagination;

    public Vehicle $vehicle;

    public string $search = '';
    public string $statusFilter = '';

    public function mount(Vehicle $vehicle): void
    {
        abort_if($vehicle->user_id !== auth()->id(), 404);

        $this->vehicle = $vehicle;
    }

    public function updatedSearch() { $this->resetPage(); }
    public function updatedStatusFilter() { $this->resetPage(); }

    public function clearFilters()
    {
        $this->reset(['search', 'statusFilter']);
        $this->resetPage();
    }

    #[Computed]
    public function activeFilterCount()
    {
        return collect([$this->search, $this->statusFilter])
            ->filter(fn ($v) => filled($v))
            ->count();
    }

    /** Every rate this vehicle has, including ones switched off. */
    #[Computed]
    public function rates()
    {
        return $this->vehicle->eventRates()
            ->with('eventType')
            ->get()
            ->sortBy(fn ($rate) => $rate->eventType->name)
            ->values();
    }

    #[Computed]
    public function requestStats(): array
    {
        $counts = RentalRequest::where('vehicle_id', $this->vehicle->id)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $n = fn (RentalRequestStatus $s) => (int) ($counts[$s->value] ?? 0);

        return [
            'total'     => (int) $counts->sum(),
            'pending'   => $n(RentalRequestStatus::Pending),
            'awaiting'  => $n(RentalRequestStatus::Quoted) + $n(RentalRequestStatus::AwaitingPayment),
            'confirmed' => $n(RentalRequestStatus::Confirmed),
            'completed' => $n(RentalRequestStatus::Completed),
        ];
    }

    #[Computed]
    public function requests()
    {
        $status = RentalRequestStatus::tryFrom($this->statusFilter);

        return RentalRequest::query()
            ->with('passenger.userStatus')
            ->where('vehicle_id', $this->vehicle->id)
            ->when($status, fn ($q) => $q->where('status', $status->value))
            ->when($this->search, function ($q) {
                $term = '%' . $this->search . '%';

                $q->where(function ($q2) use ($term) {
                    $q2->whereHas('passenger', fn ($p) => $p->where('name', 'like', $term))
                        ->orWhere('event_name', 'like', $term)
                        ->orWhere('pickup_municipality', 'like', $term)
                        ->orWhere('destination_municipality', 'like', $term);
                });
            })
            // Requests waiting for the operator come first, then newest.
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [RentalRequestStatus::Pending->value])
            ->latest()
            ->paginate(10);
    }
};
?>

<div>
    <x-page-header heading="Rental Details"></x-page-header>

    <div class="flex flex-col-reverse sm:flex-row sm:items-start sm:justify-between gap-3 sm:gap-4 mb-6">
        <div>
            <x-heading
                class="!font-primary !font-bold !text-light-txt-primary dark:!text-dark-txt-primary"
                style="font-size: var(--text-section-heading)"
            >
                Rental details and passenger requests for {{ $vehicle->plate_number }}.
            </x-heading>
        </div>

        <flux:breadcrumbs class="shrink-0 sm:pt-1">
            <flux:breadcrumbs.item href="{{ route('operator.vehicles') }}" wire:navigate>
                Back to my vehicles
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item>Rental details</flux:breadcrumbs.item>
        </flux:breadcrumbs>
    </div>

    {{-- ===================== VEHICLE ===================== --}}
    <flux:card class="mb-5 !p-3 sm:!p-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-4">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 sm:h-11 sm:w-11 items-center justify-center rounded-lg bg-primary/10 dark:bg-primary/20 text-primary dark:text-dark-txt-primary shrink-0">
                    <flux:icon.truck class="w-4 h-4 sm:w-5 sm:h-5" />
                </div>
                <div>
                    <x-text class="font-mono font-medium text-light-txt-primary dark:text-dark-txt-primary text-sm sm:text-base">
                        {{ $vehicle->plate_number }}
                    </x-text>
                    <x-text class="text-xs text-light-txt-muted dark:text-dark-txt-muted">{{ $vehicle->vehicle_type }}</x-text>
                </div>
            </div>

            @php
                $rentalStatus = $vehicle->rental_status;
            @endphp
            @if ($rentalStatus === \App\Enums\RentalStatus::Active)
                <flux:badge color="green" size="sm" class="self-start sm:self-auto font-secondary text-badge text-xs">Active for renting</flux:badge>
            @elseif ($rentalStatus === \App\Enums\RentalStatus::Suspended)
                <flux:badge color="red" size="sm" class="self-start sm:self-auto font-secondary text-badge text-xs">Suspended by admin</flux:badge>
            @else
                <flux:badge color="zinc" size="sm" class="self-start sm:self-auto font-secondary text-badge text-xs">Not active</flux:badge>
            @endif
        </div>

        <div class="mt-4 grid grid-cols-2 sm:grid-cols-4 gap-x-4 gap-y-3">
            @foreach ([
                'Seats'      => $vehicle->total_seats,
                'Driver'     => $vehicle->driver_name,
                'Body no.'   => $vehicle->body_number,
                'Engine no.' => $vehicle->engine_number,
                'Chassis no.' => $vehicle->chassis_number,
                'Route'      => $vehicle->route_list?->terminal ? 'Iriga → ' . $vehicle->route_list->terminal : null,
            ] as $label => $value)
                <div>
                    <span class="block text-[10px] sm:text-xs text-light-txt-muted dark:text-dark-txt-muted uppercase tracking-wider mb-0.5">{{ $label }}</span>
                    <span class="text-sm font-medium text-light-txt-body dark:text-dark-txt-body break-words">{{ $value ?: '—' }}</span>
                </div>
            @endforeach

            <div>
                <span class="block text-[10px] sm:text-xs text-light-txt-muted dark:text-dark-txt-muted uppercase tracking-wider mb-0.5">Franchise</span>
                @php $franchise = $vehicle->documentStatus(); @endphp
                @if ($franchise === 'valid')
                    <span class="text-sm font-medium text-success dark:text-dark-success">Valid</span>
                @elseif ($franchise === 'expiring')
                    <span class="text-sm font-medium text-warning dark:text-dark-warning">Expiring</span>
                @elseif ($franchise === 'expired')
                    <span class="text-sm font-medium text-danger dark:text-dark-danger">Expired</span>
                @else
                    <span class="text-sm font-medium text-light-txt-muted dark:text-dark-txt-muted">Not verified</span>
                @endif
                @if ($vehicle->has_franchise && $vehicle->franchise_expiry_date)
                    <span class="block text-xs text-light-txt-muted dark:text-dark-txt-muted">until {{ $vehicle->franchise_expiry_date->format('M d, Y') }}</span>
                @endif
            </div>
        </div>
    </flux:card>

    {{-- ===================== RATES ===================== --}}
    <flux:card class="mb-5 !p-3 sm:!p-6">
        <div class="flex items-start justify-between gap-3 mb-3">
            <div>
                <x-heading class="!font-primary !font-bold !text-light-txt-primary dark:!text-dark-txt-primary" style="font-size: var(--text-section-heading)">
                    Events and rates
                </x-heading>
                <x-text class="text-xs text-light-txt-muted dark:text-dark-txt-muted">
                    What passengers see for this vehicle.
                </x-text>
            </div>
            <flux:button
                href="{{ route('setup-rental', ['vehicle' => $vehicle->id]) }}"
                wire:navigate
                size="sm"
                variant="ghost"
                icon="pencil-square"
                class="font-secondary shrink-0"
            >
                Edit
            </flux:button>
        </div>

        <div class="divide-y divide-light-bd-default dark:divide-dark-bd-default">
            @forelse ($this->rates as $rate)
                <div wire:key="rate-{{ $rate->id }}" class="flex flex-wrap items-center justify-between gap-2 py-2.5">
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-medium text-light-txt-primary dark:text-dark-txt-primary">{{ $rate->eventType->name }}</span>
                        @unless ($rate->is_active && $rate->eventType->is_active)
                            <flux:badge color="zinc" size="sm" class="font-secondary text-badge text-xs">Off</flux:badge>
                        @endunless
                    </div>
                    <div class="text-xs sm:text-sm text-light-txt-body dark:text-dark-txt-body tabular-nums">
                        Down payment: <strong>{{ \App\Support\Peso::format($rate->down_payment_amount) }}</strong>
                    </div>
                </div>
            @empty
                <x-text class="py-3 text-sm text-light-txt-muted dark:text-dark-txt-muted">
                    No rental events set up yet.
                </x-text>
            @endforelse
        </div>
    </flux:card>

    {{-- ===================== REQUEST STATS ===================== --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
        <flux:card class="p-3 sm:p-4">
            <x-text class="font-secondary text-xs sm:text-stat-label text-light-txt-muted dark:text-dark-txt-muted">New requests</x-text>
            <x-text class="font-primary text-stat-value font-bold block {{ $this->requestStats['pending'] > 0 ? 'text-warning dark:text-dark-warning' : 'text-light-txt-primary dark:text-dark-txt-primary' }}">
                {{ $this->requestStats['pending'] }}
            </x-text>
        </flux:card>
        <flux:card class="p-3 sm:p-4">
            <x-text class="font-secondary text-xs sm:text-stat-label text-light-txt-muted dark:text-dark-txt-muted">Awaiting passenger</x-text>
            <x-text class="font-primary text-stat-value font-bold block text-light-txt-primary dark:text-dark-txt-primary">
                {{ $this->requestStats['awaiting'] }}
            </x-text>
        </flux:card>
        <flux:card class="p-3 sm:p-4">
            <x-text class="font-secondary text-xs sm:text-stat-label text-light-txt-muted dark:text-dark-txt-muted">Confirmed</x-text>
            <x-text class="font-primary text-stat-value font-bold block text-success dark:text-dark-success">
                {{ $this->requestStats['confirmed'] }}
            </x-text>
        </flux:card>
        <flux:card class="p-3 sm:p-4">
            <x-text class="font-secondary text-xs sm:text-stat-label text-light-txt-muted dark:text-dark-txt-muted">Completed</x-text>
            <x-text class="font-primary text-stat-value font-bold block text-light-txt-primary dark:text-dark-txt-primary">
                {{ $this->requestStats['completed'] }}
            </x-text>
        </flux:card>
    </div>

    {{-- ===================== SEARCH & FILTER ===================== --}}
    <div class="flex flex-col sm:flex-row sm:items-center gap-3 mb-4">
        <div class="flex-1">
            <flux:input
                wire:model.live.debounce.300ms="search"
                placeholder="Search passenger, event, or town..."
                class="w-full font-secondary text-table-row dark:bg-dark-secondary dark:border-dark-bd-default dark:text-dark-txt-primary"
                icon="magnifying-glass"
            />
        </div>

        <div class="flex flex-wrap sm:flex-nowrap items-stretch sm:items-center gap-2 w-full sm:w-auto">
            <flux:select
                wire:model.live="statusFilter"
                size="sm"
                placeholder="All statuses"
                class="w-full sm:w-48 font-secondary text-table-row dark:bg-dark-secondary dark:border-dark-bd-default dark:text-dark-txt-primary"
            >
                <flux:select.option value="">All statuses</flux:select.option>
                @foreach (\App\Enums\RentalRequestStatus::cases() as $case)
                    <flux:select.option value="{{ $case->value }}">{{ $case->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            @if ($this->activeFilterCount > 0)
                <flux:button wire:click="clearFilters" size="sm" variant="ghost" icon="x-mark" class="font-secondary shrink-0">
                    Clear
                </flux:button>
            @endif
        </div>
    </div>

    {{-- ===================== REQUESTS ===================== --}}
    <flux:card class="mb-4 p-0! overflow-hidden">
        <div class="overflow-x-auto">
            <flux:table container:class="md:max-h-160">
                <flux:table.columns sticky class="bg-light-secondary/50 items-center bg-light-subtle/50 dark:bg-dark-secondary/50 font-secondary text-nav-label text-light-txt-muted dark:text-dark-txt-muted">
                    <flux:table.column align="center" class="px-2 md:px-4 py-2">Passenger</flux:table.column>
                    <flux:table.column align="center" class="px-2 md:px-4 py-2">Event</flux:table.column>
                    <flux:table.column align="center" class="hidden md:table-cell px-2 md:px-4 py-2">Route</flux:table.column>
                    <flux:table.column align="center" class="hidden sm:table-cell px-2 md:px-4 py-2">Trip</flux:table.column>
                    <flux:table.column align="center" class="hidden sm:table-cell px-2 md:px-4 py-2">Passengers</flux:table.column>
                    <flux:table.column align="center" class="hidden md:table-cell px-2 md:px-4 py-2">Full Payment</flux:table.column>
                    <flux:table.column align="center" class="px-2 md:px-4 py-2">Status</flux:table.column>
                    <flux:table.column align="center" class="px-2 md:px-4 py-2">Action</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($this->requests as $request)
                        <flux:table.row :key="$request->id">
                            <flux:table.cell align="center" class="px-2 md:px-4 py-1.5 md:py-2 font-secondary text-xs md:text-table-row text-light-txt-body dark:text-dark-txt-body">
                                {{ ($request->passenger && ! $request->passenger->isDeleted()) ? $request->passenger->name : 'Deleted User' }}
                                <span class="block text-[10px] md:text-xs text-light-txt-muted dark:text-dark-txt-muted">
                                    {{ $request->created_at->diffForHumans() }}
                                </span>
                            </flux:table.cell>

                            <flux:table.cell align="center" class="px-2 md:px-4 py-1.5 md:py-2 font-secondary text-xs md:text-table-row text-light-txt-body dark:text-dark-txt-body">
                                {{ $request->event_name }}
                            </flux:table.cell>

                            <flux:table.cell align="center" class="hidden md:table-cell px-2 md:px-4 py-1.5 md:py-2 font-secondary text-xs md:text-timestamp text-light-txt-body dark:text-dark-txt-body">
                                {{ $request->pickup_municipality }} → {{ $request->destination_municipality }}
                                <span class="block max-w-[16rem] truncate text-xs text-light-txt-muted dark:text-dark-txt-muted">
                                    {{ $request->pickup_details }} → {{ $request->destination_details }}
                                </span>
                            </flux:table.cell>

                            <flux:table.cell align="center" class="hidden sm:table-cell px-2 md:px-4 py-1.5 md:py-2 font-secondary text-xs md:text-timestamp text-light-txt-muted dark:text-dark-txt-muted">
                                {{ $request->trip_start_at->format('M d, Y') }}
                                <span class="block text-xs">{{ $request->trip_start_at->format('g:i A') }} – {{ $request->trip_end_at->format($request->trip_end_at->isSameDay($request->trip_start_at) ? 'g:i A' : 'M d, g:i A') }}</span>
                            </flux:table.cell>

                            <flux:table.cell align="center" class="hidden sm:table-cell px-2 md:px-4 py-1.5 md:py-2 font-secondary text-xs md:text-table-row text-light-txt-body dark:text-dark-txt-body tabular-nums">
                                {{ $request->passenger_count }}
                            </flux:table.cell>

                            <flux:table.cell align="center" class="hidden md:table-cell px-2 md:px-4 py-1.5 md:py-2 font-secondary text-xs md:text-table-row text-light-txt-body dark:text-dark-txt-body tabular-nums">
                                {{ $request->quoted_total !== null ? '₱' . number_format((float) $request->quoted_total, 2) : '—' }}
                            </flux:table.cell>

                            <flux:table.cell align="center" class="px-2 md:px-4 py-1.5 md:py-2">
                                @php
                                    $badgeColor = match ($request->status) {
                                        \App\Enums\RentalRequestStatus::Pending         => 'yellow',
                                        \App\Enums\RentalRequestStatus::Quoted          => 'blue',
                                        \App\Enums\RentalRequestStatus::AwaitingPayment => 'orange',
                                        \App\Enums\RentalRequestStatus::Confirmed       => 'green',
                                        \App\Enums\RentalRequestStatus::Completed       => 'zinc',
                                        default                                         => 'red',
                                    };
                                @endphp
                                <flux:badge :color="$badgeColor" size="sm" class="font-secondary text-badge text-xs">
                                    {{ $request->status->label() }}
                                </flux:badge>
                            </flux:table.cell>

                            <flux:table.cell align="center" class="px-2 md:px-4 py-1.5 md:py-2">
                                <flux:button
                                    href="{{ route('rental-request-review', ['rentalRequest' => $request->id]) }}"
                                    wire:navigate
                                    size="sm"
                                    :variant="$request->status === \App\Enums\RentalRequestStatus::Pending ? 'primary' : 'ghost'"
                                    class="font-secondary"
                                >
                                    {{ $request->status === \App\Enums\RentalRequestStatus::Pending ? 'Review' : 'View' }}
                                </flux:button>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="8" class="px-2 md:px-4 py-4">
                                <div class="flex flex-col items-center justify-center py-6 md:py-12 gap-2">
                                    <flux:icon.archive-box class="w-6 h-6 md:w-8 md:h-8 text-light-txt-muted dark:text-dark-txt-muted" />
                                    <x-text class="font-secondary text-sm md:text-table-row text-light-txt-muted dark:text-dark-txt-muted">
                                        No rental requests yet.
                                    </x-text>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>

        @if ($this->requests->hasPages())
            <div class="flex flex-wrap items-center justify-end gap-2 px-3 sm:px-4 py-2 border-t border-light-bd-default dark:border-dark-bd-default bg-light-secondary dark:bg-dark-secondary">
                {{ $this->requests->links() }}
            </div>
        @endif
    </flux:card>
</div>
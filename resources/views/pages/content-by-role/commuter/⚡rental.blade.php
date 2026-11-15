<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;

use Flux\Flux;
use App\Services\RentalNotifier;
use Illuminate\Support\Facades\Log;
use App\Enums\RentalRequestStatus;
use App\Models\RentalEventType;
use App\Models\RentalRequest;
use App\Models\Vehicle;
use App\Services\RentalRequestService;

new #[Layout('layouts.commuter-layout')] class extends Component
{
    // ---- list ----
    public string $search = '';
    public string $eventFilter = '';

    // ---- selection (right panel) ----
    public ?int $selectedVehicleId = null;

    // ---- request form (modal) ----
    public string $rateId = '';
    public string $pickupMunicipality = '';
    public string $pickupBarangay = '';
    public string $pickupDetails = '';
    public string $destinationMunicipality = '';
    public string $destinationBarangay = '';
    public string $destinationDetails = '';
    public string $tripStartAt = '';
    public string $tripEndAt = '';
    public string $passengerCount = '';
    public string $notes = '';

    public function clearFilters(): void
    {
        $this->reset(['search', 'eventFilter']);
    }

    #[Computed]
    public function activeFilterCount(): int
    {
        return collect([$this->search, $this->eventFilter])->filter(fn ($v) => filled($v))->count();
    }

    /** Event names offered by at least one vehicle on this page (operators' own services included). */
    #[Computed]
    public function eventNames()
    {
        return RentalEventType::active()
            ->whereHas('vehicleRates', fn ($rate) => $rate->offered()->whereHas('vehicle', fn ($v) => $v->rentable()))
            ->pluck('name')
            ->unique()
            ->sort()
            ->values();
    }

    /** Vehicles operators have switched to Active, with at least one offered event. */
    #[Computed]
    public function vehicles()
    {
        return Vehicle::rentable()
            ->with(['user:id,name', 'offeredEventRates.eventType'])
            ->when($this->search, function ($q, $s) {
                $q->where(function ($q2) use ($s) {
                    $q2->where('plate_number', 'like', "%{$s}%")
                        ->orWhere('vehicle_type', 'like', "%{$s}%");
                });
            })
            ->when($this->eventFilter, function ($q, $name) {
                $q->whereHas('eventRates', fn ($r) => $r->offered()->whereHas('eventType', fn ($t) => $t->where('name', $name)));
            })
            ->orderBy('plate_number')
            ->get();
    }

    #[Computed]
    public function selected(): ?Vehicle
    {
        return $this->selectedVehicleId
            ? $this->vehicles->firstWhere('id', $this->selectedVehicleId)
            : null;
    }

    /**
     * Up to 5 photos of the selected vehicle, its chosen picture first.
     *
     * @return list<array{path: string, url: string}>
     */
    #[Computed]
    public function gallery(): array
    {
        $vehicle = $this->selected;

        if (! $vehicle) {
            return [];
        }

        $paths = array_slice($vehicle->images ?? [], 0, 5);

        if ($vehicle->profile_image && in_array($vehicle->profile_image, $paths, true)) {
            $paths = array_values(array_unique(array_merge([$vehicle->profile_image], $paths)));
        }

        return collect($paths)
            ->map(fn ($path) => ['path' => $path, 'url' => asset('storage/' . $path)])
            ->values()
            ->all();
    }

    /**
     * Dates that are already confirmed or on hold, per vehicle, as ready-to-print strings.
     * No passenger information is exposed here.
     *
     * @return array<int, list<string>>
     */
    #[Computed]
    public function blockedByVehicle(): array
    {
        return RentalRequest::whereIn('vehicle_id', $this->vehicles->pluck('id'))
            ->blockingDates()
            ->where('trip_end_at', '>=', now())
            ->orderBy('trip_start_at')
            ->get(['vehicle_id', 'trip_start_at', 'trip_end_at'])
            ->groupBy('vehicle_id')
            ->map(fn ($rows) => $rows->map(fn ($r) => $r->trip_start_at->isSameDay($r->trip_end_at)
                ? $r->trip_start_at->format('M d, Y')
                : $r->trip_start_at->format('M d') . ' – ' . $r->trip_end_at->format('M d, Y'))->all())
            ->all();
    }

    /**
     * Same blocked bookings as above, but as plain Y-m-d day ranges for the calendar picker.
     * No passenger information is exposed here either.
     *
     * @return array<int, list<array{start: string, end: string}>>
     */
    #[Computed]
    public function blockedRangesByVehicle(): array
    {
        return RentalRequest::whereIn('vehicle_id', $this->vehicles->pluck('id'))
            ->blockingDates()
            ->where('trip_end_at', '>=', now())
            ->orderBy('trip_start_at')
            ->get(['vehicle_id', 'trip_start_at', 'trip_end_at'])
            ->groupBy('vehicle_id')
            ->map(fn ($rows) => $rows->map(fn ($r) => [
                'start' => $r->trip_start_at->format('Y-m-d'),
                'end'   => $r->trip_end_at->format('Y-m-d'),
            ])->values()->all())
            ->all();
    }

    /** Quotes to answer, or payments to make (not yet lapsed). */
    #[Computed]
    public function actionCount(): int
    {
        return RentalRequest::forPassenger(auth()->user())
            ->where(function ($q) {
                $q->where(fn ($q2) => $q2
                        ->where('status', RentalRequestStatus::Quoted->value)
                        ->where('quote_expires_at', '>', now()))
                    ->orWhere(fn ($q2) => $q2
                        ->where('status', RentalRequestStatus::AwaitingPayment->value)
                        ->where('hold_expires_at', '>', now()));
            })
            ->count();
    }

    public function selectVehicle(int $id): void
    {
        // Only vehicles that are actually listed can be selected.
        if (! $this->vehicles->contains('id', $id)) {
            return;
        }

        if ($this->selectedVehicleId !== $id) {
            $this->resetForm();
        }

        $this->selectedVehicleId = $id;
    }

    protected function rules(): array
    {
        return [
            'rateId'                  => ['required', 'integer'],
            'pickupMunicipality'      => ['required', 'string', 'max:100'],
            'pickupBarangay'          => ['nullable', 'string', 'max:100'],
            'pickupDetails'           => ['required', 'string', 'max:255'],
            'destinationMunicipality' => ['required', 'string', 'max:100'],
            'destinationBarangay'     => ['nullable', 'string', 'max:100'],
            'destinationDetails'      => ['required', 'string', 'max:255'],
            'tripStartAt'             => ['required', 'date', 'after:now'],
            'tripEndAt'               => ['required', 'date', 'after:tripStartAt'],
            'passengerCount'          => ['required', 'integer', 'min:1', 'max:' . ($this->selected?->total_seats ?? 1)],
            'notes'                   => ['nullable', 'string', 'max:500'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'rateId'                  => 'event',
            'pickupMunicipality'      => 'pick-up town or city',
            'pickupDetails'           => 'pick-up address or landmark',
            'destinationMunicipality' => 'destination town or city',
            'destinationDetails'      => 'destination address or landmark',
            'tripStartAt'             => 'trip start',
            'tripEndAt'               => 'trip end',
            'passengerCount'          => 'number of passengers',
        ];
    }

    public function sendRequest(RentalRequestService $service, RentalNotifier $notifier): void
    {
        $vehicle = $this->selected;

        if (! $vehicle) {
            $this->addError('form', 'This vehicle is no longer available. Please pick another one.');
            return;
        }

        $this->validate();

        try {
            $rentalRequest = $service->create(auth()->user(), $vehicle, (int) $this->rateId, [
                'pickup_municipality'      => $this->pickupMunicipality,
                'pickup_barangay'          => $this->pickupBarangay,
                'pickup_details'           => $this->pickupDetails,
                'destination_municipality' => $this->destinationMunicipality,
                'destination_barangay'     => $this->destinationBarangay,
                'destination_details'      => $this->destinationDetails,
                'trip_start_at'            => $this->tripStartAt,
                'trip_end_at'              => $this->tripEndAt,
                'passenger_count'          => (int) $this->passengerCount,
                'notes'                    => $this->notes,
            ]);
        } catch (\DomainException $e) {
            // Business-rule failures (dates taken, too many open requests, ...).
            $this->addError('form', $e->getMessage());
            return;
        }

        try {
            $notifier->newRequest($rentalRequest);
        } catch (\Throwable $e) {
            Log::error('Rental notification failed', [
                'request_id' => $rentalRequest->id,
                'error'      => $e->getMessage(),
            ]);
        }

        $this->resetForm();
        Flux::modal('send-request')->close();

        Flux::toast(
            variant: 'success',
            heading: 'Request sent',
            duration: 4000,
            text: 'The operator will review it and send you a price. You pay only after you accept it.',
        );
    }

    private function resetForm(): void
    {
        $this->reset([
            'rateId', 'pickupMunicipality', 'pickupBarangay', 'pickupDetails',
            'destinationMunicipality', 'destinationBarangay', 'destinationDetails',
            'tripStartAt', 'tripEndAt', 'passengerCount', 'notes',
        ]);

        $this->resetValidation();
    }
};
?>

<div>
    <x-page-header heading="Rent a Vehicle"></x-page-header>

    <div class="mb-6 flex items-center justify-between gap-3">
        <div class="min-w-0">
            <x-heading
                class="!font-primary !font-bold !text-light-txt-primary dark:!text-dark-txt-primary"
                style="font-size: var(--text-section-heading)"
            >
                Vehicles available for rent
            </x-heading>
            <x-text class="mt-1 text-sm text-light-txt-muted dark:text-dark-txt-muted">
                Pick a vehicle, send a request, and the operator will reply with a price.
            </x-text>
        </div>

        <flux:button
            href="{{ route('my-rental-requests') }}"
            wire:navigate
            variant="primary"
            icon="inbox-stack"
            class="shrink-0 font-secondary"
        >
            My requests
            @if ($this->actionCount > 0)
                <span class="ml-1.5 inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-white/90 px-1 text-xs font-bold text-primary">
                    {{ $this->actionCount }}
                </span>
            @endif
        </flux:button>
    </div>

    {{-- ===================== SEARCH & FILTER ===================== --}}
    <div class="flex flex-col sm:flex-row sm:items-center gap-3 mb-4">
        <div class="flex-1">
            <flux:input
                wire:model.live.debounce.300ms="search"
                placeholder="Search plate number or vehicle type..."
                class="w-full font-secondary text-table-row dark:bg-dark-secondary dark:border-dark-bd-default dark:text-dark-txt-primary"
                icon="magnifying-glass"
            />
        </div>

        <div class="flex flex-wrap sm:flex-nowrap items-stretch sm:items-center gap-2 w-full sm:w-auto">
            <flux:select
                wire:model.live="eventFilter"
                size="sm"
                placeholder="All events"
                class="w-full sm:w-52 font-secondary text-table-row dark:bg-dark-secondary dark:border-dark-bd-default dark:text-dark-txt-primary"
            >
                <flux:select.option value="">All events</flux:select.option>
                @foreach ($this->eventNames as $eventName)
                    <flux:select.option value="{{ $eventName }}">{{ $eventName }}</flux:select.option>
                @endforeach
            </flux:select>

            @if ($this->activeFilterCount > 0)
                <flux:button wire:click="clearFilters" size="sm" variant="ghost" icon="x-mark" class="font-secondary shrink-0">
                    Clear
                </flux:button>
            @endif
        </div>
    </div>

    <div
        x-data
        class="grid grid-cols-1 lg:grid-cols-10 gap-4 items-start"
    >
        {{-- ===================== LEFT: LIST (70%) ===================== --}}
        <div class="lg:col-span-7 space-y-3">
            @forelse ($this->vehicles as $vehicle)
                @php
                    $isSelected = $this->selectedVehicleId === $vehicle->id;
                    $blocked    = $this->blockedByVehicle[$vehicle->id] ?? [];
                    $thumbUrl   = $vehicle->profile_image && in_array($vehicle->profile_image, $vehicle->images ?? [], true)
                        ? asset('storage/' . $vehicle->profile_image)
                        : null;
                @endphp

                <div
                    wire:key="vehicle-{{ $vehicle->id }}"
                    wire:click="selectVehicle({{ $vehicle->id }})"
                    x-on:click="$nextTick(() => window.innerWidth < 1024 && $refs.detail?.scrollIntoView({ behavior: 'smooth', block: 'start' }))"
                    role="button"
                    tabindex="0"
                    x-on:keydown.enter="$el.click()"
                    class="cursor-pointer rounded-xl transition focus:outline-none focus-visible:ring-2 focus-visible:ring-primary {{ $isSelected ? 'ring-2 ring-primary' : 'hover:ring-1 hover:ring-light-bd-default dark:hover:ring-dark-bd-default' }}"
                >
                    <flux:card class="!p-3 sm:!p-5">
                        <div class="flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-5">
                            <div class="flex items-center gap-3 sm:w-56 shrink-0">
                                @if ($thumbUrl)
                                    <img
                                        src="{{ $thumbUrl }}"
                                        alt="Picture of {{ $vehicle->plate_number }}"
                                        loading="lazy"
                                        class="h-14 w-14 sm:h-16 sm:w-16 rounded-lg border border-light-bd-default dark:border-dark-bd-default object-cover shrink-0"
                                    />
                                @else
                                    <div class="flex h-14 w-14 sm:h-16 sm:w-16 items-center justify-center rounded-lg bg-primary/10 dark:bg-primary/20 text-primary dark:text-dark-txt-primary shrink-0">
                                        <flux:icon.truck class="w-5 h-5 sm:w-6 sm:h-6" />
                                    </div>
                                @endif
                                <div class="min-w-0">
                                    <x-text class="font-mono font-medium text-light-txt-primary dark:text-dark-txt-primary text-sm sm:text-base">
                                        {{ $vehicle->plate_number }}
                                    </x-text>
                                    <x-text class="text-xs text-light-txt-muted dark:text-dark-txt-muted">
                                        {{ $vehicle->vehicle_type }} · {{ $vehicle->total_seats }} seats
                                    </x-text>
                                </div>
                            </div>

                            <div class="flex-1 min-w-0">
                                <div class="flex flex-wrap items-center gap-1.5">
                                    @foreach ($vehicle->offeredEventRates->take(3) as $rate)
                                        <flux:badge size="sm" class="font-secondary text-badge text-xs">{{ $rate->eventType->name }}</flux:badge>
                                    @endforeach
                                    @if ($vehicle->offeredEventRates->count() > 3)
                                        <span class="text-xs text-light-txt-muted dark:text-dark-txt-muted">
                                            +{{ $vehicle->offeredEventRates->count() - 3 }} more
                                        </span>
                                    @endif
                                </div>
                            </div>

                            <div class="sm:text-right shrink-0">
                                @if (count($blocked) > 0)
                                    <flux:badge color="orange" size="sm" class="font-secondary text-badge text-xs">Some dates booked</flux:badge>
                                @else
                                    <flux:badge color="green" size="sm" class="font-secondary text-badge text-xs">Open dates</flux:badge>
                                @endif
                            </div>
                        </div>
                    </flux:card>
                </div>
            @empty
                <flux:card class="!p-6">
                    <div class="flex flex-col items-center justify-center py-8 gap-2 text-center">
                        <flux:icon.truck class="w-8 h-8 text-light-txt-muted dark:text-dark-txt-muted" />
                        <x-text class="text-sm text-light-txt-muted dark:text-dark-txt-muted">
                            @if ($this->activeFilterCount > 0)
                                No vehicles match your search.
                            @else
                                No vehicles are available for rent right now.
                            @endif
                        </x-text>
                    </div>
                </flux:card>
            @endforelse
        </div>

        {{-- ===================== RIGHT: DETAILS (30%) ===================== --}}
        <div x-ref="detail" class="lg:col-span-3 lg:sticky lg:top-4 scroll-mt-4">
            <flux:card class="!p-4 sm:!p-5">
                @if ($this->selected)
                    @php
                        $v       = $this->selected;
                        $blocked = $this->blockedByVehicle[$v->id] ?? [];
                    @endphp

                    <div wire:key="detail-{{ $v->id }}" class="flex flex-col gap-4">
                        <div class="flex items-center gap-3">
                            <div class="flex h-11 w-11 items-center justify-center rounded-lg bg-primary/10 dark:bg-primary/20 text-primary dark:text-dark-txt-primary shrink-0">
                                <flux:icon.truck class="w-5 h-5" />
                            </div>
                            <div class="min-w-0">
                                <x-text class="font-mono font-semibold text-light-txt-primary dark:text-dark-txt-primary">{{ $v->plate_number }}</x-text>
                                <x-text class="text-xs text-light-txt-muted dark:text-dark-txt-muted">{{ $v->vehicle_type }}</x-text>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <span class="block text-[10px] sm:text-xs text-light-txt-muted dark:text-dark-txt-muted uppercase tracking-wider mb-0.5">Seats</span>
                                <span class="text-sm font-medium text-light-txt-body dark:text-dark-txt-body">{{ $v->total_seats }}</span>
                            </div>
<!--                             <div>
                                <span class="block text-[10px] sm:text-xs text-light-txt-muted dark:text-dark-txt-muted uppercase tracking-wider mb-0.5">Operator</span>
                                <span class="text-sm font-medium text-light-txt-body dark:text-dark-txt-body break-words">{{ $v->user?->name ?? '—' }}</span>
                            </div> -->
                        </div>

                        {{-- Photos (up to 5). Click one to open the viewer. --}}
                        @php
                            $photos = $this->gallery;
                            $count  = count($photos);
                        @endphp
                        @if ($count > 0)
                            <div>
                                <span class="block text-[10px] sm:text-xs text-light-txt-muted dark:text-dark-txt-muted uppercase tracking-wider mb-1.5">Photos</span>

                                <div class="grid grid-cols-5 gap-1.5">
                                    @foreach ($photos as $photo)
                                        <button
                                            type="button"
                                            wire:key="photo-{{ md5($photo['path']) }}"
                                            x-on:click="$dispatch('open-gallery', { path: @js($photo['path']) }); $flux.modal('vehicle-gallery').show()"
                                            class="overflow-hidden rounded-md border border-light-bd-default dark:border-dark-bd-default focus:outline-none focus-visible:ring-2 focus-visible:ring-primary aspect-square"
                                            aria-label="View photo {{ $loop->iteration }} of {{ $count }}"
                                        >
                                            <img src="{{ $photo['url'] }}" alt="" loading="lazy" class="h-full w-full object-cover transition hover:scale-105" />
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <div>
                            <span class="block text-[10px] sm:text-xs text-light-txt-muted dark:text-dark-txt-muted uppercase tracking-wider mb-1.5">Events and Down payments</span>

                            <div class="divide-y divide-light-bd-default dark:divide-dark-bd-default">
                                @foreach ($v->offeredEventRates as $rate)
                                    <div wire:key="rate-{{ $rate->id }}" class="py-2">
                                        <div class="text-sm font-medium text-light-txt-primary dark:text-dark-txt-primary">{{ $rate->eventType->name }}</div>
                                        @if ($rate->eventType->description)
                                            <div class="text-xs text-light-txt-muted dark:text-dark-txt-muted">{{ $rate->eventType->description }}</div>
                                        @endif
                                        <div class="mt-1 text-xs text-light-txt-body dark:text-dark-txt-body tabular-nums">
                                            Down payment: <strong>{{ \App\Support\Peso::format($rate->down_payment_amount) }}</strong>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div>
                            <span class="text-sm font-medium text-light-txt-primary dark:text-dark-txt-primary">Booked dates:</span>
                            @if (count($blocked) > 0)
                                <ul class="space-y-1 text-sm text-light-txt-body dark:text-dark-txt-body">
                                    @foreach ($blocked as $range)
                                        <li class="flex items-center gap-2">
                                            <flux:icon.calendar-days class="w-4 h-4 text-warning dark:text-dark-warning shrink-0" />
                                            {{ $range }}
                                        </li>
                                    @endforeach
                                </ul>
                            @else
                                <x-text class="text-sm text-light-txt-muted dark:text-dark-txt-muted">No booked dates. All dates are open.</x-text>
                            @endif
                        </div>

                        <div class="rounded-lg bg-light-subtle dark:bg-dark-subtle p-3 text-xs text-light-txt-body dark:text-dark-txt-body space-y-1">
                            <p class="font-medium text-light-txt-primary dark:text-dark-txt-primary">How it works</p>
                            <p>1. Send a request with your trip details.</p>
                            <p>2. The operator replies with a total price.</p>
                            <p>3. Accept the price, then pay the first payment online to confirm. The balance is paid in cash.</p>
                        </div>

                        <flux:modal.trigger name="send-request">
                            <flux:button variant="primary" class="w-full justify-center font-secondary">
                                Send Request
                            </flux:button>
                        </flux:modal.trigger>
                    </div>
                @else
                    <div class="flex flex-col items-center justify-center py-10 gap-2 text-center">
                        <flux:icon.cursor-arrow-rays class="w-8 h-8 text-light-txt-muted dark:text-dark-txt-muted" />
                        <x-text class="text-sm text-light-txt-muted dark:text-dark-txt-muted">
                            Select a vehicle to see its full details.
                        </x-text>
                    </div>
                @endif
            </flux:card>
        </div>
    </div>

    {{-- ===================== SEND REQUEST MODAL ===================== --}}
    @if ($this->selected)
        <flux:modal
            name="send-request"
            class="w-[calc(100%-2rem)] md:w-[64rem] md:max-w-5xl mx-auto rounded-xl"
        >
            <form wire:submit="sendRequest" class="flex flex-col gap-5 max-h-[80vh] overflow-y-auto p-1">
                <div>
                    <flux:heading size="xl" class="!font-primary !font-bold text-light-txt-primary dark:text-dark-txt-primary">
                        Send rental request
                    </flux:heading>
                    <flux:text class="mt-1 font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">
                        {{ $this->selected->plate_number }} · {{ $this->selected->vehicle_type }}. The operator will review your request and reply with a price.
                    </flux:text>
                </div>

                @error('form')
                    <div class="rounded-lg border border-danger/40 bg-danger/10 dark:bg-dark-danger/10 p-3 text-sm text-danger dark:text-dark-danger">
                        {{ $message }}
                    </div>
                @enderror

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 md:gap-8">
                    {{-- ===== LEFT: trip details ===== --}}
                    <div class="flex flex-col gap-5">
                        <flux:select wire:model="rateId" label="Event" placeholder="Choose an event">
                            @foreach ($this->selected->offeredEventRates as $rate)
                                <flux:select.option value="{{ $rate->id }}">
                                    {{ $rate->eventType->name }} — first payment {{ \App\Support\Peso::format($rate->down_payment_amount) }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>

                        <div class="space-y-3">
                            <p class="text-sm font-medium text-light-txt-primary dark:text-dark-txt-primary">From (Point A)</p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <flux:input wire:model="pickupMunicipality" label="Town or city" placeholder="e.g. Iriga City" />
                                <flux:input wire:model="pickupBarangay" label="Barangay (optional)" />
                            </div>
                            <flux:input wire:model="pickupDetails" label="Street or landmark" placeholder="e.g. In front of the church" />
                        </div>

                        <div class="space-y-3">
                            <p class="text-sm font-medium text-light-txt-primary dark:text-dark-txt-primary">To (Point B)</p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <flux:input wire:model="destinationMunicipality" label="Town or city" placeholder="e.g. Legazpi City" />
                                <flux:input wire:model="destinationBarangay" label="Barangay (optional)" />
                            </div>
                            <flux:input wire:model="destinationDetails" label="Street or landmark" placeholder="e.g. Albay Cathedral" />
                        </div>

                        <flux:input
                            wire:model="passengerCount"
                            type="number"
                            min="1"
                            max="{{ $this->selected->total_seats }}"
                            label="Number of passengers"
                            description="This vehicle seats up to {{ $this->selected->total_seats }}."
                        />
                    </div>

                    {{-- ===== RIGHT: date, time and notes ===== --}}
                    <div class="flex flex-col gap-5">
                        {{-- ---------- Trip dates: calendar range picker ---------- --}}
                        <div
                            wire:key="trip-calendar-{{ $this->selected->id }}"
                            x-data="{
                                blocked: @js($this->blockedRangesByVehicle[$this->selected->id] ?? []),
                                blockedSet: new Set(),
                                start: null,
                                end: null,
                                startTime: '08:00',
                                endTime: '17:00',
                                year: new Date().getFullYear(),
                                month: new Date().getMonth(),
                                warn: '',
                                months: ['January','February','March','April','May','June','July','August','September','October','November','December'],
                                init() {
                                    this.blocked.forEach(r => {
                                        let d = this.parse(r.start);
                                        const last = this.parse(r.end);
                                        while (d <= last) { this.blockedSet.add(this.fmt(d)); d.setDate(d.getDate() + 1); }
                                    });
                                    this.$wire.$watch('tripStartAt', v => { if (!v) { this.start = null; this.end = null; this.warn = ''; } });
                                },
                                pad(n) { return String(n).padStart(2, '0'); },
                                fmt(d) { return d.getFullYear() + '-' + this.pad(d.getMonth() + 1) + '-' + this.pad(d.getDate()); },
                                parse(k) { const [y, m, d] = k.split('-').map(Number); return new Date(y, m - 1, d, 12); },
                                get today() { return this.fmt(new Date()); },
                                get canGoBack() { const n = new Date(); return this.year > n.getFullYear() || this.month > n.getMonth(); },
                                get cells() {
                                    const lead = new Date(this.year, this.month, 1).getDay();
                                    const days = new Date(this.year, this.month + 1, 0).getDate();
                                    const out = Array(lead).fill(null);
                                    for (let d = 1; d <= days; d++) out.push(this.fmt(new Date(this.year, this.month, d)));
                                    return out;
                                },
                                prev() { if (!this.canGoBack) return; this.month--; if (this.month < 0) { this.month = 11; this.year--; } },
                                next() { this.month++; if (this.month > 11) { this.month = 0; this.year++; } },
                                isPast(k) { return k < this.today; },
                                isBlocked(k) { return this.blockedSet.has(k); },
                                isDisabled(k) { return this.isPast(k) || this.isBlocked(k); },
                                hasBlockedBetween(a, b) {
                                    let d = this.parse(a); const last = this.parse(b);
                                    while (d <= last) { if (this.isBlocked(this.fmt(d))) return true; d.setDate(d.getDate() + 1); }
                                    return false;
                                },
                                inRange(k) { return this.start && this.end && k > this.start && k < this.end; },
                                pick(k) {
                                    if (this.isDisabled(k)) return;
                                    this.warn = '';
                                    if (!this.start || this.end) { this.start = k; this.end = null; }
                                    else if (k < this.start) { this.start = k; }
                                    else if (this.hasBlockedBetween(this.start, k)) {
                                        this.warn = 'Some days in that range are already booked. Pick dates that do not include them.';
                                        this.start = k; this.end = null;
                                    }
                                    else { this.end = k; }
                                    this.sync();
                                },
                                sync() {
                                    this.$wire.tripStartAt = this.start ? this.start + 'T' + this.startTime : '';
                                    this.$wire.tripEndAt = this.end ? this.end + 'T' + this.endTime : '';
                                },
                                clear() { this.start = null; this.end = null; this.warn = ''; this.sync(); },
                                label(k) { const d = this.parse(k); return this.months[d.getMonth()].slice(0, 3) + ' ' + d.getDate() + ', ' + d.getFullYear(); },
                                get summary() {
                                    if (!this.start) return 'Select your trip start date';
                                    if (!this.end) return this.label(this.start) + ' → select end date (tap the same day for a 1-day trip)';
                                    const n = Math.round((this.parse(this.end) - this.parse(this.start)) / 86400000) + 1;
                                    return this.label(this.start) + ' → ' + this.label(this.end) + ' · ' + n + (n === 1 ? ' day' : ' days');
                                },
                                dayClass(k) {
                                    if (this.isPast(k)) return 'text-zinc-300 dark:text-zinc-600 cursor-not-allowed';
                                    if (this.isBlocked(k)) return 'bg-red-50 text-red-400 line-through decoration-red-300 cursor-not-allowed dark:bg-red-500/10 dark:text-red-400/70 dark:decoration-red-500/40';
                                    if (k === this.start || k === this.end) return 'bg-primary text-white font-semibold shadow-sm';
                                    if (this.inRange(k)) return 'bg-primary/10 text-primary dark:bg-primary/25 dark:text-white rounded-none';
                                    if (k === this.today) return 'ring-1 ring-primary/50 text-light-txt-primary dark:text-dark-txt-primary hover:bg-primary/10';
                                    return 'text-light-txt-primary dark:text-dark-txt-primary hover:bg-primary/10';
                                },
                            }"
                            class="space-y-3"
                        >
                            <div class="flex items-center justify-between">
                                <p class="text-sm font-medium text-light-txt-primary dark:text-dark-txt-primary">Trip dates</p>
                                <button type="button" x-show="start" x-cloak x-on:click="clear()" class="text-xs text-primary hover:underline">Clear dates</button>
                            </div>

                            <div class="rounded-xl border border-light-bd-default dark:border-dark-bd-default bg-white dark:bg-dark-secondary p-3 sm:p-4">
                                {{-- month header --}}
                                <div class="mb-3 flex items-center justify-between">
                                    <button type="button" x-on:click="prev()" :disabled="!canGoBack"
                                        class="flex h-8 w-8 items-center justify-center rounded-lg text-light-txt-body dark:text-dark-txt-body hover:bg-light-subtle dark:hover:bg-dark-subtle disabled:opacity-30 disabled:hover:bg-transparent"
                                        aria-label="Previous month">
                                        <flux:icon.chevron-left class="w-4 h-4" />
                                    </button>
                                    <span class="text-sm font-semibold text-light-txt-primary dark:text-dark-txt-primary" x-text="months[month] + ' ' + year"></span>
                                    <button type="button" x-on:click="next()"
                                        class="flex h-8 w-8 items-center justify-center rounded-lg text-light-txt-body dark:text-dark-txt-body hover:bg-light-subtle dark:hover:bg-dark-subtle"
                                        aria-label="Next month">
                                        <flux:icon.chevron-right class="w-4 h-4" />
                                    </button>
                                </div>

                                {{-- weekday header --}}
                                <div class="mb-1 grid grid-cols-7 text-center text-[11px] font-medium uppercase tracking-wide text-light-txt-muted dark:text-dark-txt-muted">
                                    <template x-for="w in ['Su','Mo','Tu','We','Th','Fr','Sa']" :key="w"><span x-text="w"></span></template>
                                </div>

                                {{-- days --}}
                                <div class="grid grid-cols-7 gap-y-1">
                                    <template x-for="(k, i) in cells" :key="month + '-' + i">
                                        <div class="flex h-9 items-center justify-center">
                                            <button
                                                type="button"
                                                x-show="k"
                                                x-text="k ? Number(k.slice(8)) : ''"
                                                x-on:click="pick(k)"
                                                :disabled="isDisabled(k)"
                                                :title="k && isBlocked(k) ? 'Already booked' : ''"
                                                :aria-label="k ? (label(k) + (isBlocked(k) ? ', booked' : '')) : ''"
                                                :class="k ? dayClass(k) : ''"
                                                class="h-9 w-full rounded-lg text-sm tabular-nums transition focus:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                                            ></button>
                                        </div>
                                    </template>
                                </div>

                                {{-- legend --}}
                                <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 border-t border-light-bd-default dark:border-dark-bd-default pt-3 text-[11px] text-light-txt-muted dark:text-dark-txt-muted">
                                    <span class="inline-flex items-center gap-1.5"><span class="h-3 w-3 rounded bg-primary"></span>Selected</span>
                                    <span class="inline-flex items-center gap-1.5"><span class="h-3 w-3 rounded border border-red-200 bg-red-50 dark:border-red-500/30 dark:bg-red-500/10"></span>Booked</span>
                                    <span class="inline-flex items-center gap-1.5"><span class="h-3 w-3 rounded border border-light-bd-default dark:border-dark-bd-default"></span>Available</span>
                                </div>
                            </div>

                            <p class="text-sm text-light-txt-body dark:text-dark-txt-body" x-text="summary"></p>
                            <p x-show="warn" x-cloak x-text="warn" class="text-sm text-danger dark:text-dark-danger"></p>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <flux:input type="time" label="Start time" x-model="startTime" x-on:change="sync()" />
                                <flux:input type="time" label="End time" x-model="endTime" x-on:change="sync()" />
                            </div>

                            <flux:error name="tripStartAt" />
                            <flux:error name="tripEndAt" />
                        </div>

                        <flux:textarea wire:model="notes" label="Notes for the operator (optional)" rows="3" placeholder="Anything the operator should know" />
                    </div>
                </div>

                <div class="flex flex-col-reverse sm:flex-row justify-end gap-2 pt-2 border-t border-light-bd-default dark:border-dark-bd-default">
                    <flux:modal.close>
                        <flux:button type="button" variant="ghost" class="w-full sm:w-auto justify-center font-secondary">Cancel</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="sendRequest" class="w-full sm:w-auto justify-center font-secondary">
                        Send request
                    </flux:button>
                </div>
            </form>
        </flux:modal>
    @endif

    {{-- ===================== PHOTO VIEWER MODAL ===================== --}}
    @if ($this->selected && count($this->gallery) > 0)
        <flux:modal name="vehicle-gallery" class="w-[calc(100%-2rem)] md:w-[44rem] md:max-w-2xl mx-auto rounded-xl">
            <div
                wire:key="gallery-{{ $this->selected->id }}-{{ md5(json_encode($this->gallery)) }}"
                x-data="{
                    photos: @js($this->gallery),
                    selected: @js($this->gallery[0]['path']),
                    get index() { return Math.max(0, this.photos.findIndex(p => p.path === this.selected)); },
                    get current() { return this.photos[this.index]; },
                    prev() { this.selected = this.photos[(this.index - 1 + this.photos.length) % this.photos.length].path; },
                    next() { this.selected = this.photos[(this.index + 1) % this.photos.length].path; },
                }"
                x-on:open-gallery.window="selected = $event.detail.path"
                x-on:keydown.arrow-left.window="prev()"
                x-on:keydown.arrow-right.window="next()"
                class="flex flex-col gap-4 max-h-[80vh] overflow-y-auto p-1"
            >
                <div>
                    <flux:heading size="xl" class="!font-primary !font-bold text-light-txt-primary dark:text-dark-txt-primary">
                        {{ $this->selected->plate_number }} · {{ $this->selected->vehicle_type }}
                    </flux:heading>
                    <flux:text class="mt-1 font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted tabular-nums">
                        Photo <span x-text="index + 1"></span> of <span x-text="photos.length"></span>
                    </flux:text>
                </div>

                {{-- big photo --}}
                <div class="relative overflow-hidden rounded-xl border border-light-bd-default dark:border-dark-bd-default bg-light-subtle dark:bg-dark-subtle">
                    <img :src="current?.url" alt="Vehicle photo" class="aspect-[4/3] sm:aspect-[16/10] w-full object-contain" />

                    <template x-if="photos.length > 1">
                        <div>
                            <button type="button" x-on:click="prev()" aria-label="Previous photo"
                                class="absolute left-2 top-1/2 -translate-y-1/2 flex h-9 w-9 items-center justify-center rounded-full bg-black/50 text-white hover:bg-black/70 transition">
                                <flux:icon.chevron-left class="w-5 h-5" />
                            </button>
                            <button type="button" x-on:click="next()" aria-label="Next photo"
                                class="absolute right-2 top-1/2 -translate-y-1/2 flex h-9 w-9 items-center justify-center rounded-full bg-black/50 text-white hover:bg-black/70 transition">
                                <flux:icon.chevron-right class="w-5 h-5" />
                            </button>
                        </div>
                    </template>
                </div>

                {{-- thumbnails --}}
                <div class="flex gap-2 overflow-x-auto p-1">
                    <template x-for="photo in photos" :key="photo.path">
                        <button
                            type="button"
                            x-on:click="selected = photo.path"
                            :aria-pressed="selected === photo.path"
                            :class="selected === photo.path ? 'ring-2 ring-primary ring-offset-2 ring-offset-white dark:ring-offset-dark-secondary' : 'opacity-70 hover:opacity-100'"
                            class="h-16 w-16 sm:h-20 sm:w-20 shrink-0 overflow-hidden rounded-lg transition focus:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                        >
                            <img :src="photo.url" alt="" class="h-full w-full object-cover" />
                        </button>
                    </template>
                </div>

                <div class="flex justify-end pt-2 border-t border-light-bd-default dark:border-dark-bd-default">
                    <flux:modal.close>
                        <flux:button type="button" variant="ghost" class="w-full sm:w-auto justify-center font-secondary">Close</flux:button>
                    </flux:modal.close>
                </div>
            </div>
        </flux:modal>
    @endif
</div>
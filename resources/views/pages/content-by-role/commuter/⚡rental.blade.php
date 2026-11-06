<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;

use Flux\Flux;

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

    public function sendRequest(RentalRequestService $service): void
    {
        $vehicle = $this->selected;

        if (! $vehicle) {
            $this->addError('form', 'This vehicle is no longer available. Please pick another one.');
            return;
        }

        $this->validate();

        try {
            $service->create(auth()->user(), $vehicle, (int) $this->rateId, [
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
                                <div class="flex h-10 w-10 sm:h-11 sm:w-11 items-center justify-center rounded-lg bg-primary/10 dark:bg-primary/20 text-primary dark:text-dark-txt-primary shrink-0">
                                    <flux:icon.truck class="w-4 h-4 sm:w-5 sm:h-5" />
                                </div>
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
                                <x-text class="mt-1 text-xs text-light-txt-muted dark:text-dark-txt-muted">
                                    Operator: {{ $vehicle->user?->name ?? '—' }}
                                </x-text>
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
                            <div>
                                <span class="block text-[10px] sm:text-xs text-light-txt-muted dark:text-dark-txt-muted uppercase tracking-wider mb-0.5">Operator</span>
                                <span class="text-sm font-medium text-light-txt-body dark:text-dark-txt-body break-words">{{ $v->user?->name ?? '—' }}</span>
                            </div>
                        </div>

                        <div>
                            <span class="block text-[10px] sm:text-xs text-light-txt-muted dark:text-dark-txt-muted uppercase tracking-wider mb-1.5">Events and first payment</span>

                            <div class="divide-y divide-light-bd-default dark:divide-dark-bd-default">
                                @foreach ($v->offeredEventRates as $rate)
                                    <div wire:key="rate-{{ $rate->id }}" class="py-2">
                                        <div class="text-sm font-medium text-light-txt-primary dark:text-dark-txt-primary">{{ $rate->eventType->name }}</div>
                                        @if ($rate->eventType->description)
                                            <div class="text-xs text-light-txt-muted dark:text-dark-txt-muted">{{ $rate->eventType->description }}</div>
                                        @endif
                                        <div class="mt-1 text-xs text-light-txt-body dark:text-dark-txt-body tabular-nums">
                                            First payment: <strong>{{ \App\Support\Peso::format($rate->down_payment_amount) }}</strong>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div>
                            <span class="block text-[10px] sm:text-xs text-light-txt-muted dark:text-dark-txt-muted uppercase tracking-wider mb-1.5">Already booked</span>
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
            class="w-[calc(100%-2rem)] sm:max-w-xl mx-auto rounded-xl"
        >
            <form wire:submit="sendRequest" class="flex flex-col gap-5 max-h-[75vh] overflow-y-auto p-1">
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

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <flux:input wire:model="tripStartAt" type="datetime-local" label="Trip start" />
                    <flux:input wire:model="tripEndAt" type="datetime-local" label="Trip end" />
                </div>

                <flux:input
                    wire:model="passengerCount"
                    type="number"
                    min="1"
                    max="{{ $this->selected->total_seats }}"
                    label="Number of passengers"
                    description="This vehicle seats up to {{ $this->selected->total_seats }}."
                />

                <flux:textarea wire:model="notes" label="Notes for the operator (optional)" rows="3" placeholder="Anything the operator should know" />

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
</div>
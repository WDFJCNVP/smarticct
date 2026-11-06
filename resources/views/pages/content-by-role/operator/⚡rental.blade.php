<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;

use Flux\Flux;

use App\Enums\RentalRequestStatus;
use App\Enums\RentalStatus;
use App\Models\Vehicle;
use App\Services\RentalStatusService;

new #[Layout('layouts.operator-layout')] class extends Component
{
    /** Vehicle id => is its rental switch on? */
    public array $active = [];

    public function mount(): void
    {
        $this->active = $this->vehicles
            ->mapWithKeys(fn (Vehicle $v) => [$v->id => $v->rental_status === RentalStatus::Active])
            ->all();
    }

    /** All of the operator's vehicles, shown in the first list. */
    #[Computed]
    public function vehicles()
    {
        return Vehicle::query()
            ->where('user_id', auth()->id())
            ->with('route_list')
            ->withExists(['eventRates as has_rental_rates' => fn ($q) => $q->offered()])
            ->orderBy('plate_number')
            ->get();
    }

    #[Computed]
    public function hasCard(): bool
    {
        return auth()->user()->card !== null;
    }

    /** Vehicles whose switch is on, with their request counts (shown below the list). */
    #[Computed]
    public function rentalVehicles()
    {
        $upcoming = fn ($q) => $q
            ->where('status', RentalRequestStatus::Confirmed->value)
            ->where('trip_end_at', '>=', now());

        return Vehicle::query()
            ->where('user_id', auth()->id())
            ->where('rental_status', RentalStatus::Active->value)
            ->with('offeredEventRates.eventType')
            ->withCount([
                'rentalRequests as pending_requests_count' => fn ($q) => $q
                    ->where('status', RentalRequestStatus::Pending->value),
                'rentalRequests as awaiting_requests_count' => fn ($q) => $q
                    ->whereIn('status', [
                        RentalRequestStatus::Quoted->value,
                        RentalRequestStatus::AwaitingPayment->value,
                    ]),
                'rentalRequests as confirmed_requests_count' => $upcoming,
            ])
            ->orderBy('plate_number')
            ->get();
    }

    /** Runs when a vehicle's switch is flipped. $key is the vehicle id. */
    public function updatedActive($value, $key): void
    {
        $vehicle = Vehicle::where('user_id', auth()->id())->find((int) $key);

        if (! $vehicle) {
            unset($this->active[$key]);
            return;
        }

        $turnOn = (bool) $value;

        try {
            app(RentalStatusService::class)->set(
                $vehicle,
                auth()->user(),
                $turnOn ? RentalStatus::Active : RentalStatus::Inactive,
            );
        } catch (\DomainException $e) {
            // Put the switch back where it was.
            $this->active[$key] = $vehicle->rental_status === RentalStatus::Active;

            Flux::toast(
                variant: 'danger',
                heading: 'Cannot change rental status',
                duration: 5000,
                text: $e->getMessage(),
            );
            return;
        }

        Flux::toast(
            variant: 'success',
            heading: $turnOn ? 'Vehicle is now active' : 'Vehicle is now not active',
            duration: 4000,
            text: $turnOn
                ? 'Passengers can now see it on the booking page and send you requests.'
                : 'It is hidden from passengers. Existing requests are not affected.',
        );
    }
};
?>

<div>
    <x-page-header heading="Rentals"></x-page-header>

    <div class="mb-6">
        <x-heading
            class="!font-primary !font-bold !text-light-txt-primary dark:!text-dark-txt-primary"
            style="font-size: var(--text-section-heading)"
        >
            Your vehicles
        </x-heading>
        <x-text class="mt-1 text-sm text-light-txt-muted dark:text-dark-txt-muted">
            Turn a vehicle on to show it to passengers and accept their requests. Turn it off to hide it.
        </x-text>
    </div>

    @if (! $this->hasCard)
        <div class="mb-4 rounded-lg border border-warning/40 bg-warning/10 dark:bg-dark-warning/10 p-3 text-sm text-light-txt-body dark:text-dark-txt-body">
            You need an issued card before you can rent out a vehicle, because your first payments are credited to it.
        </div>
    @endif

    {{-- ===================== 1. YOUR VEHICLES ===================== --}}
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-3 mb-2">
        @forelse ($this->vehicles as $vehicle)
            @php
                $isActive  = $active[$vehicle->id] ?? false;
                $suspended = $vehicle->rental_status === \App\Enums\RentalStatus::Suspended;
                $hasRates  = (bool) $vehicle->has_rental_rates;
            @endphp

            <flux:card wire:key="vehicle-{{ $vehicle->id }}" class="!p-3 sm:!p-5">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 dark:bg-primary/20 text-primary dark:text-dark-txt-primary shrink-0">
                            <flux:icon.truck class="w-4 h-4 sm:w-5 sm:h-5" />
                        </div>
                        <div class="min-w-0">
                            <x-text class="font-mono font-medium text-light-txt-primary dark:text-dark-txt-primary text-sm sm:text-base">
                                {{ $vehicle->plate_number }}
                            </x-text>
                            <x-text class="text-xs text-light-txt-muted dark:text-dark-txt-muted">{{ $vehicle->vehicle_type }}</x-text>
                        </div>
                    </div>

                    @if ($suspended)
                        <flux:badge color="red" size="sm" class="font-secondary text-badge text-xs shrink-0">Suspended by admin</flux:badge>
                    @else
                        <flux:switch
                            wire:model.live="active.{{ $vehicle->id }}"
                            :disabled="! $isActive && ! $hasRates"
                            aria-label="Active for renting"
                        />
                    @endif
                </div>

                <div class="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                    <div>
                        <span class="block text-[10px] sm:text-xs text-light-txt-muted dark:text-dark-txt-muted uppercase tracking-wider">Capacity</span>
                        <span class="text-light-txt-body dark:text-dark-txt-body">{{ $vehicle->total_seats }} seats</span>
                    </div>
                    <div>
                        <span class="block text-[10px] sm:text-xs text-light-txt-muted dark:text-dark-txt-muted uppercase tracking-wider">Route</span>
                        <span class="text-light-txt-body dark:text-dark-txt-body">
                            {{ $vehicle->route_list?->terminal ? 'Iriga → ' . $vehicle->route_list->terminal : '—' }}
                        </span>
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap items-center justify-between gap-2 border-t border-light-bd-default dark:border-dark-bd-default pt-3">
                    <x-text class="text-xs text-light-txt-muted dark:text-dark-txt-muted">
                        @if ($suspended)
                            Rental is switched off by the admin.
                        @elseif ($isActive)
                            Active: visible to passengers and open for requests.
                        @elseif (! $hasRates)
                            Set up its rental details before you can switch it on.
                        @else
                            Not active: hidden from passengers.
                        @endif
                    </x-text>

                    @if (! $hasRates)
                        <flux:button
                            href="{{ route('setup-rental', ['vehicle' => $vehicle->id]) }}"
                            wire:navigate
                            size="sm"
                            variant="primary"
                            class="font-secondary"
                        >Set-up now</flux:button>
                    @else
                        <flux:button
                            href="{{ route('setup-rental', ['vehicle' => $vehicle->id]) }}"
                            wire:navigate
                            size="sm"
                            variant="ghost"
                            icon="pencil-square"
                            class="font-secondary"
                        >Edit rental details</flux:button>
                    @endif
                </div>
            </flux:card>
        @empty
            <flux:card class="lg:col-span-2 !p-6">
                <div class="flex flex-col items-center justify-center py-6 gap-2 text-center">
                    <flux:icon.truck class="w-8 h-8 text-light-txt-muted dark:text-dark-txt-muted" />
                    <x-text class="text-sm text-light-txt-muted dark:text-dark-txt-muted">You have no registered vehicles yet.</x-text>
                </div>
            </flux:card>
        @endforelse
    </div>

    {{-- ===================== 2. VEHICLES ACTIVE FOR RENTING ===================== --}}
    <div class="mt-8 mb-3 flex flex-col sm:flex-row sm:items-end sm:justify-between gap-1">
        <div>
            <x-heading
                class="!font-primary !font-bold !text-light-txt-primary dark:!text-dark-txt-primary"
                style="font-size: var(--text-section-heading)"
            >
                Vehicles active for renting
            </x-heading>
            <x-text class="mt-0.5 text-sm text-light-txt-muted dark:text-dark-txt-muted">
                These vehicles are visible to passengers on the booking page.
            </x-text>
        </div>
        <flux:badge color="green" size="sm" class="self-start sm:self-auto font-secondary text-badge text-xs">
            {{ $this->rentalVehicles->count() }} active
        </flux:badge>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-3 mb-4">
        @forelse ($this->rentalVehicles as $rental)
            <flux:card wire:key="rental-{{ $rental->id }}" class="!p-3 sm:!p-5">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 dark:bg-primary/20 text-primary dark:text-dark-txt-primary shrink-0">
                            <flux:icon.truck class="w-4 h-4 sm:w-5 sm:h-5" />
                        </div>
                        <div>
                            <x-text class="font-mono font-medium text-light-txt-primary dark:text-dark-txt-primary text-sm sm:text-base">
                                {{ $rental->plate_number }}
                            </x-text>
                            <x-text class="text-xs text-light-txt-muted dark:text-dark-txt-muted">
                                {{ $rental->vehicle_type }} · {{ $rental->total_seats }} seats
                            </x-text>
                        </div>
                    </div>
                    <flux:badge color="green" size="sm" class="font-secondary text-badge text-xs shrink-0">Active</flux:badge>
                </div>

                {{-- Events offered --}}
                <div class="mt-3 flex flex-wrap items-center gap-1.5">
                    @if ($rental->offeredEventRates->count() > 3)
                        <span class="text-xs text-light-txt-muted dark:text-dark-txt-muted">
                            +{{ $rental->offeredEventRates->count() - 3 }} more
                        </span>
                    @endif
                </div>

                {{-- Request counts --}}
                <div class="mt-4 grid grid-cols-3 gap-2 text-center">
                    <div class="rounded-lg bg-light-subtle dark:bg-dark-subtle py-2">
                        <span class="block text-lg font-bold font-primary {{ $rental->pending_requests_count > 0 ? 'text-warning dark:text-dark-warning' : 'text-light-txt-primary dark:text-dark-txt-primary' }}">
                            {{ $rental->pending_requests_count }}
                        </span>
                        <span class="block text-[10px] sm:text-xs uppercase tracking-wider text-light-txt-muted dark:text-dark-txt-muted">New requests</span>
                    </div>
                    <div class="rounded-lg bg-light-subtle dark:bg-dark-subtle py-2">
                        <span class="block text-lg font-bold font-primary text-light-txt-primary dark:text-dark-txt-primary">
                            {{ $rental->awaiting_requests_count }}
                        </span>
                        <span class="block text-[10px] sm:text-xs uppercase tracking-wider text-light-txt-muted dark:text-dark-txt-muted">Awaiting passenger</span>
                    </div>
                    <div class="rounded-lg bg-light-subtle dark:bg-dark-subtle py-2">
                        <span class="block text-lg font-bold font-primary text-success dark:text-dark-success">
                            {{ $rental->confirmed_requests_count }}
                        </span>
                        <span class="block text-[10px] sm:text-xs uppercase tracking-wider text-light-txt-muted dark:text-dark-txt-muted">Upcoming booked</span>
                    </div>
                </div>

                <div class="mt-4 flex justify-end border-t border-light-bd-default dark:border-dark-bd-default pt-3">
                    <flux:button
                        href="{{ route('rental-details', ['vehicle' => $rental->id]) }}"
                        wire:navigate
                        size="sm"
                        variant="primary"
                        icon-trailing="arrow-right"
                        class="font-secondary"
                    >
                        See more
                    </flux:button>
                </div>
            </flux:card>
        @empty
            <flux:card class="lg:col-span-2 !p-6">
                <div class="flex flex-col items-center justify-center py-6 gap-2 text-center">
                    <flux:icon.truck class="w-8 h-8 text-light-txt-muted dark:text-dark-txt-muted" />
                    <x-text class="text-sm text-light-txt-muted dark:text-dark-txt-muted">
                        None of your vehicles is active for renting yet.
                    </x-text>
                    <x-text class="text-xs text-light-txt-muted dark:text-dark-txt-muted">
                        Switch a vehicle on above to show it here.
                    </x-text>
                </div>
            </flux:card>
        @endforelse
    </div>
</div>
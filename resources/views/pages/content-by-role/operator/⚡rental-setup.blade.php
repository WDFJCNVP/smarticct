<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;

use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

use App\Enums\RentalStatus;
use App\Models\RentalEventType;
use App\Models\Vehicle;
use App\Models\VehicleEventRate;

new #[Layout('layouts.operator-layout')] class extends Component
{
    public Vehicle $vehicle;

    // Create form
    public string $service_name = '';
    public $down_payment = '';

    // Edit form (only the down payment can be changed; the service name stays)
    public ?int $edit_id = null;
    public string $edit_service_name = '';
    public $edit_down_payment = '';

    public function mount(Vehicle $vehicle): void
    {
        abort_if($vehicle->user_id !== auth()->id(), 404);

        $this->vehicle = $vehicle;
    }

    /** The services this vehicle currently offers. */
    #[Computed]
    public function services()
    {
        return $this->vehicle->eventRates()
            ->where('is_active', true)
            ->with('eventType')
            ->get()
            ->sortBy(fn ($rate) => mb_strtolower($rate->eventType->name))
            ->values();
    }

    /** Standard services, offered as suggestions while typing a name. */
    #[Computed]
    public function suggestions()
    {
        return RentalEventType::active()->whereNull('user_id')->orderBy('name')->pluck('name');
    }

    #[Computed]
    public function readiness(): array
    {
        $franchiseOk = $this->vehicle->has_franchise
            && in_array($this->vehicle->documentStatus(), ['valid', 'expiring'], true);

        return [
            'card'      => auth()->user()->card !== null,
            'franchise' => $franchiseOk,
        ];
    }

    public function save(): void
    {
        abort_if($this->vehicle->user_id !== auth()->id(), 404);

        $name = trim((string) preg_replace('/\s+/', ' ', $this->service_name));
        $this->service_name = $name;

        $this->validate(
            [
                'service_name' => [
                    'required', 'string', 'min:3', 'max:60',
                    // Letters, numbers, spaces and a few safe symbols only (no tags or links).
                    'regex:/^[\pL\pN][\pL\pN\s\-\.\&\/\(\)\',]*$/u',
                ],
                'down_payment' => ['required', 'numeric', 'min:1', 'max:999999.99'],
            ],
            ['service_name.regex' => 'Use only letters, numbers, spaces, and simple punctuation.'],
            ['service_name' => 'service name', 'down_payment' => 'down payment'],
        );

        DB::transaction(function () use ($name) {
            $userId = auth()->id();

            // Reuse a standard service, or one of your own, when the name already exists.
            $type = RentalEventType::availableTo($userId)
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                ->first();

            if ($type && ! $type->is_active) {
                throw ValidationException::withMessages(['service_name' => 'That service name is not available.']);
            }

            if (! $type) {
                if (RentalEventType::where('user_id', $userId)->count() >= RentalEventType::MAX_CUSTOM_PER_OPERATOR) {
                    throw ValidationException::withMessages([
                        'service_name' => 'You can add up to ' . RentalEventType::MAX_CUSTOM_PER_OPERATOR . ' services of your own.',
                    ]);
                }

                $type = RentalEventType::create([
                    'user_id'   => $userId,
                    'name'      => $name,
                    'is_active' => true,
                ]);
            }

            $keys = ['vehicle_id' => $this->vehicle->id, 'rental_event_type_id' => $type->id];

            if (VehicleEventRate::where($keys)->where('is_active', true)->exists()) {
                throw ValidationException::withMessages(['service_name' => 'This vehicle already offers that service.']);
            }

            // A service removed earlier is switched back on with the new amount.
            VehicleEventRate::updateOrCreate($keys, [
                'down_payment_amount' => round((float) $this->down_payment, 2),
                'is_active'           => true,
            ]);
        });

        $this->reset(['service_name', 'down_payment']);
        $this->resetValidation();
        unset($this->services);

        Flux::toast(
            duration: 4000,
            variant: 'success',
            heading: 'Service added',
            text: 'The service and its down payment have been saved.',
        );
    }

    public function edit(int $id): void
    {
        $rate = $this->vehicle->eventRates()->with('eventType')->where('is_active', true)->findOrFail($id);

        $this->edit_id           = $rate->id;
        $this->edit_service_name = $rate->eventType->name;
        $this->edit_down_payment = (string) $rate->down_payment_amount;

        $this->resetValidation();

        Flux::modal('edit-service')->show();
    }

    public function update(): void
    {
        $this->validate(
            ['edit_down_payment' => ['required', 'numeric', 'min:1', 'max:999999.99']],
            [],
            ['edit_down_payment' => 'down payment'],
        );

        $rate = $this->vehicle->eventRates()->where('is_active', true)->findOrFail($this->edit_id);

        $rate->update(['down_payment_amount' => round((float) $this->edit_down_payment, 2)]);

        unset($this->services);
        Flux::modal('edit-service')->close();

        Flux::toast(
            duration: 4000,
            variant: 'success',
            heading: 'Down payment updated',
            text: 'New requests will use the new down payment. Requests already made keep the one they saw.',
        );
    }

    /** Takes a service off this vehicle. The row is kept, so past requests still refer to it. */
    public function remove(int $id): void
    {
        $rate = $this->vehicle->eventRates()->where('is_active', true)->findOrFail($id);

        // An active vehicle must keep at least one service, or passengers would see it with nothing to book.
        $isLast = $this->vehicle->eventRates()->offered()->count() <= 1;

        if ($isLast && $this->vehicle->fresh()->rental_status === RentalStatus::Active) {
            Flux::toast(
                duration: 5000,
                variant: 'danger',
                heading: 'Cannot remove the last service',
                text: 'Switch this vehicle off for renting first, or add another service.',
            );
            return;
        }

        $rate->update(['is_active' => false]);

        unset($this->services);

        Flux::toast(duration: 3000, variant: 'success', heading: 'Service removed', text: 'It is no longer offered on this vehicle.');
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
                Services for {{ $vehicle->plate_number }}
            </x-heading>
            <x-text class="mt-1 text-sm text-light-txt-muted dark:text-dark-txt-muted">
                Add what this vehicle can be rented for, and the down payment passengers pay online to reserve. You set the full payment in your quote for each request, because every trip is different.
            </x-text>
        </div>

        <flux:breadcrumbs class="shrink-0 sm:pt-1">
            <flux:breadcrumbs.item href="{{ route('operator.vehicles') }}" wire:navigate>
                Back to my vehicles
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item>Rental details</flux:breadcrumbs.item>
        </flux:breadcrumbs>
    </div>

    {{-- Requirements before the vehicle can be set to Active --}}
    @if (! $this->readiness['card'] || ! $this->readiness['franchise'])
        <div class="mb-5 rounded-lg border border-warning/40 bg-warning/10 dark:bg-dark-warning/10 p-3 sm:p-4">
            <x-text class="text-sm font-medium text-light-txt-primary dark:text-dark-txt-primary mb-2">
                You can add services now, but to set this vehicle to Active for rent you still need:
            </x-text>
            <ul class="space-y-1 text-sm text-light-txt-body dark:text-dark-txt-body">
                <li class="flex items-center gap-2">
                    @if ($this->readiness['card'])
                        <flux:icon.check-circle class="w-4 h-4 text-success dark:text-dark-success shrink-0" />
                    @else
                        <flux:icon.x-circle class="w-4 h-4 text-danger dark:text-dark-danger shrink-0" />
                    @endif
                    An issued card, which receives your first payments
                </li>
                <li class="flex items-center gap-2">
                    @if ($this->readiness['franchise'])
                        <flux:icon.check-circle class="w-4 h-4 text-success dark:text-dark-success shrink-0" />
                    @else
                        <flux:icon.x-circle class="w-4 h-4 text-danger dark:text-dark-danger shrink-0" />
                    @endif
                    A valid franchise for this vehicle
                </li>
            </ul>
        </div>
    @endif

    <!-- Create Card -->
    <flux:card class="mb-4">
        <x-inputs-container>
            <flux:field>
                <flux:label>Service name</flux:label>
                <flux:input
                    wire:model="service_name"
                    list="service-suggestions"
                    maxlength="60"
                    placeholder="e.g. Wedding, Island hopping"
                    autocomplete="off"
                />
                <datalist id="service-suggestions">
                    @foreach ($this->suggestions as $suggestion)
                        <option value="{{ $suggestion }}"></option>
                    @endforeach
                </datalist>
                <flux:error name="service_name" />
            </flux:field>

            <flux:input type="number" step="0.01" min="1" inputmode="decimal" wire:model="down_payment" label="Down payment" placeholder="0.00" />
        </x-inputs-container>
        <flux:button wire:click="save" variant="primary" class="w-full cursor-pointer mt-2">Save</flux:button>
    </flux:card>

    <!-- Listing Card -->
    <flux:card class="mt-2">
        <x-table>
            <x-table-columns>
                <x-table-column>#</x-table-column>
                <x-table-column>Service</x-table-column>
                <x-table-column>Down payment</x-table-column>
                <x-table-column>Action</x-table-column>
            </x-table-columns>
            <x-table-rows>
                @forelse ($this->services as $index => $rate)
                    <x-table-row>
                        <x-table-cell>{{ $index + 1 }}</x-table-cell>
                        <x-table-cell>
                            {{ $rate->eventType->name }}
                            @if ($rate->eventType->user_id !== null)
                                <flux:badge size="sm" color="blue" class="ml-1 font-secondary text-badge text-xs">Your service</flux:badge>
                            @endif
                        </x-table-cell>
                        <x-table-cell>{{ \App\Support\Peso::format($rate->down_payment_amount) }}</x-table-cell>
                        <x-table-cell>
                            <flux:button wire:click="edit({{ $rate->id }})">Edit</flux:button>
                            <flux:button
                                wire:click="remove({{ $rate->id }})"
                                wire:confirm="Remove this service from this vehicle?"
                                variant="ghost"
                            >Remove</flux:button>
                        </x-table-cell>
                    </x-table-row>
                @empty
                    <x-table-row>
                        <x-table-cell colspan="4" class="text-center text-light-txt-muted dark:text-dark-txt-muted">There are no services yet</x-table-cell>
                    </x-table-row>
                @endforelse
            </x-table-rows>
        </x-table>
    </flux:card>

    <flux:modal name="edit-service" class="md:w-96">
        <form wire:submit="update" class="space-y-6">
            <div>
                <flux:heading size="lg" class="font-primary font-bold text-light-txt-primary dark:text-dark-txt-primary">Update down payment</flux:heading>
                <flux:text class="mt-2">New requests will use the new amount. Requests already made keep the one they saw.</flux:text>
            </div>

            <flux:field>
                <flux:label>Service name</flux:label>
                <flux:input wire:model="edit_service_name" size="sm" disabled />
            </flux:field>

            <flux:field>
                <flux:input type="number" step="0.01" min="1" inputmode="decimal" wire:model="edit_down_payment" label="Down payment" placeholder="0.00" size="sm" />
                <flux:error name="edit_down_payment" />
            </flux:field>

            <div class="flex">
                <flux:spacer />
                <flux:button type="submit" variant="primary">Save changes</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
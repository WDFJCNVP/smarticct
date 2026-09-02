<?php

use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use App\Events\RegistrationTapCardEvent;
use Livewire\Component;
use Illuminate\Support\Str;

use Illuminate\Support\Facades\DB;

use App\Models\User;
use App\Models\Card;
use App\Models\Vehicle;
use App\Models\Terminal;
use App\Models\Route;
use App\Models\RouteList;
use App\Models\OperatorTicketRate;

use App\Mail\WelcomeUserMail;
use Illuminate\Support\Facades\Mail;
use Flux\Flux;

use App\Services\UserService;

new #[Layout('layouts.admin-layout')] class extends Component
{
    public int $step = 1;
    public bool $skipped = false;

    public string $role = '';
    public string $first_name = '';
    public string $last_name = '';
    public string $age;
    public string $email_address = '';
    public string $phone_number;

    public string $date_of_birth = '';
    public string $commuter_type = 'Regular';
    public string $address = '';
    public string $card_number = '';
    public string $new_card_id = '';

    public bool $card_focused = true;
    public string $card_state = 'warn';

    public string $employee_id = '';
    public string $license_number = '';
    public string $assigned_route = '';
    public string $vehicle_plate = '';
    public string $operator_type = 'Driver';
    public string $group_number;

    public array $vehicles = [
        [
        'vehicle_type' => '',
         'plate_number' => '',
         'group_number' => '',
         'route' => '',
         'seat_capacity' => '',
         'engine_number' => '',
         'body_number' => '',
         'chassis_number' => '',
         'has_franchise' => false,
         'franchise_expiry_date' => '',
         ],
    ];

    public string $house_subd = '';
    public ?int $zone_number = null;
    public string $barangay = '';
    public string $municipality = '';
    public string $province = '';      // <-- NEW

    protected $validationAttributes = [
        'vehicles.*.seat_capacity'  => 'seat capacity',
        'vehicles.*.plate_number'   => 'plate number',
        'vehicles.*.vehicle_type'   => 'vehicle type',
        'vehicles.*.route'          => 'route',
        'vehicles.*.engine_number'  => 'engine number',
        'vehicles.*.body_number'    => 'body number',
        'vehicles.*.chassis_number' => 'chassis number',
        'vehicles.*.has_franchise'  => 'franchise verification',
        'vehicles.*.franchise_expiry_date' => 'validity date of franchise',
        'vehicles.*.group_number'   => 'group number',
    ];

    #[Computed]
    public function getVehicleType() {
        return OperatorTicketRate::get('vehicle_type');
    }

    public function stepSkipped() {
        $this->skipped = true;
        $this->next();
    }

    public function updated($property)
    {
        if (str_starts_with($property, 'vehicles.')) {
            $this->resetValidation($property);

            // If a vehicle drops from complete -> incomplete while its modal
            // is open (e.g. clearing seat capacity), close that modal so the
            // page falls back to the inline open-form branch cleanly instead
            // of leaving stale modal DOM behind.
            if (preg_match('/^vehicles\.(\d+)\./', $property, $m)) {
                $idx = (int) $m[1];
                if (isset($this->vehicles[$idx]) && !$this->vehicleIsComplete($this->vehicles[$idx])) {
                    $this->dispatch('close-vehicle-modal', index: $idx);
                }
            }
        }

        if (in_array($property, ['first_name', 'last_name', 'email_address', 'age', 'phone_number'])) {
            $this->resetValidation($property);
        }

        if (in_array($property, ['house_subd', 'zone_number', 'barangay', 'municipality', 'province'])) {  // ADDED province
            $this->resetValidation($property);
        }
    }

    #[On('echo:registration-tap-card,.RegistrationTapCardEvent')]
    public function getUid($event): void
    {
        $this->card_number = $event['uid'];
        $this->new_card_id = $event['id'];
        $this->card_state  = 'success';
    }

    public function cardScanned(): void
    {
        $this->card_state = $this->card_number !== '' ? 'success' : 'ready';
    }

    public function clearCard(): void
    {
        $this->card_number = '';
        $this->card_state  = 'ready';
    }

    public function cardFocused(): void
    {
        if ($this->card_state !== 'success') {
            $this->card_state = 'ready';
        }
    }

    public function cardBlurred(): void
    {
        if ($this->card_state !== 'success') {
            $this->card_state = 'warn';
        }
    }

    public function refocus(): void
    {
        $this->card_state = 'ready';
        $this->dispatch('focus-rfid-input');
    }

    #[Computed]
    public function getRoute()
    {
         return RouteList::with('operatorTicketRate')
            ->get();
    }

    public function saveAddress(): void
    {
        $data = $this->validate([
            'house_subd'   => 'nullable|string|max:255',
            'zone_number'  => 'required|integer|min:1|max:20',
            'barangay'     => 'required|string|max:255',
            'municipality' => 'required|string|max:255',
            'province'     => 'required|string|max:255',    // <-- NEW
        ]);

        $parts = array_filter([
            $data['house_subd'] !== '' ? $data['house_subd'] : null,
            'Zone ' . $data['zone_number'],
            $data['barangay'],
            $data['municipality'],
            $data['province'],                             // <-- REPLACED hardcoded
        ]);

        $this->address = implode(', ', $parts);
        $this->resetValidation();

        $this->dispatch('address-saved');
    }

    public function next(): void
    {
        $this->resetValidation();

        if ($this->step === 1) {
            $this->validate([
                'role' => 'required|in:commuter,operator'
            ]);
        }

        if ($this->step === 2) {
            $this->validate([
                'first_name'    => 'required|min:2',
                'last_name'     => 'required|min:2',
                'email_address' => 'required|email|unique:users,email_address',
                'age'           => 'required|min:2|numeric',
            ]);
        }

        if ($this->step === 3) {
            if ($this->role === 'commuter') {
                $this->validate([
                    'address'       => 'required|min:5',
                    'phone_number'  => 'required|numeric',
                    'commuter_type' => 'required',
                ]);
            } else {
                $rules = [
                    'address'                         => 'required|min:5',
                    'phone_number'                    => 'required|numeric',
                    'vehicles'                        => 'required|array|min:1',
                    'vehicles.*.vehicle_type'         => 'required|string',
                    'vehicles.*.plate_number'         => 'required|unique:vehicles,plate_number',
                    'vehicles.*.route'                => 'required|string',
                    'vehicles.*.seat_capacity'        => 'required|integer|min:10|max:50',
                    'vehicles.*.engine_number'        => 'required|string|min:2',
                    'vehicles.*.body_number'          => 'required|string|min:2',
                    'vehicles.*.chassis_number'       => 'required|string|min:2',
                    'vehicles.*.has_franchise'        => 'required|accepted',
                    'vehicles.*.franchise_expiry_date' => 'required|date|after:today',
                ];

                $rules['vehicles.*.group_number'] = [
                    'required_if:vehicles.*.vehicle_type,Bus,UV-express',
                    'integer',
                    'min:1',
                    'max:2'
                ];

                try {
                    $this->validate($rules);
                } catch (\Illuminate\Validation\ValidationException $e) {
                    // Only vehicles rendered as a collapsed card + modal (i.e.
                    // "complete" by our basic check) can hide an error from
                    // view — e.g. a duplicate plate number even though every
                    // required field is filled. Open that vehicle's modal so
                    // the error is visible. Incomplete vehicles already show
                    // their fields inline, so no action needed there.
                    foreach ($e->validator->errors()->keys() as $key) {
                        if (preg_match('/^vehicles\.(\d+)\./', $key, $m)) {
                            $idx = (int) $m[1];
                            if (isset($this->vehicles[$idx]) && $this->vehicleIsComplete($this->vehicles[$idx])) {
                                $this->dispatch('open-vehicle-modal', index: $idx);
                            }
                            break;
                        }
                    }
                    throw $e;
                }
            }
        }

        if ($this->step === 4) {
            if (!$this->skipped) {
                $this->validate([
                    'card_number' => 'required|unique:cards,uid',
                ]);
            }
        }

        if ($this->step < 5) {
            $this->step++;
        }
    }

    public function back(): void
    {
        if ($this->step > 1) {
            $this->step--;
        }
    }

    public function addVehicle(): void
    {
        $this->vehicles[] = [
            'vehicle_type' => '',
            'plate_number' => '',
            'group_number' => '',
            'route' => '',
            'seat_capacity' => '',
            'engine_number' => '',
            'body_number' => '',
            'chassis_number' => '',
            'has_franchise' => false,
            'franchise_expiry_date' => '',
        ];
    }

    public function removeVehicle(int $index): void
    {
        if (count($this->vehicles) > 1) {
            array_splice($this->vehicles, $index, 1);
        }
    }

    public function vehicleIsComplete(array $vehicle): bool {
        $required = ['vehicle_type', 'plate_number', 'route', 'seat_capacity', 'engine_number', 'body_number', 'chassis_number'];
        foreach ($required as $field) {
            if (!filled($vehicle[$field] ?? null)) {
                return false;
            }
        }

        if (empty($vehicle['has_franchise']) || !filled($vehicle['franchise_expiry_date'] ?? null)) {
            return false;
        }

        if (in_array($vehicle['vehicle_type'] ?? '', ['Bus', 'UV-express'])) {
            return filled($vehicle['group_number'] ?? null);
        }

        return true;
    }

    public function register(): void
    {

        $rawPassword = Str::password(10, true, true, true, false);

        $userBasicInformation = [
            'name'         => $this->first_name . ' ' . $this->last_name,
            'age'          => $this->age,
            'commuter_type'=> $this->commuter_type,
            'phone_number' => $this->phone_number,
            'address'      => $this->address,
            'email_address' => $this->email_address,
            'password' => $rawPassword,
            'role'         => $this->role,
        ];

        if($this->card_number) {
            $cardInformation = [
                'uid'    => $this->card_number,
                'status' => 'active',
            ];
        } else {
            $cardInformation = [];
        }

        $user = app(UserService::class)->create(
            $userBasicInformation,
            $cardInformation,
            $this->vehicles,
        );

        if($user) {

            Mail::to($user->email_address)->send(new WelcomeUserMail(
                $user->name,
                $user->email_address,
                $rawPassword
            ));

            Flux::toast(
                variant: 'success',
                heading: 'User Registered',
                duration: 3000, 
                text: 'User registered successfully. Confirmation email sent to the registered user.'
            );

            $this->dispatch('user-registered');
            $this->reset();
            $this->step = 1;
        }


    }

    public function isStepDone(int $s): bool
    {
        return $this->step > $s;
    }

};
?>

<div
     x-on:open-vehicle-modal.window="$flux.modal('edit-vehicle-' + $event.detail.index).show()"
     x-on:close-vehicle-modal.window="$flux.modal('edit-vehicle-' + $event.detail.index).close()">
    
    {{-- Breadcrumbs on top on mobile; heading + breadcrumbs side-by-side from sm up --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 my-8">
        <flux:breadcrumbs class="order-1 sm:order-2">
            <flux:breadcrumbs.item href="{{ route('admin.users') }}" wire:navigate>Back to Users</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>Registration</flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <flux:heading size="xl" class="order-2 sm:order-1 !font-primary !font-bold text-light-txt-primary dark:text-dark-txt-primary">
            {{ $this->role ? 'Registration for ' . ucfirst($this->role) : 'Register New User' }}
        </flux:heading>
    </div>

    <div class="flex items-center gap-1 mb-6 font-secondary text-timestamp">
        @foreach([1 => 'Select role', 2 => 'Account info', 3 => 'Details', 4 => 'Card', 5 => 'Confirm'] as $s => $label)
            <div class="flex items-center gap-1 {{ $loop->last ? '' : 'flex-1' }}">
                <div class="flex items-center gap-1.5">
                    <div @class([
                        'w-12 h-12 rounded-full flex items-center justify-center text-timestamp font-secondary font-medium shrink-0 border transition-colors',
                        'bg-primary text-white border-primary'                                                                                              => $step === $s,
                        'bg-success/10 text-success border-success/30 dark:bg-dark-success/10 dark:text-dark-success dark:border-dark-success/30'          => $step > $s,
                        'bg-light-subtle dark:bg-dark-subtle text-light-txt-muted dark:text-dark-txt-muted border-light-bd-default dark:border-dark-bd-default' => $step < $s,
                    ])>
                        @if($step > $s)
                            <flux:icon.check class="w-3 h-3" />
                        @else
                            {{ $s }}
                        @endif
                    </div>
                    <span @class([
                        'hidden sm:inline whitespace-nowrap font-secondary transition-colors',
                        'text-light-txt-primary dark:text-dark-txt-primary font-medium' => $step === $s,
                        'text-light-txt-muted dark:text-dark-txt-muted'                  => $step !== $s,
                    ])>{{ $label }}</span>
                </div>
                @if(!$loop->last)
                    <div @class([
                        'flex-1 h-px mx-1 transition-colors',
                        'bg-primary'                                                    => $step > $s,
                        'bg-light-bd-default dark:bg-dark-bd-default'                    => $step <= $s,
                    ])></div>
                @endif
            </div>
        @endforeach
    </div>

    @if($step === 1)
        <flux:radio.group wire:model="role" variant="cards" class="grid grid-cols-1 sm:grid-cols-2 gap-3 w-full">
            <flux:radio value="commuter" label="Commuter" description="A commuter who rides and tracks transit routes.">
                <x-slot name="icon"><flux:icon.user class="w-5 h-5" /></x-slot>
            </flux:radio>
            <flux:radio value="operator" label="Operator" description="A driver or staff assigned to a route or vehicle.">
                <x-slot name="icon"><flux:icon.identification class="w-5 h-5" /></x-slot>
            </flux:radio>
        </flux:radio.group>
        <flux:error name="role" />
    @endif

    @if($step === 2)
        <div class="space-y-4">
            <x-inputs-container>
                <x-input wire:model.blur="first_name" label="First name" placeholder="e.g. Juan" />
                <x-input wire:model.blur="last_name"  label="Last name"  placeholder="e.g. dela Cruz" />
                <x-input wire:model.blur="email_address"   label="Email address" placeholder="juandelacruz@gmail.com" />
                <x-input wire:model.blur="age"   label="Age" placeholder="e.g. 25" type="number" />
            </x-inputs-container>
        </div>
    @endif

    @if($step === 3 && $role === 'commuter')
        <div class="space-y-4">
            <x-inputs-container>
                <flux:field>
                    <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">
                        Address
                    </flux:label>
                    <flux:modal.trigger name="address-modal">
                        <button
                            type="button"
                            class="w-full text-left font-secondary text-table-row bg-light-primary dark:bg-dark-surface text-light-txt-body dark:text-dark-txt-primary border border-light-bd-default dark:border-dark-bd-default rounded-lg px-3 py-2.5 transition-shadow duration-200 focus:outline-none focus:ring-2 focus:ring-secondary/50"
                        >
                            @if ($address)
                                {{ $address }}
                            @else
                                <span class="text-light-txt-muted dark:text-dark-txt-muted">Tap to set address</span>
                            @endif
                        </button>
                    </flux:modal.trigger>
                    <flux:error name="address" />
                </flux:field>

                <x-input wire:model.blur="phone_number"  type="number" label="Phone number" pattern="[0-9]{10}" placeholder="e.g. 09463637401"/>
                <x-select wire:model.live="commuter_type" label="Commuter type" size="lg">
                    <x-select-option>Regular</x-select-option>
                    <x-select-option>Senior Citizen</x-select-option>
                    <x-select-option>PWD</x-select-option>
                    <x-select-option>Student</x-select-option>
                </x-select>
            </x-inputs-container>
        </div>
    @endif

    @if($step === 3 && $role === 'operator')
        <div class="space-y-4">
            <x-inputs-container>
                <flux:field>
                    <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">
                        Home Address
                    </flux:label>
                    <flux:modal.trigger name="address-modal">
                        <button
                            type="button"
                            class="w-full text-left font-secondary text-table-row bg-light-primary dark:bg-dark-surface text-light-txt-body dark:text-dark-txt-primary border border-light-bd-default dark:border-dark-bd-default rounded-lg px-3 py-2.5 transition-shadow duration-200 focus:outline-none focus:ring-2 focus:ring-secondary/50"
                        >
                            @if ($address)
                                {{ $address }}
                            @else
                                <span class="text-light-txt-muted dark:text-dark-txt-muted">Tap to set address</span>
                            @endif
                        </button>
                    </flux:modal.trigger>
                    <flux:error name="address" />
                </flux:field>

                <x-input wire:model.blur="phone_number" label="Phone no." placeholder="63+ 912 345 6789"/>
            </x-inputs-container>

            <div class="flex items-center justify-between pt-2 pb-1 border-t border-light-bd-default dark:border-dark-bd-default">
                <div>
                    <p class="font-secondary text-table-row font-semibold text-light-txt-primary dark:text-dark-txt-primary">Vehicle Registration</p>
                    <p class="font-secondary text-timestamp text-light-txt-muted dark:text-dark-txt-muted">
                        {{ count($vehicles) }} vehicle{{ count($vehicles) !== 1 ? 's' : '' }} added
                    </p>
                </div>
                <flux:button wire:click="addVehicle" size="sm" icon="plus" variant="primary" class="font-secondary">Add Vehicle</flux:button>
            </div>

        @forelse ($vehicles as $index => $vehicle)
            @if($this->vehicleIsComplete($vehicle))
                {{-- Collapsed summary card + Edit modal, once all required fields are filled --}}
                <div wire:key="vehicle-card-{{ $index }}-{{ md5(json_encode($vehicle)) }}"
                    class="flex items-center justify-between gap-3 rounded-lg border border-light-bd-default dark:border-dark-bd-default bg-light-secondary dark:bg-dark-surface p-3">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-9 h-9 rounded-lg bg-light-subtle dark:bg-dark-subtle flex items-center justify-center shrink-0">
                            <flux:icon.truck class="w-4 h-4 text-light-txt-muted dark:text-dark-txt-muted" />
                        </div>
                        <div class="min-w-0">
                            <p class="font-secondary text-table-row font-medium text-light-txt-primary dark:text-dark-txt-primary truncate">
                                {{ $vehicle['plate_number'] }}
                                <span class="font-normal text-light-txt-muted dark:text-dark-txt-muted">· {{ $vehicle['vehicle_type'] }}</span>
                            </p>
                            <p class="font-secondary text-timestamp text-light-txt-muted dark:text-dark-txt-muted truncate">
                                {{ $vehicle['route'] }} · {{ $vehicle['seat_capacity'] }} seats
                                @if(!empty($vehicle['group_number'])) · Group {{ $vehicle['group_number'] }} @endif
                            </p>
                            <p class="font-secondary text-timestamp text-light-txt-muted dark:text-dark-txt-muted truncate">
                                Engine: {{ $vehicle['engine_number'] }} · Body: {{ $vehicle['body_number'] }} · Chassis: {{ $vehicle['chassis_number'] }}
                            </p>
                            <p class="font-secondary text-timestamp text-light-txt-muted dark:text-dark-txt-muted truncate">
                                Validity date of franchise: {{ \Illuminate\Support\Carbon::parse($vehicle['franchise_expiry_date'])->format('M d, Y') }}
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <flux:modal.trigger name="edit-vehicle-{{ $index }}">
                            <button type="button"
                                class="font-secondary text-timestamp font-medium px-2.5 py-1 rounded-md text-light-txt-muted dark:text-dark-txt-muted hover:bg-light-subtle dark:hover:bg-dark-subtle cursor-pointer">
                                Edit
                            </button>
                        </flux:modal.trigger>
                        @if(count($vehicles) > 1)
                            <button wire:click="removeVehicle({{ $index }})" type="button"
                                class="font-secondary text-timestamp font-medium px-2.5 py-1 rounded-md border border-danger/40 dark:border-dark-danger/40 bg-danger/10 dark:bg-dark-danger/10 text-danger dark:text-dark-danger hover:bg-danger/20 dark:hover:bg-dark-danger/20 cursor-pointer">
                                Delete
                            </button>
                        @endif
                    </div>
                </div>

                <flux:modal
                    name="edit-vehicle-{{ $index }}"
                    :closable="false"
                    class="w-[calc(100%-2rem)] sm:max-w-lg md:max-w-2xl lg:max-w-3xl mx-auto max-h-[80vh] sm:max-h-[90vh] overflow-hidden rounded-xl"
                >
                    <div class="flex flex-col max-h-[calc(80vh-2rem)] sm:max-h-[calc(90vh-2rem)] overflow-y-auto overscroll-contain p-4 sm:p-6 !pr-4 sm:!pr-6 space-y-5">
                        <!-- Header -->
                        <div class="flex items-start justify-between">
                            <div>
                                <flux:heading size="xl" class="!font-primary !font-bold text-light-txt-primary dark:text-dark-txt-primary">
                                    Edit Vehicle #{{ $index + 1 }}
                                </flux:heading>
                                <flux:text class="mt-1 font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">
                                    Update this vehicle's details.
                                </flux:text>
                            </div>
                            <flux:modal.close>
                                <button type="button" class="p-1 rounded-full hover:bg-light-subtle dark:hover:bg-dark-subtle text-light-txt-muted dark:text-dark-txt-muted -mt-1">
                                    <flux:icon name="x-mark" class="w-5 h-5" />
                                </button>
                            </flux:modal.close>
                        </div>

                        <!-- Vehicle fields (unchanged) -->
                        <x-inputs-container class="grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <flux:label class="mb-3 font-secondary text-table-row text-light-txt-body dark:text-dark-txt-primary">Vehicle Type</flux:label>
                                <flux:select wire:model.live="vehicles.{{ $index }}.vehicle_type" placeholder="Choose type..." size="sm">
                                    @foreach ($this->getVehicleType as $vehicleType)
                                        <flux:select.option>{{ $vehicleType->vehicle_type }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                                <flux:error name="vehicles.{{ $index }}.vehicle_type" />
                            </div>

                            <div>
                                <x-input wire:model.blur="vehicles.{{ $index }}.plate_number" label="Plate number" placeholder="e.g. ABC-123" size="sm" />
                            </div>

                            <div>
                                <x-input wire:model.blur="vehicles.{{ $index }}.seat_capacity" type="number" label="Seat capacity" max="50" min="10" />
                            </div>

                            <div>
                                <flux:label class="mb-3 font-secondary text-table-row text-light-txt-body dark:text-dark-txt-primary">Route</flux:label>
                                <flux:select wire:model.live="vehicles.{{ $index }}.route" placeholder="Select route for this vehicle..." size="sm">
                                    @foreach ($this->getRoute as $route)
                                        @if ($route->operatorTicketRate->vehicle_type === $this->vehicles[$index]['vehicle_type'])
                                            <flux:select.option value="{{ $route->terminal }}">
                                                Iriga Terminal to
                                                <strong>{{ $route->terminal }}</strong>
                                            </flux:select.option>
                                        @endif
                                    @endforeach
                                </flux:select>
                                <flux:error name="vehicles.{{ $index }}.route" />
                            </div>

                            @if(in_array($this->vehicles[$index]['vehicle_type'], ['Bus', 'UV-express']))
                                <div>
                                    <flux:label class="mb-3 font-secondary text-table-row text-light-txt-body dark:text-dark-txt-primary">Group No.</flux:label>
                                    <flux:select wire:model.live="vehicles.{{ $index }}.group_number" placeholder="Select group for this vehicle..." size="sm">
                                        <flux:select.option value="1">1</flux:select.option>
                                        <flux:select.option value="2">2</flux:select.option>
                                    </flux:select>
                                    <flux:error name="vehicles.{{ $index }}.group_number" />
                                </div>
                            @endif

                            <div>
                                <x-input wire:model.blur="vehicles.{{ $index }}.engine_number" label="Engine number" placeholder="e.g. EN-12345" size="sm" />
                            </div>
                            <div>
                                <x-input wire:model.blur="vehicles.{{ $index }}.body_number" label="Body number" placeholder="e.g. BD-12345" size="sm" />
                            </div>
                            <div>
                                <x-input wire:model.blur="vehicles.{{ $index }}.chassis_number" label="Chassis number" placeholder="e.g. CH-123456789" size="sm" />
                            </div>
                        </x-inputs-container>

                        <!-- Compliance documents -->
                        <div class="border-t border-light-bd-default dark:border-dark-bd-default pt-3 space-y-3">
                            <p class="font-secondary text-timestamp font-medium uppercase tracking-wide text-light-txt-muted dark:text-dark-txt-muted">Compliance documents</p>

                            <div class="flex items-start gap-3">
                                <flux:checkbox wire:model.live="vehicles.{{ $index }}.has_franchise" />
                                <div class="flex-1 min-w-0 space-y-1">
                                    <flux:label class="font-secondary text-table-row text-light-txt-body dark:text-dark-txt-primary">
                                        Franchise verified
                                    </flux:label>
                                    <div class="flex items-center gap-2">
                                        <span class="font-secondary text-timestamp text-light-txt-muted dark:text-dark-txt-muted whitespace-nowrap">Validity Date of Franchise:</span>
                                        <flux:input
                                            type="date"
                                            wire:model.live="vehicles.{{ $index }}.franchise_expiry_date"
                                            :disabled="!$this->vehicles[$index]['has_franchise']"
                                            size="sm"
                                            class="flex-1"
                                        />
                                    </div>
                                    <flux:error name="vehicles.{{ $index }}.has_franchise" />
                                    <flux:error name="vehicles.{{ $index }}.franchise_expiry_date" />
                                </div>
                            </div>
                        </div>

                        <!-- Footer -->
                        <div class="flex flex-col-reverse sm:flex-row justify-end items-stretch sm:items-center gap-2 pt-2 border-t border-light-bd-default dark:border-dark-bd-default">
                            <flux:modal.close class="w-full sm:w-auto">
                                <flux:button type="button" variant="ghost" class="w-full sm:w-auto justify-center font-secondary">
                                    Cancel
                                </flux:button>
                            </flux:modal.close>
                            <flux:modal.close class="w-full sm:w-auto">
                                <flux:button type="button" variant="primary" class="w-full sm:w-auto justify-center font-secondary">
                                    Done
                                </flux:button>
                            </flux:modal.close>
                        </div>
                    </div>
                </flux:modal>

            @else
                {{-- Open inline form, visible immediately, no modal needed --}}
                <div wire:key="vehicle-{{ $index }}"
                    class="rounded-lg border border-light-bd-default dark:border-dark-bd-default bg-light-subtle dark:bg-dark-subtle p-4 space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-body">Vehicle {{ $index + 1 }}</span>
                        @if(count($vehicles) > 1)
                            <button wire:click="removeVehicle({{ $index }})" type="button"
                                class="font-secondary text-timestamp font-medium px-2.5 py-1 rounded-md border border-danger/40 dark:border-dark-danger/40 bg-danger/10 dark:bg-dark-danger/10 text-danger dark:text-dark-danger hover:bg-danger/20 dark:hover:bg-dark-danger/20 cursor-pointer">
                                Delete
                            </button>
                        @endif
                    </div>
                    <x-inputs-container>
                        <div>
                            <flux:label class="mb-3 font-secondary text-table-row text-light-txt-body dark:text-dark-txt-primary">Vehicle Type</flux:label>
                            <flux:select wire:model.live="vehicles.{{ $index }}.vehicle_type" placeholder="Choose type..." size="sm">
                                @foreach ($this->getVehicleType as $vehicleType)
                                    <flux:select.option>{{ $vehicleType->vehicle_type }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:error name="vehicles.{{ $index }}.vehicle_type" />
                        </div>

                        <div>
                            <x-input wire:model.blur="vehicles.{{ $index }}.plate_number" label="Plate number" placeholder="e.g. ABC-123" size="sm" />
                        </div>

                        <div>
                            <x-input wire:model.blur="vehicles.{{ $index }}.seat_capacity" size="sm" type="number" label="Seat capacity" max="50" min="10"/>
                        </div>

                        <div>
                            <flux:label class="mb-3 font-secondary text-table-row text-light-txt-body dark:text-dark-txt-primary">Route</flux:label>
                            <flux:select wire:model.live="vehicles.{{ $index }}.route" placeholder="Select route for this vehicle..." size="sm">
                                @foreach ($this->getRoute as $route)
                                    @if ($route->operatorTicketRate->vehicle_type === $this->vehicles[$index]['vehicle_type'])
                                        <flux:select.option value="{{ $route->terminal }}">
                                            Iriga Terminal to
                                            <strong>{{ $route->terminal }}</strong>
                                        </flux:select.option>
                                    @endif
                                @endforeach
                            </flux:select>
                            <flux:error name="vehicles.{{ $index }}.route" />
                        </div>

                        @if(in_array($this->vehicles[$index]['vehicle_type'], ['Bus', 'UV-express']))
                            <div>
                                <flux:label class="mb-3 font-secondary text-table-row text-light-txt-body dark:text-dark-txt-primary">Group No.</flux:label>
                                <flux:select wire:model.live="vehicles.{{ $index }}.group_number" placeholder="Select group for this vehicle..." size="sm">
                                    <flux:select.option value="1">1</flux:select.option>
                                    <flux:select.option value="2">2</flux:select.option>
                                </flux:select>
                                <flux:error name="vehicles.{{ $index }}.group_number" />
                            </div>
                        @endif

                        <div>
                            <x-input wire:model.blur="vehicles.{{ $index }}.engine_number" label="Engine number" placeholder="e.g. EN-12345" size="sm" />
                        </div>
                        <div>
                            <x-input wire:model.blur="vehicles.{{ $index }}.body_number" label="Body number" placeholder="e.g. BD-12345" size="sm" />
                        </div>
                        <div>
                            <x-input wire:model.blur="vehicles.{{ $index }}.chassis_number" label="Chassis number" placeholder="e.g. CH-123456789" size="sm" />
                        </div>
                    </x-inputs-container>

                    <div class="border-t border-light-bd-default dark:border-dark-bd-default pt-3 space-y-3">
                        <p class="font-secondary text-timestamp font-medium uppercase tracking-wide text-light-txt-muted dark:text-dark-txt-muted">Compliance documents</p>

                        <div class="flex items-start gap-3">
                            <flux:checkbox wire:model.live="vehicles.{{ $index }}.has_franchise" />
                            <div class="flex-1 min-w-0 space-y-1">
                                <flux:label class="font-secondary text-table-row text-light-txt-body dark:text-dark-txt-primary">
                                    Franchise verified
                                    <span class="ml-1 font-normal text-light-txt-muted dark:text-dark-txt-muted">(admin confirms document was submitted)</span>
                                </flux:label>
                                <div class="flex items-center gap-2">
                                    <span class="font-secondary text-timestamp text-light-txt-muted dark:text-dark-txt-muted whitespace-nowrap">Validity Date of Franchise:</span>
                                    <flux:input
                                        type="date"
                                        wire:model.live="vehicles.{{ $index }}.franchise_expiry_date"
                                        :disabled="!$this->vehicles[$index]['has_franchise']"
                                        size="sm"
                                        class="flex-1"
                                    />
                                </div>
                                <flux:error name="vehicles.{{ $index }}.has_franchise" />
                                <flux:error name="vehicles.{{ $index }}.franchise_expiry_date" />
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        @empty
            <div class="rounded-lg border border-dashed border-light-bd-strong dark:border-dark-bd-strong p-6 text-center">
                <flux:icon.truck class="w-8 h-8 mx-auto text-light-txt-muted dark:text-dark-txt-muted mb-2" />
                <p class="font-secondary text-table-row text-light-txt-muted dark:text-dark-txt-muted">No vehicles added yet.</p>
                <p class="font-secondary text-timestamp text-light-txt-muted dark:text-dark-txt-muted mt-1">Click "Add Vehicle" to register one.</p>
            </div>
        @endforelse
        </div>
    @endif

    @if($step === 4)
        <x-card>
            <div @class([
                'flex items-center gap-3 p-4 border-b border-light-bd-default dark:border-dark-bd-default',
                'bg-info/10 dark:bg-dark-info/10'       => $card_state === 'ready',
                'bg-success/10 dark:bg-dark-success/10' => $card_state === 'success',
                'bg-danger/10 dark:bg-dark-danger/10'   => $card_state === 'warn',
            ])>
                <div @class([
                    'w-10 h-10 rounded-full flex items-center justify-center shrink-0',
                    'bg-info/20 dark:bg-dark-info/20'       => $card_state === 'ready',
                    'bg-success/20 dark:bg-dark-success/20' => $card_state === 'success',
                    'bg-danger/20 dark:bg-dark-danger/20'   => $card_state === 'warn',
                ])>
                    @if($card_state === 'ready')
                        <flux:icon name="credit-card" class="w-5 h-5 text-info dark:text-dark-info" />
                    @elseif($card_state === 'success')
                        <flux:icon name="check-circle" class="w-5 h-5 text-success dark:text-dark-success" />
                    @else
                        <flux:icon name="exclamation-triangle" class="w-5 h-5 text-danger dark:text-dark-danger" />
                    @endif
                </div>

                <div class="flex-1 min-w-0">
                    <p @class([
                        'font-secondary text-table-row font-medium',
                        'text-info dark:text-dark-info'       => $card_state === 'ready',
                        'text-success dark:text-dark-success' => $card_state === 'success',
                        'text-danger dark:text-dark-danger'   => $card_state === 'warn',
                    ])>
                        @if($card_state === 'ready')   Get your RFID card ready
                        @elseif($card_state === 'success') Card scanned successfully!
                        @else Input field lost focus
                        @endif
                    </p>
                    <p @class([
                        'font-secondary text-timestamp',
                        'text-info dark:text-dark-info'       => $card_state === 'ready',
                        'text-success dark:text-dark-success' => $card_state === 'success',
                        'text-danger dark:text-dark-danger'   => $card_state === 'warn',
                    ])>
                        @if($card_state === 'ready')
                            Hold the card near the reader — the number fills in automatically.
                        @elseif($card_state === 'success')
                            UID {{ $card_number }} captured. Click × to scan a different card.
                        @else
                            Click the input field below to re-focus, then tap the rfid card.
                        @endif
                    </p>
                </div>

                @if($card_state === 'success')
                    <button wire:click="clearCard"
                        class="text-light-txt-muted hover:text-light-txt-body dark:text-dark-txt-muted dark:hover:text-dark-txt-primary transition"
                        aria-label="Clear card">
                        <flux:icon name="x-mark" class="w-6 h-6" />
                    </button>
                @endif
            </div>

            <div class="p-4">
                <flux:field>
                    <x-input
                        id="rfid-input"
                        wire:model="card_number"
                        label="Card UID"
                        name="card_number"
                        wire:keydown.enter="cardScanned"
                        wire:focus="cardFocused"
                        wire:blur="cardBlurred"
                        placeholder="Tap your card on the reader..."
                        autocomplete="off"
                        class="font-mono tracking-widest"
                        autofocus
                    />
                    <flux:error name="card_number" />
                </flux:field>
            </div>
        </x-card>
    @endif

    @if($step === 5)
        <div class="space-y-4">
            <p class="font-secondary text-timestamp font-medium uppercase tracking-wide text-light-txt-muted dark:text-dark-txt-muted">
                Review before saving
            </p>

            <div class="border border-light-bd-default dark:border-dark-bd-default rounded-md p-5">
            {{-- Identity header --}}
                <div class="pb-5 border-b border-light-bd-default dark:border-dark-bd-default flex items-center gap-3">
                    <div class="w-12 h-12 rounded-full bg-secondary text-primary dark:text-dark-txt-primary flex items-center justify-center font-secondary text-table-row font-medium">
                        {{ strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1)) }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <span class="font-primary text-lg font-bold text-light-txt-primary dark:text-dark-txt-primary">
                            {{ $first_name }} {{ $last_name }}
                        </span>
                    </div>
                    <div>
                        @if ($this->role === 'operator')
                            <flux:badge color="blue" size="sm" class="font-secondary text-badge">Operator</flux:badge>
                        @else
                            <flux:badge color="yellow" size="sm" class="font-secondary text-badge">Commuter</flux:badge>
                        @endif
                    </div>
                </div>



                {{-- Account details table --}}
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>Field</flux:table.column>
                        <flux:table.column>Details</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        <flux:table.row>
                            <flux:table.cell class="flex items-center gap-2 font-secondary font-medium text-light-txt-body dark:text-dark-txt-primary">
                                <flux:icon.home class="w-4 h-4 text-light-txt-muted dark:text-dark-txt-muted shrink-0" />
                                Home address
                            </flux:table.cell>
                            <flux:table.cell class="font-secondary break-words">{{ $address }}</flux:table.cell>
                        </flux:table.row>

                        <flux:table.row>
                            <flux:table.cell class="flex items-center gap-2 font-secondary font-medium text-light-txt-body dark:text-dark-txt-primary">
                                <flux:icon.envelope class="w-4 h-4 text-light-txt-muted dark:text-dark-txt-muted shrink-0" />
                                Email
                            </flux:table.cell>
                            <flux:table.cell class="font-secondary break-words">{{ $email_address ?: '—' }}</flux:table.cell>
                        </flux:table.row>

                        <flux:table.row>
                            <flux:table.cell class="flex items-center gap-2 font-secondary font-medium text-light-txt-body dark:text-dark-txt-primary">
                                <flux:icon.phone class="w-4 h-4 text-light-txt-muted dark:text-dark-txt-muted shrink-0" />
                                Phone
                            </flux:table.cell>
                            <flux:table.cell class="font-secondary">{{ $phone_number }}</flux:table.cell>
                        </flux:table.row>

                        @if($role === 'commuter')
                            <flux:table.row>
                                <flux:table.cell class="flex items-center gap-2 font-secondary font-medium text-light-txt-body dark:text-dark-txt-primary">
                                    <flux:icon.user class="w-4 h-4 text-light-txt-muted dark:text-dark-txt-muted shrink-0" />
                                    Commuter type
                                </flux:table.cell>
                                <flux:table.cell class="font-secondary">{{ $commuter_type }}</flux:table.cell>
                            </flux:table.row>
                        @endif

                        <flux:table.row>
                            <flux:table.cell class="flex items-center gap-2 font-secondary font-medium text-light-txt-body dark:text-dark-txt-primary">
                                <flux:icon.credit-card class="w-4 h-4 text-light-txt-muted dark:text-dark-txt-muted shrink-0" />
                                Has card
                            </flux:table.cell>
                            <flux:table.cell class="font-secondary">
                                @if($card_number)
                                    <flux:badge color="green" size="sm">Yes</flux:badge>
                                @else
                                    <flux:badge color="zinc" size="sm">No</flux:badge>
                                @endif
                            </flux:table.cell>
                        </flux:table.row>
                    </flux:table.rows>
                </flux:table>
            </div>

            {{-- Operator vehicles table --}}
            @if($role === 'operator')
                <div class="space-y-2 pt-2">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <p class="font-secondary text-timestamp font-medium uppercase tracking-wide text-light-txt-muted dark:text-dark-txt-muted">
                                Vehicles
                            </p>
                        </div>
                        <flux:badge size="sm" color="zinc" class="font-secondary text-badge">
                            {{ count($vehicles) }} Vehicle{{ count($vehicles) !== 1 ? 's' : '' }}
                        </flux:badge>
                    </div>

                    <div class="rounded-lg border border-light-bd-default dark:border-dark-bd-default overflow-hidden">
                        <flux:table>
                            <flux:table.columns class="bg-light-subtle dark:bg-dark-subtle">
                                <flux:table.column align="center">Plate No.</flux:table.column>
                                <flux:table.column align="center">Type</flux:table.column>
                                <flux:table.column align="center">Route</flux:table.column>
                                <flux:table.column align="center">Seats</flux:table.column>
                                <flux:table.column align="center">Group</flux:table.column>
                                <flux:table.column align="center">Engine No.</flux:table.column>
                                <flux:table.column align="center">Body No.</flux:table.column>
                                <flux:table.column align="center">Chassis No.</flux:table.column>
                                <flux:table.column align="center">Validity Date of Franchise</flux:table.column>
                            </flux:table.columns>
                            <flux:table.rows>
                                @foreach ($vehicles as $index => $vehicle)
                                    <flux:table.row :key="'summary-vehicle-' . $index">
                                        <flux:table.cell align="center" variant="strong" class="font-secondary text-table-row whitespace-nowrap">
                                            {{ $vehicle['plate_number'] }}
                                        </flux:table.cell>
                                        <flux:table.cell align="center" class="font-secondary text-table-row whitespace-nowrap">
                                            {{ $vehicle['vehicle_type'] }}
                                        </flux:table.cell>
                                        <flux:table.cell align="center" class="font-secondary text-table-row">
                                            {{ $vehicle['route'] }}
                                        </flux:table.cell>
                                        <flux:table.cell align="center" class="font-secondary text-table-row">
                                            {{ $vehicle['seat_capacity'] }}
                                        </flux:table.cell>
                                        <flux:table.cell align="center" class="py-0">
                                            @if(!empty($vehicle['group_number']))
                                                <flux:badge size="sm" color="zinc">{{ $vehicle['group_number'] }}</flux:badge>
                                            @else
                                                <span class="font-secondary text-table-row text-light-txt-muted dark:text-dark-txt-muted">—</span>
                                            @endif
                                        </flux:table.cell>
                                        <flux:table.cell align="center" class="font-secondary text-table-row whitespace-nowrap">
                                            {{ $vehicle['engine_number'] }}
                                        </flux:table.cell>
                                        <flux:table.cell align="center" class="font-secondary text-table-row whitespace-nowrap">
                                            {{ $vehicle['body_number'] }}
                                        </flux:table.cell>
                                        <flux:table.cell align="center" class="font-secondary text-table-row whitespace-nowrap">
                                            {{ $vehicle['chassis_number'] }}
                                        </flux:table.cell>
                                        <flux:table.cell align="center" class="font-secondary text-table-row whitespace-nowrap">
                                            {{ \Illuminate\Support\Carbon::parse($vehicle['franchise_expiry_date'])->format('M d, Y') }}
                                        </flux:table.cell>
                                    </flux:table.row>
                                @endforeach
                            </flux:table.rows>
                        </flux:table>
                    </div>
                </div>
            @endif

            <x-text class="font-secondary text-timestamp text-light-txt-muted dark:text-dark-txt-muted leading-relaxed">
                A welcome message with login credentials will be sent to the user's registered email. Please inform the user to check their Gmail accounts.
            </x-text>
        </div>
    @endif

    <div class="flex items-center gap-2 pt-6">
        <flux:spacer />
        @if($step > 1)
            <flux:button size="sm" variant="ghost" wire:click="back" class="font-secondary">Back</flux:button>
        @endif
        @if($step < 5)
            @if ($step === 4 && $this->card_number === '')
                <flux:button size="sm" variant="primary" wire:click="stepSkipped" class="font-secondary">Skip</flux:button>
            @else
                <flux:button size="sm" variant="primary" wire:click="next" class="font-secondary">Continue</flux:button>
            @endif
        @else
            <flux:button size="sm" variant="primary" wire:click="register" class="font-secondary">
                Register this {{ ucfirst($this->role) }}
            </flux:button>
        @endif
    </div>

    {{-- ADDRESS MODAL – updated with Province field --}}
    <flux:modal
        name="address-modal"
        :closable="false"
        class="w-[calc(100%-2rem)] sm:max-w-lg md:max-w-2xl lg:max-w-3xl mx-auto rounded-xl overflow-hidden"
        x-on:address-saved.window="$flux.modal('address-modal').close()"
    >
        <div class="flex flex-col p-4 sm:p-6 !pr-4 sm:!pr-6 space-y-5 overflow-y-auto max-h-[70vh]">
            <!-- Header -->
            <div class="flex items-start justify-between">
                <div>
                    <flux:heading size="xl" class="!font-primary !font-bold text-light-txt-primary dark:text-dark-txt-primary">
                        Set your address
                    </flux:heading>
                    <flux:text class="mt-1 font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">
                        Please provide the complete address.
                    </flux:text>
                </div>
                <flux:modal.close>
                    <button type="button" class="p-1 rounded-full hover:bg-light-subtle dark:hover:bg-dark-subtle text-light-txt-muted dark:text-dark-txt-muted -mt-1">
                        <flux:icon name="x-mark" class="w-5 h-5" />
                    </button>
                </flux:modal.close>
            </div>

            <!-- Fields -->
            <flux:field>
                <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">
                    House No. / Subdivision
                    <span class="ml-2 text-light-txt-muted dark:text-dark-txt-muted font-normal">(optional)</span>
                </flux:label>
                <flux:input
                    wire:model="house_subd"
                    placeholder="e.g. Blk 3 Lot 5, Hillside Subd."
                    class="font-secondary text-table-row bg-light-primary dark:bg-dark-surface text-light-txt-body dark:text-dark-txt-primary border-light-bd-default dark:border-dark-bd-default placeholder:text-light-txt-muted dark:placeholder:text-dark-txt-muted"
                />
                <flux:error name="house_subd" />
            </flux:field>

            <flux:field>
                <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">Zone No.</flux:label>
                <flux:input
                    type="number"
                    wire:model.blur="zone_number"
                    min="1"
                    max="20"
                    placeholder="e.g. 3"
                    class="font-secondary text-table-row bg-light-primary dark:bg-dark-surface text-light-txt-body dark:text-dark-txt-primary border-light-bd-default dark:border-dark-bd-default placeholder:text-light-txt-muted dark:placeholder:text-dark-txt-muted"
                />
                <flux:error name="zone_number" />
            </flux:field>

            <flux:field>
                <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">Barangay</flux:label>
                <flux:input
                    wire:model.blur="barangay"
                    placeholder="e.g. San Roque"
                    class="font-secondary text-table-row bg-light-primary dark:bg-dark-surface text-light-txt-body dark:text-dark-txt-primary border-light-bd-default dark:border-dark-bd-default placeholder:text-light-txt-muted dark:placeholder:text-dark-txt-muted"
                />
                <flux:error name="barangay" />
            </flux:field>

            <flux:field>
                <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">Municipality / City</flux:label>
                <flux:input
                    wire:model.blur="municipality"
                    placeholder="e.g. Iriga City"
                    class="font-secondary text-table-row bg-light-primary dark:bg-dark-surface text-light-txt-body dark:text-dark-txt-primary border-light-bd-default dark:border-dark-bd-default placeholder:text-light-txt-muted dark:placeholder:text-dark-txt-muted"
                />
                <flux:error name="municipality" />
            </flux:field>

            {{-- NEW Province field --}}
            <flux:field>
                <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">Province</flux:label>
                <flux:input
                    wire:model.blur="province"
                    placeholder="e.g. Camarines Sur"
                    class="font-secondary text-table-row bg-light-primary dark:bg-dark-surface text-light-txt-body dark:text-dark-txt-primary border-light-bd-default dark:border-dark-bd-default placeholder:text-light-txt-muted dark:placeholder:text-dark-txt-muted"
                />
                <flux:error name="province" />
            </flux:field>

            <!-- Footer -->
            <div class="flex flex-col-reverse sm:flex-row justify-end items-stretch sm:items-center gap-2 pt-2 border-t border-light-bd-default dark:border-dark-bd-default">
                <flux:modal.close class="w-full sm:w-auto">
                    <flux:button type="button" variant="ghost" class="w-full sm:w-auto justify-center font-secondary">
                        Cancel
                    </flux:button>
                </flux:modal.close>
                <flux:button
                    type="button"
                    variant="primary"
                    icon="check"
                    wire:click="saveAddress"
                    wire:loading.attr="disabled"
                    wire:target="saveAddress"
                    class="font-secondary w-full sm:w-auto"
                >
                    Save address
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <script>
        document.addEventListener('livewire:initialized', () => {
            Livewire.on('focus-rfid-input', () => {
                setTimeout(() => {
                    const el = document.getElementById('rfid-input');
                    if (el) el.focus();
                }, 50);
            });
        });
    </script>
</div>
<?php

use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use App\Events\RegistrationTapCardEvent;
use Livewire\Component;
use Illuminate\Support\Str;

//facades
use Illuminate\Support\Facades\DB;

//models
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

    // Basic info
    public string $role = '';
    public string $first_name = '';
    public string $last_name = '';
    public string $age;
    public string $email_address = '';
    public string $phone_number;

    // commuter details
    public string $date_of_birth = '';
    public string $commuter_type = 'Regular';
    public string $address = '';
    public string $card_number = '';
    public string $new_card_id = '';

    // Card scan state
    public bool $card_focused = true;
    public string $card_state = 'warn'; 

    // Operator details
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
         'seat_capacity' => ''
         ],
    ];

    // Address modal fields
    public string $house_subd = '';
    public ?int $zone_number = null;
    public string $barangay = '';

    // Iriga City, Camarines Sur barangays
    public const BARANGAYS = [
        'Antipolo',
        'Cristo Rey',
        'Del Rosario (Banao)',
        'Francia',
        'La Anunciacion',
        'La Medalla',
        'La Purisima',
        'La Trinidad',
        'Niño Jesus',
        'Perpetual Help',
        'Sagrada',
        'Salvacion',
        'San Agustin',
        'San Andres',
        'San Antonio',
        'San Francisco (Pob.)',
        'San Isidro',
        'San Jose',
        'San Juan',
        'San Miguel',
        'San Nicolas',
        'San Pedro',
        'San Rafael',
        'San Ramon',
        'San Roque (Pob.)',
        'Santiago',
        'San Vicente Norte',
        'San Vicente Sur',
        'Santa Cruz Norte',
        'Santa Cruz Sur',
        'Santa Elena',
        'Santa Isabel',
        'Santa Maria',
        'Santa Teresita',
        'Santo Domingo',
        'Santo Niño',
    ];

    #[Computed]
    public function getVehicleType() {  
        return OperatorTicketRate::get('vehicle_type');
    }

    #[Computed]
    public function getBarangays()
    {
        return self::BARANGAYS;
    }

    public function stepSkipped() {
        $this->skipped = true;
        $this->next();
    }

    // public function updated($property)
    // {
    //     if (in_array($property, ['first_name', 'last_name'])) {

    //         if (!empty($this->first_name) && !empty($this->last_name)) {

    //             $prefix = match ($this->role) {
    //                 'commuter' => '11284711',
    //                 'operator' => '11284712',
    //                 'cashier'  => '11284713',
    //                 'admin'    => '11284714',
    //                 default    => '11284710',
    //             };

    //             $sequence = str_pad(
    //                 random_int(1, 9999),
    //                 4,
    //                 '0',
    //                 STR_PAD_LEFT
    //             );

    //             $baseUsername = $prefix . $sequence;

    //             $this->username = $this->ensureUniqueUsername($baseUsername);

    //             if (empty($this->password)) {
    //                 $this->password = str_pad(
    //                     random_int(0, 99999999),
    //                     8,
    //                     '0',
    //                     STR_PAD_LEFT
    //                 );
    //             }
    //         }
    //     }

    //     // Clear stale validation errors on the specific vehicle field the user just edited
    //     if (str_starts_with($property, 'vehicles.')) {
    //         $this->resetValidation($property);
    //     }
    // }


    // protected function ensureUniqueUsername(string $username): string
    // {
    //     $original = $username;
    //     $counter = 1;

    //     while (User::where('username', $username)->exists()) {
    //         $username = $original . $counter;
    //         $counter++;
    //     }

    //     return $username;
    // }

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
            'house_subd'  => 'nullable|string|max:255',
            'zone_number' => 'required|integer|min:1|max:20',
            'barangay'    => 'required|string|in:' . implode(',', self::BARANGAYS),
        ]);

        $parts = array_filter([
            $data['house_subd'] !== '' ? $data['house_subd'] : null,
            'Zone ' . $data['zone_number'],
            $data['barangay'],
            'Iriga City',
            'Camarines Sur',
        ]);

        $this->address = implode(', ', $parts);
        $this->resetValidation();

        $this->dispatch('address-saved');
    }

    public function next(): void
    {
        if ($this->step === 1) {
            $this->validate(['role' => 'required|in:commuter,operator']);
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
                    'commuter_type'  => 'required',
                ]);
            } else {
                $this->validate([
                    'address'                    => 'required|min:5',
                    'phone_number'               => 'required|numeric',
                    'vehicles'                   => 'required|array|min:1',
                    'vehicles.*.vehicle_type'    => 'required|string',
                    'vehicles.*.plate_number'    => 'required|unique:vehicles,plate_number',
                    'vehicles.*.route'           => 'required|string',
                    'vehicles.*.seat_capacity'   => 'required|integer|min:10|max:50',
                    'vehicles.*.group_number'    => 'nullable|integer|min:1|max:2',
                ]);
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
        $this->vehicles[] = ['vehicle_type' => '', 'plate_number' => '', 'group_number' => '', 'route'=>''];
    }

    public function removeVehicle(int $index): void
    {
        if (count($this->vehicles) > 1) {
            array_splice($this->vehicles, $index, 1);
        }
    }

    public function vehicleIsComplete(array $vehicle): bool {
        $required = ['vehicle_type', 'plate_number', 'route', 'seat_capacity'];
        foreach ($required as $field) {
            if (!filled($vehicle[$field] ?? null)) {
                return false;
            }
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
                text: 'User has been successfully registered.'
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

    // public function mount() {
    //     dd($this->getRoute());
    // }
};
?>

<div>
    <flux:breadcrumbs>
        <flux:breadcrumbs.item href="{{ route('admin.users') }}" wire:navigate>Users</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>Registration</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <flux:heading size="xl" class="!font-primary !font-bold my-8 text-light-txt-primary dark:text-dark-txt-primary">
        {{ $this->role ? 'Registration for ' . ucfirst($this->role) : 'Register New User' }}
    </flux:heading>

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
        @error('role')
            <p class="font-secondary text-timestamp text-danger dark:text-dark-danger mt-2">{{ $message }}</p>
        @enderror
    @endif

    @if($step === 2)
        <div class="space-y-4">
                
            <x-inputs-container>
                <x-input wire:model="first_name" label="First name" placeholder="e.g. Juan" />
                <x-input wire:model="last_name"  label="Last name"  placeholder="e.g. dela Cruz" />
                <x-input wire:model="email_address"   label="Email address" placeholder="juandelacruz@gmail.com" />
                <x-input wire:model="age"   label="Age" placeholder="e.g. 25" type="number" /> 
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

                <x-input wire:model="phone_number"  type="number" label="Phone number" pattern="[0-9]{10}" placeholder="e.g. 09463637401"/>
                <x-select wire:model="commuter_type" label="Commuter type" size="lg">
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

                <x-input wire:model="phone_number"    label="Phone no."                 placeholder="63+ 912 345 6789"  />
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
                        </div>
                    </div>
                    <div class="flex items-center gap-1 shrink-0">
                        <flux:modal.trigger name="edit-vehicle-{{ $index }}">
                            <flux:button size="sm" variant="ghost" icon="pencil" class="text-light-txt-muted dark:text-dark-txt-muted" />
                        </flux:modal.trigger>
                        @if(count($vehicles) > 1)
                            <flux:button wire:click="removeVehicle({{ $index }})" size="sm" variant="ghost" icon="trash" class="text-danger dark:text-dark-danger" />
                        @endif
                    </div>
                </div>

                @php
                    $vehicleErrors = collect($errors->keys())
                        ->filter(fn($key) => str_starts_with($key, "vehicles.$index."))
                        ->map(fn($key) => $errors->first($key));
                @endphp

                @if($vehicleErrors->isNotEmpty())
                    <div wire:key="vehicle-errors-{{ $index }}" class="rounded-lg border border-danger/30 bg-danger/5 dark:bg-dark-danger/10 p-3 space-y-1">
                        @foreach ($vehicleErrors as $message)
                            <p class="font-secondary text-timestamp text-danger dark:text-dark-danger">{{ $message }}</p>
                        @endforeach
                    </div>
                @endif

                <flux:modal name="edit-vehicle-{{ $index }}" class="md:w-[28rem]">
                    <div class="space-y-4">
                        <div>
                            <flux:heading size="lg" class="font-primary text-light-txt-primary dark:text-dark-txt-primary">
                                Edit Vehicle {{ $index + 1 }}
                            </flux:heading>
                            <flux:text class="mt-1 font-secondary text-timestamp text-light-txt-muted dark:text-dark-txt-muted">
                                Update this vehicle's details.
                            </flux:text>
                        </div>

                        <x-inputs-container class="grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <flux:label class="mb-3 font-secondary text-table-row text-light-txt-body dark:text-dark-txt-primary">Vehicle Type</flux:label>
                                <flux:select wire:model.live="vehicles.{{ $index }}.vehicle_type" placeholder="Choose type..." size="sm">
                                    @foreach ($this->getVehicleType as $vehicleType)
                                        <flux:select.option>{{ $vehicleType->vehicle_type }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                                @error("vehicles.$index.vehicle_type")
                                    <p class="font-secondary text-timestamp text-danger dark:text-dark-danger mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <x-input wire:model.live="vehicles.{{ $index }}.plate_number" label="Plate number" placeholder="e.g. ABC-123" size="sm" />
                            </div>

                            <div>
                                <x-input wire:model.live="vehicles.{{ $index }}.seat_capacity" type="number" label="Seat capacity" max="50" min="10"/>
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
                                @error("vehicles.$index.route")
                                    <p class="font-secondary text-timestamp text-danger dark:text-dark-danger mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            @if ($this->vehicles[$index]['vehicle_type'] === 'Bus' || $this->vehicles[$index]['vehicle_type'] === 'UV-express')
                                <div>
                                    <flux:label class="mb-3 font-secondary text-table-row text-light-txt-body dark:text-dark-txt-primary">Group No.</flux:label>
                                    <flux:select wire:model.live="vehicles.{{ $index }}.group_number" placeholder="Select group for this vehicle..." size="sm">
                                        <flux:select.option value="1">1</flux:select.option>
                                        <flux:select.option value="2">2</flux:select.option>
                                    </flux:select>
                                    @error("vehicles.$index.group_number")
                                        <p class="font-secondary text-timestamp text-danger dark:text-dark-danger mt-1">{{ $message }}</p>
                                    @enderror
                                </div>
                            @else
                                <div>
                                    <flux:label class="mb-3 font-secondary text-table-row text-light-txt-body dark:text-dark-txt-primary">Group No.</flux:label>
                                    <flux:select wire:model.live="vehicles.{{ $index }}.group_number" placeholder="Select group for this vehicle..." size="sm" disabled>
                                        <flux:select.option value="1">1</flux:select.option>
                                        <flux:select.option value="2">2</flux:select.option>
                                    </flux:select>
                                    @error("vehicles.$index.group_number")
                                        <p class="font-secondary text-timestamp text-danger dark:text-dark-danger mt-1">{{ $message }}</p>
                                    @enderror
                                </div>
                            @endif
                        </x-inputs-container>

                        <div class="flex justify-end gap-2 pt-2 border-t border-light-bd-default dark:border-dark-bd-default">
                            <flux:modal.close>
                                <flux:button variant="primary" class="font-secondary">Done</flux:button>
                            </flux:modal.close>
                        </div>
                    </div>
                </flux:modal>
            @else
                <div wire:key="vehicle-{{ $index }}-{{ md5(json_encode($vehicle)) }}"
                    class="rounded-lg border border-light-bd-default dark:border-dark-bd-default bg-light-subtle dark:bg-dark-subtle p-4 space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-body">Vehicle {{ $index + 1 }}</span>
                        @if(count($vehicles) > 1)
                            <button wire:click="removeVehicle({{ $index }})" type="button"
                                class="flex items-center gap-1 font-secondary text-timestamp text-danger hover:text-danger dark:text-dark-danger hover:bg-danger/10 dark:hover:bg-dark-danger/10 px-2 py-1 rounded-md transition cursor-pointer">
                                <flux:icon.trash class="w-3.5 h-3.5" /> Remove
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
                            @error("vehicles.$index.vehicle_type")
                                <p class="font-secondary text-timestamp text-danger dark:text-dark-danger mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <x-input wire:model.live="vehicles.{{ $index }}.plate_number" label="Plate number" placeholder="e.g. ABC-123" size="sm" />
                            @error("vehicles.$index.plate_number")
                                <p class="font-secondary text-timestamp text-danger dark:text-dark-danger mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <x-input wire:model.live="vehicles.{{ $index }}.seat_capacity" type="number" label="Seat capacity" max="50" min="10"/>
                            @error("vehicles.$index.seat_capacity")
                                <p class="font-secondary text-timestamp text-danger dark:text-dark-danger mt-1">{{ $message }}</p>
                            @enderror
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
                            @error("vehicles.$index.route")
                                <p class="font-secondary text-timestamp text-danger dark:text-dark-danger mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        @if ($this->vehicles[$index]['vehicle_type'] === 'Bus' || $this->vehicles[$index]['vehicle_type'] === 'UV-express')
                            <div>
                                <flux:label class="mb-3 font-secondary text-table-row text-light-txt-body dark:text-dark-txt-primary">Group No.</flux:label>
                                <flux:select wire:model.live="vehicles.{{ $index }}.group_number" placeholder="Select group for this vehicle..." size="sm">
                                    <flux:select.option value="1">1</flux:select.option>
                                    <flux:select.option value="2">2</flux:select.option>
                                </flux:select>
                                @error("vehicles.$index.group_number")
                                    <p class="font-secondary text-timestamp text-danger dark:text-dark-danger mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        @else
                            <div>
                                <flux:label class="mb-3 font-secondary text-table-row text-light-txt-body dark:text-dark-txt-primary">Group No.</flux:label>
                                <flux:select wire:model.live="vehicles.{{ $index }}.group_number" placeholder="Select group for this vehicle..." size="sm" disabled>
                                    <flux:select.option value="1">1</flux:select.option>
                                    <flux:select.option value="2">2</flux:select.option>
                                </flux:select>
                                @error("vehicles.$index.group_number")
                                    <p class="font-secondary text-timestamp text-danger dark:text-dark-danger mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        @endif
                    </x-inputs-container>
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

            {{-- Input --}}
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
                </flux:field>
            </div>
        </x-card>
    @endif

    @if($step === 5)

        <div class="space-y-4">

            <p class="font-secondary text-timestamp font-medium uppercase tracking-wide text-light-txt-muted dark:text-dark-txt-muted">Review before saving</p>

            <flux:card>
                <div class="flex items-center gap-3 mb-2">

                    <div class="w-12 h-12 rounded-full bg-secondary text-primary dark:text-dark-txt-primary flex items-center justify-center font-secondary text-table-row font-medium">
                        {{ strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1)) }}
                    </div>

                    <div class="flex-1 min-w-0">
                        <x-text variant="strong" class="font-primary text-body text-light-txt-primary dark:text-dark-txt-primary">{{ $first_name }} {{ $last_name }}</x-text>
                        {{-- <x-text>{{ $username }}</x-text> --}}
                    </div>

                    <div>
                        @if ($this->role === 'operator')
                            <flux:badge color="blue" size="sm" class="font-secondary text-badge">Operator</flux:badge>
                        @else
                            <flux:badge color="yellow" size="sm" class="font-secondary text-badge">Commuter</flux:badge>
                        @endif
                    </div>

                </div>

                <x-inputs-container class="border-t border-light-bd-default dark:border-dark-bd-default pt-3 grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        {{-- <x-text class="font-secondary text-stat-label text-light-txt-muted dark:text-dark-txt-muted">Username</x-text>
                        <x-text variant="strong" class="font-secondary text-table-row text-light-txt-primary dark:text-dark-txt-primary">{{ $username }}</x-text> --}}
                    </div>
                    <div>
                        {{-- <x-text class="font-secondary text-stat-label text-light-txt-muted dark:text-dark-txt-muted">Password</x-text>
                        <x-text variant="strong" class="font-secondary text-table-row text-light-txt-primary dark:text-dark-txt-primary">{{ $password }}</x-text> --}}
                    </div>
                    <div>
                        <x-text class="font-secondary text-stat-label text-light-txt-muted dark:text-dark-txt-muted">Home address</x-text>
                        <x-text variant="strong" class="font-secondary text-table-row text-light-txt-primary dark:text-dark-txt-primary">{{ $address }}</x-text>
                    </div>
                    @if ($email_address)
                        <div>
                            <x-text class="font-secondary text-stat-label text-light-txt-muted dark:text-dark-txt-muted">Email address</x-text>
                            <x-text variant="strong" class="font-secondary text-table-row text-light-txt-primary dark:text-dark-txt-primary">{{ $email_address }}</x-text>
                        </div>
                    @endif
                    <div>
                        <x-text class="font-secondary text-stat-label text-light-txt-muted dark:text-dark-txt-muted">Phone no.</x-text>
                        <x-text variant="strong" class="font-secondary text-table-row text-light-txt-primary dark:text-dark-txt-primary">{{ $phone_number }}</x-text>
                    </div>
                    @if($role === 'commuter')
                        <div>
                            <x-text class="font-secondary text-stat-label text-light-txt-muted dark:text-dark-txt-muted">Commuter type</x-text>
                            <x-text variant="strong" class="font-secondary text-table-row text-light-txt-primary dark:text-dark-txt-primary">{{ $commuter_type }}</x-text>
                        </div>
                    @endif
                    <div>
                        <x-text class="font-secondary text-stat-label text-light-txt-muted dark:text-dark-txt-muted">Has card</x-text>
                        <x-text variant="strong" class="font-secondary text-table-row text-light-txt-primary dark:text-dark-txt-primary">{{ $card_number ? 'Yes' : 'No' }}</x-text>
                    </div>

                </x-inputs-container>
            </flux:card>
            @if($role === 'operator')
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <p class="font-secondary text-timestamp font-medium uppercase tracking-wide text-light-txt-muted dark:text-dark-txt-muted">Vehicles</p>
                        <flux:badge size="sm" color="zinc" class="font-secondary text-badge">
                            {{ count($vehicles) }} Vehicle/s
                        </flux:badge>
                    </div>

                    @foreach ($vehicles as $vehicle)
                        <flux:card class="!p-4" wire:key="summary-vehicle-{{ $loop->index }}">
                            <div class="flex items-center justify-between mb-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-lg bg-light-subtle dark:bg-dark-subtle flex items-center justify-center">
                                        <flux:icon
                                            name="{{ $vehicle['vehicle_type'] === 'Bus' ? 'truck' : ($vehicle['vehicle_type'] === 'Uv-express' ? 'truck' : 'truck') }}"
                                            class="w-4 h-4 text-light-txt-muted dark:text-dark-txt-muted"
                                        />
                                    </div>
                                    <div>
                                        <x-text variant="strong" class="font-secondary text-table-row text-light-txt-primary dark:text-dark-txt-primary">{{ $vehicle['plate_number'] }}</x-text>
                                        <x-text class="font-secondary text-timestamp text-light-txt-muted dark:text-dark-txt-muted">{{ $vehicle['vehicle_type'] }}</x-text>
                                    </div>
                                </div>
                                @if(!empty($vehicle['group_number']))
                                    <flux:badge size="sm" color="zinc" class="font-secondary text-badge">Group {{ $vehicle['group_number'] }}</flux:badge>
                                @endif
                            </div>

                            <x-inputs-container class="border-t border-light-bd-default dark:border-dark-bd-default pt-3 grid-cols-1 sm:grid-cols-2 gap-4">

                                <div>
                                    <x-text class="font-secondary text-timestamp text-light-txt-muted dark:text-dark-txt-muted">Route</x-text>
                                    <x-text variant="strong" class="font-secondary text-table-row text-light-txt-primary dark:text-dark-txt-primary">{{ $vehicle['route'] }}</x-text>
                                </div>
                                <div>
                                    <x-text class="font-secondary text-timestamp text-light-txt-muted dark:text-dark-txt-muted">Seat Capacity</x-text>
                                    <x-text variant="strong" class="font-secondary text-table-row text-light-txt-primary dark:text-dark-txt-primary">{{ $vehicle['seat_capacity'] }}</x-text>
                                </div>
                            </x-inputs-container>
                        </flux:card>
                    @endforeach
                </div>
            @endif
            <x-text class="font-secondary text-timestamp text-light-txt-muted dark:text-dark-txt-muted leading-relaxed">
                A welcome message with login instructions will be sent to the user. You can edit their profile anytime from the Users page.
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

    <flux:modal name="address-modal" class="md:w-[26rem]" x-on:address-saved.window="$flux.modal('address-modal').close()">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg" class="!font-primary !font-bold text-light-txt-primary dark:text-dark-txt-primary">
                    Set your address
                </flux:heading>
                <flux:text class="mt-1 font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">
                    All addresses are within Iriga City, Camarines Sur.
                </flux:text>
            </div>

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
                    wire:model="zone_number"
                    min="1"
                    max="20"
                    placeholder="e.g. 3"
                    class="font-secondary text-table-row bg-light-primary dark:bg-dark-surface text-light-txt-body dark:text-dark-txt-primary border-light-bd-default dark:border-dark-bd-default placeholder:text-light-txt-muted dark:placeholder:text-dark-txt-muted"
                />
                <flux:error name="zone_number" />
            </flux:field>

            <flux:field>
                <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">Barangay</flux:label>
                <flux:select
                    wire:model="barangay"
                    placeholder="Select barangay"
                    class="font-secondary text-table-row bg-light-primary dark:bg-dark-surface text-light-txt-body dark:text-dark-txt-primary border-light-bd-default dark:border-dark-bd-default"
                >
                    @foreach ($this->getBarangays as $brgy)
                        <flux:select.option value="{{ $brgy }}">{{ $brgy }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="barangay" />
            </flux:field>

            <div class="flex flex-col sm:flex-row justify-end gap-2 pt-2 border-t border-light-bd-default dark:border-dark-bd-default">
                <flux:modal.close>
                    <flux:button type="button" variant="ghost" class="font-secondary w-full sm:w-auto">
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
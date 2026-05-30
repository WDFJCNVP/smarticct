<?php

use Livewire\Attributes\Validate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use App\Events\RegistrationTapCardEvent;

use Livewire\Component;
use App\Models\User;
use App\Models\Card;
use App\Models\Vehicle;
use App\Models\Terminal;
use App\Models\Route;

new class extends Component
{
    public int $step = 1;

    // Basic info
    public string $role = '';
    public string $first_name = '';
    public string $last_name = '';
    public string $email = '';
    public string $mobile = '';
    public string $password = '';

    //Passenger details
    public string $date_of_birth = '';
    public string $passenger_type = 'Regular';
    public string $home_address = '';
    public string $card_number = '';

    // Operator details
    public string $employee_id = '';
    public string $license_number = '';
    public string $assigned_route = '';
    public string $vehicle_plate = '';
    public string $operator_type = 'Driver';

    public array $validated_attributes = [];

    public array $vehicles = [
        ['vehicle_type' => '', 'plate_number' => '', 'route' => '']
    ];

    #[On('echo:registration-tap-card,.RegistrationTapCardEvent')]
    public function getUid($event)
    {
        $this->card_number = $event['uid'];
    }

    public function selectRole(string $role): void
    {
        $this->role = $role;
    }

    #[Computed]
    public function getRoute() {
        $route = Terminal::all();

        return $route;
    }

    // public function mount() {
    //     dd($this->getRoute());
    // }

    public function next(): void
    {
        if ($this->step === 1) {
            $validated = $this->validate(['role' => 'required|in:passenger,operator']);

            $this->validated_attributes[] = $validated;
        }

        if ($this->step === 2) {
            $this->validated_attributes[] = $this->validate([
                'first_name' => 'required|min:2',
                'last_name'  => 'required|min:2',
                'email'      => 'required|email|unique:users,email',
                'mobile'     => 'required|min:10',
                'password'   => 'required|min:8',
            ]);
        }

        if ($this->step === 3) {
            if ($this->role === 'passenger') {
                $this->validated_attributes[] = $this->validate([
                    'date_of_birth' => 'required|date',
                    'home_address'  => 'required|min:5',
                    'card_number'   => 'required|unique:cards,uid',
                ]);
            } else {
                $this->validated_attributes[] = $this->validate([
                    'employee_id'                    => 'required',
                    'license_number'                 => 'required',
                    'vehicles'                       => 'required|array|min:1',
                    'vehicles.*.vehicle_type'        => 'required',
                    'vehicles.*.plate_number'        => 'required',
                    'vehicles.*.route'               => 'required',
                ]);
            }
        }

        if ($this->step < 4) {
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
        $this->vehicles[] = ['vehicle_type' => '', 'plate_number' => '', 'route' => ''];
    }

    public function removeVehicle(int $index): void
    {
        if (count($this->vehicles) > 1) {
            array_splice($this->vehicles, $index, 1);
        }
    }

    public function register(): void
    {

    
        DB::transaction(function () {

             $name = $this->first_name . ' ' . $this->last_name;

            $user = User::create([
                'name'     => $name,
                'email'    => $this->email,
                'address'  => $this->home_address,
                'role'     => $this->role,
                'password' => bcrypt($this->password)
            ]);

            $new_user_id = $user->id;

            Card::create([
                'user_id' => $new_user_id,
                'uid'     => '12345678'
            ]);

            foreach ($this->vehicles as $vehicle) {

                $terminal_id = intval($vehicle['route']);

            $vehicle = Vehicle::create([
                'user_id'       => $new_user_id,
                'vehicle_type'  => $vehicle['vehicle_type'],
                'plate_number'  => $vehicle['plate_number'],
                'total_seats'  => 10,
            ]);

            Route::create([
                'vehicle_id' => $vehicle->id,
                'terminal_id' => $terminal_id,
                'first_trip' => '8:00 am',
                'last_trip' => '9:00 am',
                'base_fare' => 10.2,
            ]);
            }
        });

        $this->dispatch('user-registered');
        $this->reset();
        $this->step = 1;
    }

    public function isStepDone(int $s): bool
    {
        return $this->step > $s;
    }
};
?>

<div x-data>

    <flux:breadcrumbs>
        <flux:breadcrumbs.item href="{{ route('admin.users') }}" wire:navigate>Users</flux:breadcrumbs.item>
        <flux:breadcrumbs.item >Registration</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <flux:heading size="lg" class="my-5">{{ $this->role ? 'Registration for ' . ucfirst($this->role): 'Register New User' }}</flux:heading>

    <div class="flex items-center gap-1 mb-6 text-xs">
        @foreach([1 => 'Select role', 2 => 'Account info', 3 => 'Details', 4 => 'Confirm'] as $s => $label)
            <div class="flex items-center gap-1 {{ $loop->last ? '' : 'flex-1' }}">
                <div class="flex items-center gap-1.5">

                    <div @class([
                        'w-8 h-8 rounded-full flex items-center justify-center text-xs font-medium shrink-0 border transition-colors',
                        'bg-accent text-accent-foreground border-accent'                         => $step === $s,
                        'bg-accent/20 text-accent border-accent/40'                              => $step > $s,
                        'bg-zinc-100 dark:bg-zinc-800 text-zinc-400 dark:text-zinc-500 border-zinc-200 dark:border-zinc-700' => $step < $s,
                    ])>
                        @if($step > $s)
                            <flux:icon.check class="w-3 h-3" />
                        @else
                            {{ $s }}
                        @endif
                    </div>

                    <span @class([
                        'hidden sm:inline whitespace-nowrap transition-colors',
                        'text-zinc-900 dark:text-zinc-100 font-medium' => $step === $s,
                        'text-zinc-400 dark:text-zinc-500'             => $step !== $s,
                    ])>{{ $label }}</span>
                </div>

                @if(!$loop->last)
                    <div @class([
                        'flex-1 h-px mx-1 transition-colors',
                        'bg-accent'                               => $step > $s,
                        'bg-zinc-200 dark:bg-zinc-700'            => $step <= $s,
                    ])></div>
                @endif
            </div>
        @endforeach
    </div>

    @if($step === 1)
        <div>
            <flux:radio.group wire:model="role" variant="cards" class="grid grid-cols-2 w-full">
                <flux:radio value="passenger" label="Passenger" description="A commuter who rides and tracks transit routes.">
                    <x-slot name="icon">
                        <flux:icon.user class="w-5 h-5" />
                    </x-slot>
                </flux:radio>
                <flux:radio value="operator" label="Operator" description="A driver or staff assigned to a route or vehicle.">
                    <x-slot name="icon">
                        <flux:icon.identification class="w-5 h-5" />
                    </x-slot>
                </flux:radio>
            </flux:radio.group>
        </div>

        @error('role')
            <p class="text-red-500 text-xs mt-2">{{ $message }}</p>
        @enderror
    @endif

    @if($step === 2)
        <div class="space-y-4">
            <div class="grid grid-cols-2 gap-3">
                <flux:input
                    wire:model="first_name"
                    label="First name"
                    placeholder="e.g. Juan"
                    size="sm"
                />
                <flux:input
                    wire:model="last_name"
                    label="Last name"
                    placeholder="e.g. dela Cruz"
                    size="sm"
                />
                <flux:input
                    wire:model="mobile"
                    label="Mobile number"
                    placeholder="+63 9XX XXX XXXX"
                    size="sm"
                />
            </div>
            <flux:input
                wire:model="email"
                type="email"
                label="Email address"
                placeholder="user@example.com"
                size="sm"
            />
            <flux:input
                wire:model="password"
                type="password"
                label="Temporary password"
                placeholder="Minimum 8 characters"
                size="sm"
                description="The user will be prompted to change this on first login."
            />
        </div>
    @endif

    @if($step === 3 && $role === 'passenger')
        <div class="space-y-4">
            {{-- <flux:callout icon="information-circle" color="blue">
                <flux:callout.text>Passengers get a travel card and can load balance, and view travel history.</flux:callout.text>
            </flux:callout> --}}

            <div class="grid grid-cols-2 gap-3">
                <flux:input
                    wire:model="date_of_birth"
                    type="date"
                    label="Date of birth"
                    size="sm"
                />
                <flux:select
                    wire:model="passenger_type"
                    label="Passenger type"
                    size="sm"
                >
                    <option>Regular</option>
                    <option>Senior Citizen</option>
                    <option>PWD</option>
                    <option>Student</option>
                </flux:select>
            </div>
            <flux:input
                wire:model="home_address"
                label="Home address"
                placeholder="Street, barangay, city"
                size="sm"
            />
            <flux:input
                wire:model="card_number"
                label="Card number"
                placeholder="e.g. SCCT-0001-2025"
                size="sm"
                description="Tap the card to the RFID card reader."
                disabled
            />
        </div>
    @endif

    @if($step === 3 && $role === 'operator')
        <div class="space-y-4">

            <div class="grid grid-cols-2 gap-3">
                <flux:input
                    wire:model="employee_id"
                    label="Franchise No."
                    placeholder="e.g. OPR-2025-001"
                    size="sm"
                />
                <flux:input
                    wire:model="license_number"
                    label="License number"
                    placeholder="e.g. N01-12-345678"
                    size="sm"
                />
            </div>

            <div class="flex items-center justify-between pt-2 pb-1 border-t border-zinc-200 dark:border-zinc-700">
                <div>
                    <p class="text-sm font-semibold text-zinc-800 dark:text-zinc-100">Vehicle Registration</p>
                    <p class="text-xs text-zinc-400 dark:text-zinc-500">{{ count($vehicles) }} vehicle{{ count($vehicles) !== 1 ? 's' : '' }} added</p>
                </div>
                <flux:button wire:click="addVehicle" size="sm" icon="plus" variant="primary">
                    Add Vehicle
                </flux:button>
            </div>

            @forelse ($vehicles as $index => $vehicle)
                <div
                    wire:key="vehicle-{{ $index }}"
                    class="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50 p-4 space-y-3"
                >
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">
                                Vehicle {{ $index + 1 }}
                            </span>
                        </div>

                        @if(count($vehicles) > 1)
                            <button
                                wire:click="removeVehicle({{ $index }})"
                                type="button"
                                class="flex items-center gap-1 text-xs text-red-500 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 px-2 py-1 rounded-md transition cursor-pointer"
                            >
                                <flux:icon.trash class="w-3.5 h-3.5" />
                                Remove
                            </button>
                        @endif
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <flux:label class="mb-3">Vehicle Type</flux:label>
                            <flux:select
                                wire:model="vehicles.{{ $index }}.vehicle_type"
                                placeholder="Choose type..."
                                size="sm"
                            >
                                <flux:select.option>Bus</flux:select.option>
                                <flux:select.option>Multi-cab</flux:select.option>
                                <flux:select.option>Van</flux:select.option>
                                <flux:select.option>Jeep</flux:select.option>
                            </flux:select>
                        </div>
                        <flux:input
                            wire:model="vehicles.{{ $index }}.plate_number"
                            label="Plate number"
                            placeholder="e.g. ABC-123"
                            size="sm"
                        />
                    </div>

                    <div>
                        <flux:label>Route</flux:label>
                        <flux:select
                            wire:model="vehicles.{{ $index }}.route"
                            placeholder="Select route for this vehicle..."
                            size="sm"
                        >
                         @foreach ($this->getRoute() as $route)
                            <flux:select.option value="{{$route->id}}">Iriga Terminal to {{$route->municipality}}</flux:select.option>
                          @endforeach
                        </flux:select>
                    </div>
                </div>
            @empty
                <div class="rounded-lg border border-dashed border-zinc-300 dark:border-zinc-600 p-6 text-center">
                    <flux:icon.truck class="w-8 h-8 mx-auto text-zinc-300 dark:text-zinc-600 mb-2" />
                    <p class="text-sm text-zinc-400">No vehicles added yet.</p>
                    <p class="text-xs text-zinc-400 mt-1">Click "Add Vehicle" to register one.</p>
                </div>
            @endforelse

        </div>
    @endif

    @if($step === 4)
        <div class="space-y-4">
            <p class="text-xs font-medium uppercase tracking-wide text-zinc-400">Review before saving</p>

            <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50 p-4 space-y-3">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-full bg-primary/10 text-primary flex items-center justify-center text-sm font-medium">
                        {{ strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1)) }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-medium text-sm text-zinc-900 dark:text-zinc-100 truncate">{{ $first_name }} {{ $last_name }}</p>
                        <p class="text-xs text-zinc-500 truncate">{{ $email }}</p>
                    </div>
                    <span @class([
                        'text-xs font-medium px-2 py-0.5 rounded-full',
                        'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300'   => $role === 'passenger',
                        'bg-amber-100 text-amber-700 dark:bg-amber-900 dark:text-amber-300' => $role === 'operator',
                    ])>
                        {{ ucfirst($role) }}
                    </span>
                </div>

                <div class="border-t border-zinc-200 dark:border-zinc-700 pt-3 grid grid-cols-2 gap-y-2 text-sm">
                    <div>
                        <p class="text-xs text-zinc-400">Mobile</p>
                        <p class="font-medium text-zinc-800 dark:text-zinc-200 text-xs">{{ $mobile }}</p>
                    </div>
                    @if($role === 'passenger')
                        <div>
                            <p class="text-xs text-zinc-400">Passenger type</p>
                            <p class="font-medium text-zinc-800 dark:text-zinc-200 text-xs">{{ $passenger_type }}</p>
                        </div>
                        @if($card_number)
                            <div>
                                <p class="text-xs text-zinc-400">Card no.</p>
                                <p class="font-medium text-zinc-800 dark:text-zinc-200 text-xs font-mono">{{ $card_number }}</p>
                            </div>
                        @endif
                    @else
                        <div>
                            <p class="text-xs text-zinc-400">Operator type</p>
                            <p class="font-medium text-zinc-800 dark:text-zinc-200 text-xs">{{ $operator_type }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-zinc-400">Employee ID</p>
                            <p class="font-medium text-zinc-800 dark:text-zinc-200 text-xs">{{ $employee_id }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-zinc-400">Assigned route</p>
                            <p class="font-medium text-zinc-800 dark:text-zinc-200 text-xs">{{ $assigned_route }}</p>
                        </div>
                    @endif
                </div>
            </div>

            <p class="text-xs text-zinc-400 leading-relaxed">
                A welcome email with login instructions will be sent to the user. You can edit their profile anytime from the Users page.
            </p>
        </div>
    @endif
        <div class="flex items-center gap-2 pt-2">
    <flux:spacer />

    @if($step > 1)
        <flux:button size="sm" variant="ghost" wire:click="back">
            Back
        </flux:button>
    @endif

    @if($step < 4)
        <flux:button size="sm" variant="primary" wire:click="next">
            Continue
        </flux:button>
    @else
        <flux:button size="sm" variant="primary" wire:click="register">
            Register this {{ ucfirst($this->role) }}
        </flux:button>
    @endif
</div>
</div>
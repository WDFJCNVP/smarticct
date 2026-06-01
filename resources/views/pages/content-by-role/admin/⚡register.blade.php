<?php

use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use App\Events\RegistrationTapCardEvent;
use Livewire\Component;
use App\Models\User;
use App\Models\Card;
use App\Models\Vehicle;
use App\Models\Terminal;
use App\Models\Route;
use Illuminate\Support\Facades\DB;

new class extends Component
{
    public int $step = 1;

    // Basic info
    public string $role = '';
    public string $first_name = '';
    public string $last_name = '';
    public string $username = '';
    public string $phone_number = '';
    public string $password = '';

    // Passenger details
    public string $date_of_birth = '';
    public string $passenger_type = 'Regular';
    public string $address = '';
    public string $card_number = '';
    public string $new_card_id = '';

    // Card scan state
    public bool $card_focused = true;
    public string $card_state = 'warn'; // ready | success | warn

    // Operator details
    public string $employee_id = '';
    public string $license_number = '';
    public string $assigned_route = '';
    public string $vehicle_plate = '';
    public string $operator_type = 'Driver';

    public array $vehicles = [
        ['vehicle_type' => '', 'plate_number' => '', 'route' => '']
    ];

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
        return Terminal::all();
    }

    public function next(): void
    {
        if ($this->step === 1) {
            $this->validate(['role' => 'required|in:passenger,operator']);
        }

        if ($this->step === 2) {
            $this->validate([
                'first_name' => 'required|min:2',
                'last_name'  => 'required|min:2',
                'username'   => 'required|unique:users,username',
                'password'   => 'required|min:8',
            ]);
        }

        if ($this->step === 3) {
            if ($this->role === 'passenger') {
                $this->validate([
                    'date_of_birth' => 'required|date',
                    'address'       => 'required|min:5',
                    'phone_number'  => 'required|numeric',
                ]);
            } else {
                $this->validate([
                    'employee_id'             => 'required',
                    'license_number'          => 'required',
                    'vehicles'                => 'required|array|min:1',
                    'vehicles.*.vehicle_type' => 'required',
                    'vehicles.*.plate_number' => 'required',
                    'vehicles.*.route'        => 'required',
                ]);
            }
        }

        if ($this->step === 4) {
            $this->validate([
                'card_number' => 'required|unique:cards,uid',
            ]);
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
            $user = User::create([
                'name'         => $this->first_name . ' ' . $this->last_name,
                'username'     => $this->username,
                'phone_number' => $this->phone_number,
                'address'      => $this->address,
                'role'         => $this->role,
                'password'     => bcrypt($this->password),
            ]);

            $user->card()->create([
                'uid'    => $this->card_number,
                'status' => 'active',
            ]);

            if ($this->role === 'operator') {
                foreach ($this->vehicles as $vehicle) {
                    $v = Vehicle::create([
                        'user_id'      => $user->id,
                        'vehicle_type' => $vehicle['vehicle_type'],
                        'plate_number' => $vehicle['plate_number'],
                        'total_seats'  => 10,
                    ]);

                    Route::create([
                        'vehicle_id'  => $v->id,
                        'terminal_id' => intval($vehicle['route']),
                        'first_trip'  => '8:00 am',
                        'last_trip'   => '9:00 am',
                        'base_fare'   => 10.2,
                    ]);
                }
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

<div>
    <flux:breadcrumbs>
        <flux:breadcrumbs.item href="{{ route('admin.users') }}" wire:navigate>Users</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>Registration</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <flux:heading size="lg" class="my-8">
        {{ $this->role ? 'Registration for ' . ucfirst($this->role) : 'Register New User' }}
    </flux:heading>

    {{-- Step indicator --}}
    <div class="flex items-center gap-1 mb-6 text-xs">
        @foreach([1 => 'Select role', 2 => 'Account info', 3 => 'Details', 4 => 'Card', 5 => 'Confirm'] as $s => $label)
            <div class="flex items-center gap-1 {{ $loop->last ? '' : 'flex-1' }}">
                <div class="flex items-center gap-1.5">
                    <div @class([
                        'w-12 h-12 rounded-full flex items-center justify-center text-xs font-medium shrink-0 border transition-colors',
                        'bg-accent text-accent-foreground border-accent'                                                                  => $step === $s,
                        'bg-green-50 text-green-700 border-green-200 dark:bg-green-950/30 dark:text-green-400 dark:border-green-900/50'                                                                       => $step > $s,
                        'bg-zinc-100 dark:bg-zinc-800 text-zinc-400 dark:text-zinc-500 border-zinc-200 dark:border-zinc-700'              => $step < $s,
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
                        'bg-accent'                    => $step > $s,
                        'bg-zinc-200 dark:bg-zinc-700' => $step <= $s,
                    ])></div>
                @endif
            </div>
        @endforeach
    </div>

    {{-- Step 1: Select Role --}}
    @if($step === 1)
        <flux:radio.group wire:model="role" variant="cards" class="grid grid-cols-2 w-full">
            <flux:radio value="passenger" label="Passenger" description="A commuter who rides and tracks transit routes.">
                <x-slot name="icon"><flux:icon.user class="w-5 h-5" /></x-slot>
            </flux:radio>
            <flux:radio value="operator" label="Operator" description="A driver or staff assigned to a route or vehicle.">
                <x-slot name="icon"><flux:icon.identification class="w-5 h-5" /></x-slot>
            </flux:radio>
        </flux:radio.group>
        @error('role')
            <p class="text-red-500 text-xs mt-2">{{ $message }}</p>
        @enderror
    @endif

    {{-- Step 2: Account Info --}}
    @if($step === 2)
        <div class="space-y-4">
            <flux:callout variant="warning" icon="exclamation-circle"
                heading="The user will be prompted to change temporary password on first login." />
            <x-inputs-container>
                <x-input wire:model="first_name" label="First name" placeholder="e.g. Juan" />
                <x-input wire:model="last_name"  label="Last name"  placeholder="e.g. dela Cruz" />
                <x-input wire:model="username"   label="Username"   placeholder="e.g. juandelacruz" />
                <x-input wire:model="password"   label="Temporary password" type="password" />
            </x-inputs-container>
        </div>
    @endif

    {{-- Step 3: Passenger Details --}}
    @if($step === 3 && $role === 'passenger')
        <div class="space-y-4">
            <x-inputs-container>
                <x-input wire:model="date_of_birth" type="date"   label="Date of birth" />
                <x-input wire:model="address"       type="text"   label="Address" />
                <x-input wire:model="phone_number"  type="number" label="Phone number" />
                <x-select wire:model="passenger_type" label="Passenger type" size="lg">
                    <x-select-option>Regular</x-select-option>
                    <x-select-option>Senior Citizen</x-select-option>
                    <x-select-option>PWD</x-select-option>
                    <x-select-option>Student</x-select-option>
                </x-select>
            </x-inputs-container>
        </div>
    @endif

    {{-- Step 3: Operator Details --}}
    @if($step === 3 && $role === 'operator')
        <div class="space-y-4">
            <x-inputs-container>
                <x-input wire:model="employee_id"   label="Franchise No."   placeholder="e.g. OPR-2025-001"  />
                <x-input wire:model="license_number" label="License number" placeholder="e.g. N01-12-345678"  />
                <x-input wire:model="address"       label="Address"         placeholder="e.g. Zone 3 Ayugan Ocampo Camsur" />
                <x-input wire:model="phone_number"   label="Phone no."      placeholder="63+ 000 000 0000"  />
            </x-inputs-container>

            <div class="flex items-center justify-between pt-2 pb-1 border-t border-zinc-200 dark:border-zinc-700">
                <div>
                    <p class="text-sm font-semibold text-zinc-800 dark:text-zinc-100">Vehicle Registration</p>
                    <p class="text-xs text-zinc-400 dark:text-zinc-500">
                        {{ count($vehicles) }} vehicle{{ count($vehicles) !== 1 ? 's' : '' }} added
                    </p>
                </div>
                <flux:button wire:click="addVehicle" size="sm" icon="plus" variant="primary">Add Vehicle</flux:button>
            </div>

            @forelse ($vehicles as $index => $vehicle)
                <div wire:key="vehicle-{{ $index }}"
                     class="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50 p-4 space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Vehicle {{ $index + 1 }}</span>
                        @if(count($vehicles) > 1)
                            <button wire:click="removeVehicle({{ $index }})" type="button"
                                class="flex items-center gap-1 text-xs text-red-500 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 px-2 py-1 rounded-md transition cursor-pointer">
                                <flux:icon.trash class="w-3.5 h-3.5" /> Remove
                            </button>
                        @endif
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <flux:label class="mb-3">Vehicle Type</flux:label>
                            <flux:select wire:model="vehicles.{{ $index }}.vehicle_type" placeholder="Choose type..." size="sm">
                                <flux:select.option>Bus</flux:select.option>
                                <flux:select.option>Multi-cab</flux:select.option>
                                <flux:select.option>Van</flux:select.option>
                                <flux:select.option>Jeep</flux:select.option>
                            </flux:select>
                        </div>
                        <flux:input wire:model="vehicles.{{ $index }}.plate_number" label="Plate number" placeholder="e.g. ABC-123" size="sm" />
                    </div>
                    <div>
                        <flux:label>Route</flux:label>
                        <flux:select wire:model="vehicles.{{ $index }}.route" placeholder="Select route for this vehicle..." size="sm">
                            @foreach ($this->getRoute() as $route)
                                <flux:select.option value="{{ $route->id }}">Iriga Terminal to {{ $route->municipality }}</flux:select.option>
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

    {{-- Step 4: RFID Card Scan --}}
    @if($step === 4)
        <x-card>

            <div @class([
                'flex items-center gap-3 p-4 border-b border-zinc-200 dark:border-zinc-700',
                'bg-blue-50 dark:bg-blue-950'   => $card_state === 'ready',
                'bg-green-50 dark:bg-green-950' => $card_state === 'success',
                'bg-red-50 dark:bg-red-950'     => $card_state === 'warn',
            ])>
                <div @class([
                    'w-10 h-10 rounded-full flex items-center justify-center shrink-0',
                    'bg-blue-200 dark:bg-blue-800'   => $card_state === 'ready',
                    'bg-green-200 dark:bg-green-800' => $card_state === 'success',
                    'bg-red-200 dark:bg-red-800'     => $card_state === 'warn',
                ])>
                    @if($card_state === 'ready')
                        <flux:icon name="credit-card" class="w-5 h-5 text-blue-800 dark:text-blue-200" />
                    @elseif($card_state === 'success')
                        <flux:icon name="check-circle" class="w-5 h-5 text-green-800 dark:text-green-200" />
                    @else
                        <flux:icon name="exclamation-triangle" class="w-5 h-5 text-red-800 dark:text-red-200" />
                    @endif
                </div>

                <div class="flex-1 min-w-0">
                    <p @class([
                        'text-sm font-medium',
                        'text-blue-900 dark:text-blue-100'   => $card_state === 'ready',
                        'text-green-900 dark:text-green-100' => $card_state === 'success',
                        'text-red-900 dark:text-red-100'     => $card_state === 'warn',
                    ])>
                        @if($card_state === 'ready')   Get your RFID card ready
                        @elseif($card_state === 'success') Card scanned successfully!
                        @else Input field lost focus
                        @endif
                    </p>
                    <p @class([
                        'text-xs',
                        'text-blue-600 dark:text-blue-300'   => $card_state === 'ready',
                        'text-green-600 dark:text-green-300' => $card_state === 'success',
                        'text-red-600 dark:text-red-300'     => $card_state === 'warn',
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

                {{-- Clear button on success --}}
                @if($card_state === 'success')
                    <button wire:click="clearCard"
                        class="text-zinc-400 hover:text-zinc-600 transition"
                        aria-label="Clear card">
                        <flux:icon name="x-mark" class="w-6 h-6" />
                    </button>
                @endif
            </div>

            {{-- Input --}}
            <div class="p-4">
                <flux:field>
                    <flux:label class="flex items-center gap-1.5 text-xs">
                        <flux:icon name="credit-card" class="w-3.5 h-3.5" />
                        Card UID
                    </flux:label>
                    <flux:input
                        id="rfid-input"
                        wire:model="card_number"
                        wire:keydown.enter="cardScanned"
                        wire:focus="cardFocused"
                        wire:blur="cardBlurred"
                        placeholder="Tap your card on the reader..."
                        autocomplete="off"
                        class="font-mono tracking-widest"
                        autofocus
                    />
                    hello
                    @error('card_number')
                        <flux:error>{{ $message }}</flux:error>
                    @enderror
                </flux:field>
            </div>
        </x-card>
    @endif

    {{-- Step 5: Confirm --}}
    @if($step === 5)

        <div class="space-y-4">

            <p class="text-xs font-medium uppercase tracking-wide text-zinc-400">Review before saving</p>

            <x-card>
                <div class="flex items-center gap-3">

                    <div class="w-12 h-12 rounded-full bg-primary/10 text-primary flex items-center justify-center text-sm font-medium">
                        {{ strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1)) }}JC
                    </div>

                    <div class="flex-1 min-w-0">
                        <x-text variant="strong" class="text-base">{{ $first_name }} {{ $last_name }}</x-text>
                        <x-text>{{ $username }}</x-text>
                    </div>

                    <span @class([
                        'text-xs font-medium px-2 py-0.5 rounded-full',
                        'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300'     => $role === 'passenger',
                        'bg-amber-100 text-amber-700 dark:bg-amber-900 dark:text-amber-300' => $role === 'operator',
                    ])>{{ ucfirst($role) }}</span>

                </div>

                <x-inputs-container class="border-t border-zinc-200 dark:border-zinc-700 pt-3 grid-cols-3">
                    <div>
                        <x-text class="text-xs">Home Address</x-text>
                        <x-text variant="strong" >{{ $address }}</x-text>
                    </div>
                    <div>
                        <x-text class="text-xs">Phone no.</x-text>
                        <x-text variant="strong" >{{ $phone_number }}</x-text>
                    </div>
                    @if($role === 'passenger')
                        <div>
                            <x-text class="text-xs">Passenger type</x-text>
                            <x-text variant="strong" >{{ $passenger_type }}</x-text>
                        </div>
                        @if($card_number)
                            <div>
                                <x-text class="text-xs">Card UID</x-text>
                                <x-text variant="strong" >{{ $card_number }}</x-text>
                            </div>
                        @endif
                    @else
                        <div>
                            <flux:text class="text-xs">Operator's data</flux:text>
                        </div>
                    @endif
                </x-inputs-container>
            </x-card>
            <x-text class="text-xs text-zinc-400 leading-relaxed">
                A welcome message with login instructions will be sent to the user. You can edit their profile anytime from the Users page.
            </x-text>
        </div>
    @endif

    {{-- Navigation --}}
    <div class="flex items-center gap-2 pt-6">
        <flux:spacer />
        @if($step > 1)
            <flux:button size="sm" variant="ghost" wire:click="back">Back</flux:button>
        @endif
        @if($step < 5)
            <flux:button size="sm" variant="primary" wire:click="next">Continue</flux:button>
        @else
            <flux:button size="sm" variant="primary" wire:click="register">
                Register this {{ ucfirst($this->role) }}
            </flux:button>
        @endif
    </div>

    {{-- Focus helper: dispatched by refocus() method --}}
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
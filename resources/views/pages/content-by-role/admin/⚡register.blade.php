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
    public string $username = '';
    public string $email_address = '';
    public string $phone_number;
    public string $password = '';

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
        if (in_array($property, ['first_name', 'last_name'])) {

            if (!empty($this->first_name) && !empty($this->last_name)) {

                $prefix = match ($this->role) {
                    'commuter' => '11284711',
                    'operator' => '11284712',
                    'cashier'  => '11284713',
                    'admin'    => '11284714',
                    default    => '11284710',
                };

                $sequence = str_pad(
                    random_int(1, 9999),
                    4,
                    '0',
                    STR_PAD_LEFT
                );

                $baseUsername = $prefix . $sequence;

                $this->username = $this->ensureUniqueUsername($baseUsername);

                if (empty($this->password)) {
                    $this->password = str_pad(
                        random_int(0, 99999999),
                        8,
                        '0',
                        STR_PAD_LEFT
                    );
                }
            }
        }
    }


    protected function ensureUniqueUsername(string $username): string
    {
        $original = $username;
        $counter = 1;

        while (User::where('username', $username)->exists()) {
            $username = $original . $counter;
            $counter++;
        }

        return $username;
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

    public function next(): void
    {
        if ($this->step === 1) {
            $this->validate(['role' => 'required|in:commuter,operator']);
        }

        if ($this->step === 2) {
            $this->validate([
                'first_name'    => 'required|min:2',
                'last_name'     => 'required|min:2',
                'username'      => 'required|unique:users,username',
                'password'      => 'required|min:8',
                'email_address' => 'nullable|email|unique:users,email_address',
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

    public function register(): void
    {

        $userBasicInformation = [
            'name'         => $this->first_name . ' ' . $this->last_name,
            'age'          => $this->age,
            'commuter_type'=> $this->commuter_type,
            'username'     => $this->username,
            'phone_number' => $this->phone_number,
            'address'      => $this->address,
            'email_address' => $this->email_address,
            'role'         => $this->role,
            'password'     => $this->password, 
        ];

        if($this->card_number) {
            $cardInformation = [
                'uid'    => $this->card_number,
                'status' => 'active',
            ];
        } else {
            $cardInformation = [];
        }



        app(UserService::class)->create(
            $userBasicInformation,
            $cardInformation,
            $this->vehicles,
        );

        Flux::toast(
            variant: 'success',
            heading: 'User Registered',
            text: 'User has been successfully registered.'
        );

        $this->dispatch('user-registered');
        $this->reset();
        $this->step = 1;
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

    <flux:heading size="lg" class="my-8">
        {{ $this->role ? 'Registration for ' . ucfirst($this->role) : 'Register New User' }}
    </flux:heading>

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

    @if($step === 1)
        <flux:radio.group wire:model="role" variant="cards" class="grid grid-cols-2 w-full">
            <flux:radio value="commuter" label="Commuter" description="A commuter who rides and tracks transit routes.">
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

    @if($step === 2)
        <div class="space-y-4">
            <flux:callout variant="info" icon="information-circle"
                heading="Credentials Auto-Generated" 
                description="Username and temporary numeric PIN are filled automatically based on names." />
                
            <x-inputs-container>
                {{-- We add .live.debounce to sync changes gracefully as the admin types --}}
                <x-input wire:model.live.debounce.500ms="first_name" label="First name" placeholder="e.g. Juan" />
                <x-input wire:model.live.debounce.500ms="last_name"  label="Last name"  placeholder="e.g. dela Cruz" />
                <x-input wire:model="email_address"   label="Email address (optional)" placeholder="juandelacruz@gmail.com" />
                <x-input wire:model="age"   label="Age" placeholder="e.g. 25" type="number" />
                <x-input wire:model="username" label="Username" placeholder="e.g. juandelacruz" />
                
                {{-- Changed type to "text" so the admin can see and copy the temporary PIN --}}
                <x-input wire:model="password" label="Temporary numeric password" type="text" class="font-mono" />
            </x-inputs-container>
        </div>
    @endif

    @if($step === 3 && $role === 'commuter')
        <div class="space-y-4">
            <x-inputs-container>
                <x-input wire:model="address"       type="text"   label="Address" placeholder="e.g. Zone 3 San Miguel Nabua Camarines Sur"/>
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
                <x-input wire:model="address"         label="Home address"              placeholder="e.g. Zone 3 San Miguel Nabua Camarines Sur" />
                <x-input wire:model="phone_number"    label="Phone no."                 placeholder="63+ 912 345 6789"  />
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
                    <x-inputs-container>
                        <div>
                            <flux:label class="mb-3">Vehicle Type</flux:label>
                            <flux:select wire:model.live="vehicles.{{ $index }}.vehicle_type" placeholder="Choose type..." size="sm">
                                @foreach ($this->getVehicleType as $vehicle)
                                     <flux:select.option>{{ $vehicle->vehicle_type }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        </div>

                        <x-input wire:model="vehicles.{{ $index }}.plate_number" label="Plate number" placeholder="e.g. ABC-123" size="sm" />

                        <x-input wire:model="vehicles.{{ $index }}.seat_capacity" type="number" label="Seat capacity" max="50" min="10"/>

                        <div>
                            <flux:label>Route</flux:label>
                            <flux:select wire:model="vehicles.{{ $index }}.route" placeholder="Select route for this vehicle..." size="sm">
                                @foreach ($this->getRoute as $route)

                                    @if ($route->operatorTicketRate->vehicle_type === $this->vehicles[$index]['vehicle_type'])

                                    <flux:select.option value="{{ $route->terminal }}">
                                        Iriga Terminal to 
                                        <strong>{{ $route->terminal}}</strong>
                                    </flux:select.option>

                                    @endif

                                @endforeach
                            </flux:select>
                        </div>

                        @if ($this->vehicles[$index]['vehicle_type'] === 'Bus' || $this->vehicles[$index]['vehicle_type'] === 'UV-express')
                            <div>
                                <flux:label>Group No.</flux:label>
                                <flux:select wire:model="vehicles.{{ $index }}.group_number" placeholder="Select group for this vehicle..." size="sm">
                                    <flux:select.option value="1">1</flux:select.option>
                                    <flux:select.option value="2">2</flux:select.option>
                                </flux:select>
                            </div>
                        @else
                            <div>
                                <flux:label>Group No.</flux:label>
                                <flux:select wire:model="vehicles.{{ $index }}.group_number" placeholder="Select group for this vehicle..." size="sm" disabled>
                                    <flux:select.option value="1">1</flux:select.option>
                                    <flux:select.option value="2">2</flux:select.option>
                                </flux:select>
                            </div>
                        @endif
                    </x-inputs-container>
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

            <p class="text-xs font-medium uppercase tracking-wide text-zinc-400">Review before saving</p>

            <flux:card>
                <div class="flex items-center gap-3">

                    <div class="w-12 h-12 rounded-full bg-primary/10 text-primary flex items-center justify-center text-sm font-medium">
                        {{ strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1)) }}
                    </div>

                    <div class="flex-1 min-w-0">
                        <x-text variant="strong" class="text-base">{{ $first_name }} {{ $last_name }}</x-text>
                        {{-- <x-text>{{ $username }}</x-text> --}}
                    </div>

                    <div>
                        @if ($this->role === 'operator')
                            <flux:badge color="blue" size="sm">Operator</flux:badge>
                        @else
                            <flux:badge color="yellow" size="sm">Commuter</flux:badge>
                        @endif
                    </div>

                </div>

                <x-inputs-container class="border-t border-zinc-200 dark:border-zinc-700 pt-3 grid-cols-3">
                    <div>
                        <x-text size="sm">Username</x-text>
                        <x-text variant="strong" >{{ $username }}</x-text>
                    </div>
                    <div>
                        <x-text size="sm">Password</x-text>
                        <x-text variant="strong" >{{ $password }}</x-text>
                    </div>
                    <div>
                        <x-text size="sm">Home address</x-text>
                        <x-text variant="strong" >{{ $address }}</x-text>
                    </div>
                    @if ($email_address)
                        <div>
                            <x-text size="sm">Email address</x-text>
                            <x-text variant="strong" >{{ $email_address }}</x-text>
                        </div>
                    @endif
                    <div>
                        <x-text size="sm">Phone no.</x-text>
                        <x-text variant="strong" >{{ $phone_number }}</x-text>
                    </div>
                    @if($role === 'commuter')
                        <div>
                            <x-text size="sm">Commuter type</x-text>
                            <x-text variant="strong" >{{ $commuter_type }}</x-text>
                        </div>
                    @endif
                    <div>
                        <x-text size="sm">Has card</x-text>
                        <x-text variant="strong">{{ $card_number ? 'Yes' : 'No' }}</x-text>
                    </div>

                </x-inputs-container>
            </flux:card>
            @if($role === 'operator')
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <p class="text-xs font-medium uppercase tracking-wide text-zinc-400">Vehicles</p>
                        <flux:badge size="sm" color="zinc">
                            {{ count($vehicles) }} Vehicle/s
                        </flux:badge>
                    </div>

                    @foreach ($vehicles as $vehicle)
                        <flux:card class="!p-4" wire:key="summary-vehicle-{{ $loop->index }}">
                            <div class="flex items-center justify-between mb-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-lg bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center">
                                        <flux:icon
                                            name="{{ $vehicle['vehicle_type'] === 'Bus' ? 'truck' : ($vehicle['vehicle_type'] === 'Uv-express' ? 'truck' : 'truck') }}"
                                            class="w-4 h-4 text-zinc-500"
                                        />
                                    </div>
                                    <div>
                                        <x-text variant="strong" class="text-sm">{{ $vehicle['plate_number'] }}</x-text>
                                        <x-text class="text-xs text-zinc-400">{{ $vehicle['vehicle_type'] }}</x-text>
                                    </div>
                                </div>
                                @if(!empty($vehicle['group_number']))
                                    <flux:badge size="sm" color="zinc">Group {{ $vehicle['group_number'] }}</flux:badge>
                                @endif
                            </div>

                            <x-inputs-container class="border-t border-zinc-200 dark:border-zinc-700 pt-3 grid-cols-2">

                                <div>
                                    <x-text class="text-xs">Route</x-text>
                                    <x-text variant="strong">{{ $vehicle['route'] }}</x-text>
                                </div>
                                <div>
                                    <x-text class="text-xs">Seat Capacity</x-text>
                                    <x-text variant="strong">{{ $vehicle['seat_capacity'] }}</x-text>
                                </div>
                            </x-inputs-container>
                        </flux:card>
                    @endforeach
                </div>
            @endif
            <x-text class="text-xs text-zinc-400 leading-relaxed">
                A welcome message with login instructions will be sent to the user. You can edit their profile anytime from the Users page.
            </x-text>
        </div>
    @endif

    <div class="flex items-center gap-2 pt-6">
        <flux:spacer />
        @if($step > 1)
            <flux:button size="sm" variant="ghost" wire:click="back">Back</flux:button>
        @endif
        @if($step < 5)
            @if ($step === 4 && $this->card_number === '')
                <flux:button size="sm" variant="primary" wire:click="stepSkipped">Skip</flux:button>
            @else
                <flux:button size="sm" variant="primary" wire:click="next">Continue</flux:button>
            @endif
        @else
            <flux:button size="sm" variant="primary" wire:click="register">
                Register this {{ ucfirst($this->role) }}
            </flux:button>
        @endif
    </div>

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
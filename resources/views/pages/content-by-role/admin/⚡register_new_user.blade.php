<?php

use Livewire\Attributes\Validate;
use Livewire\Component;
use App\Models\User;
use App\Models\Card;

new class extends Component
{
    public int $step = 1;

    public string $role = '';

    #[Validate('required_if:step,2|min:2')]
    public string $first_name = '';

    #[Validate('required_if:step,2|min:2')]
    public string $last_name = '';

    #[Validate('required_if:step,2|email')]
    public string $email = '';

    #[Validate('required_if:step,2|min:10')]
    public string $mobile = '';

    #[Validate('required_if:step,2|min:8')]
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

    public function selectRole(string $role): void
    {
        $this->role = $role;
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
                'email'      => 'required|email|unique:users,email',
                'mobile'     => 'required|min:10',
                'password'   => 'required|min:8',
            ]);
        }

        if ($this->step === 3) {
            if ($this->role === 'passenger') {
                $this->validate([
                    'date_of_birth' => 'required|date',
                    'home_address'  => 'required|min:5',
                ]);
            } else {
                $this->validate([
                    'employee_id'     => 'required',
                    'license_number'  => 'required',
                    'assigned_route'  => 'required',
                    'operator_type'   => 'required',
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

    public function register(): void
    {
        $validated_attributes = $this->validate();

        

        $name = $validated_attributes['first_name'] . ' ' . $validated_attributes['last_name'];

        $user =User::create([
            'name'     => $name,
            'email'    => $validated_attributes['email'],
            'address'  => $this->home_address,
            'role'     => $this->role,
            'password' => bcrypt($validated_attributes['password'])
        ]);

        $new_user_id = $user->id;

        Card::create([
            'user_id' => $new_user_id,
            'uid'     => $this->card_number
        ]);


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

    <div class="flex items-center gap-1 mb-6 text-xs">
        @foreach([1 => 'Select role', 2 => 'Account info', 3 => 'Details', 4 => 'Confirm'] as $s => $label)
            <div class="flex items-center gap-1 {{ $loop->last ? '' : 'flex-1' }}">
                <div class="flex items-center gap-1.5">
                    <div @class([
                        'w-6 h-6 rounded-full flex items-center justify-center text-xs font-medium shrink-0 border',
                        'bg-primary text-white border-primary'         => $step === $s,
                        'bg-primary/10 text-primary border-primary/30' => $step > $s,
                        'bg-zinc-100 text-zinc-400 border-zinc-200 dark:bg-zinc-800 dark:text-zinc-500 dark:border-zinc-700' => $step < $s,
                    ])>
                        @if($step > $s)
                            <flux:icon.check class="w-3 h-3" />
                        @else
                            {{ $s }}
                        @endif
                    </div>
                    <span @class([
                        'hidden sm:inline whitespace-nowrap',
                        'text-zinc-900 dark:text-zinc-100 font-medium' => $step === $s,
                        'text-zinc-400 dark:text-zinc-500'             => $step !== $s,
                    ])>{{ $label }}</span>
                </div>
                @if(!$loop->last)
                    <div @class([
                        'flex-1 h-px mx-1',
                        'bg-primary'          => $step > $s,
                        'bg-zinc-200 dark:bg-zinc-700' => $step <= $s,
                    ])></div>
                @endif
            </div>
        @endforeach
    </div>

    @if($step === 1)
        <div class="grid grid-cols-2 gap-3">
            <button
                wire:click="selectRole('passenger')"
                type="button"
                @class([
                    'flex flex-col items-start gap-2 rounded-lg border p-4 text-left transition cursor-pointer',
                    'border-primary bg-primary/5 ring-1 ring-primary' => $role === 'passenger',
                    'border-zinc-200 dark:border-zinc-700 hover:bg-zinc-50 dark:hover:bg-zinc-800' => $role !== 'passenger',
                ])
            >
                <div @class([
                    'rounded-md p-2',
                    'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300' => true,
                ])>
                    <flux:icon.user class="w-5 h-5" />
                </div>
                <div>
                    <p class="font-medium text-sm text-zinc-900 dark:text-zinc-100">Passenger</p>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5 leading-relaxed">A commuter who rides and tracks transit routes.</p>
                </div>
            </button>

            <button
                wire:click="selectRole('operator')"
                type="button"
                @class([
                    'flex flex-col items-start gap-2 rounded-lg border p-4 text-left transition cursor-pointer',
                    'border-primary bg-primary/5 ring-1 ring-primary' => $role === 'operator',
                    'border-zinc-200 dark:border-zinc-700 hover:bg-zinc-50 dark:hover:bg-zinc-800' => $role !== 'operator',
                ])
            >
                <div class="rounded-md p-2 bg-amber-100 text-amber-700 dark:bg-amber-900 dark:text-amber-300">
                    <flux:icon.identification class="w-5 h-5" />
                </div>
                <div>
                    <p class="font-medium text-sm text-zinc-900 dark:text-zinc-100">Operator</p>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5 leading-relaxed">A driver or staff assigned to a route or vehicle.</p>
                </div>
            </button>
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
            </div>
            <flux:input
                wire:model="email"
                type="email"
                label="Email address"
                placeholder="user@example.com"
                size="sm"
            />
            <flux:input
                wire:model="mobile"
                label="Mobile number"
                placeholder="+63 9XX XXX XXXX"
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
            <flux:callout icon="information-circle" color="blue">
                <flux:callout.text>Passengers get a travel card and can load balance, and view travel history.</flux:callout.text>
            </flux:callout>

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
                description="Leave blank to issue a new card automatically."
            />
        </div>
    @endif

    @if($step === 3 && $role === 'operator')
        <div class="space-y-4">
            <flux:callout icon="information-circle" color="warning">
                <flux:callout.text>Operators can be assigned to routes and vehicles, and can scan passenger cards.</flux:callout.text>
            </flux:callout>

            <div class="grid grid-cols-2 gap-3">
                <flux:input
                    wire:model="employee_id"
                    label="Employee ID"
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
            <div class="grid grid-cols-2 gap-3">
                <flux:select
                    wire:model="assigned_route"
                    label="Assigned route"
                    size="sm"
                >
                    <option value="">— Select route —</option>
                    <option>Route 1 – Cubao to Fairview</option>
                    <option>Route 2 – Monumento to SM North</option>
                    <option>Route 3 – Manila to Quezon City</option>
                </flux:select>
                <flux:input
                    wire:model="vehicle_plate"
                    label="Vehicle plate"
                    placeholder="e.g. ABC 1234"
                    size="sm"
                />
            </div>
            <flux:select
                wire:model="operator_type"
                label="Operator type"
                size="sm"
            >
                <option>Driver</option>
                <option>Conductor</option>
                <option>Inspector</option>
            </flux:select>
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
            Register user
        </flux:button>
    @endif
</div>
</div>
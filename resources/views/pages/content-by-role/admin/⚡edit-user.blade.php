<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use App\Events\UserInfoUpdated;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\Terminal;
use App\Models\Route;

new class extends Component
{
    public User $user;

    public $name;
    public $email;
    public $address;

    public $confirmingAddVehicle;
    public bool $confirmingDelete = false;
    public $confirmingDeleteVehicle = null;

    public $create_vehicle_type = null;
    public $create_route        = null;
    public $create_plate_number = null;
    public $create_total_seats  = null;

    // Per-vehicle edit state: keyed by vehicle ID
    public array $editingVehicle = [];

    public function mount($user_id)
    {
        $this->user    = User::findOrFail($user_id);
        $this->name    = $this->user->name;
        $this->email   = $this->user->email;
        $this->address = $this->user->address;
    }

    #[Computed]
    public function getVehicle()
    {
        return Vehicle::where('user_id', $this->user->id)->get();
    }

    #[Computed]
    public function getTerminal()
    {
        return Terminal::get();
    }

    // ─── Vehicle CRUD ────────────────────────────────────────────────────────────

    public function addNewVehicle()
    {
        DB::transaction(function () {
            $attributes = $this->validate([
                'create_vehicle_type' => 'required|min:1|string',
                'create_route'        => 'required|min:1|string',
                'create_plate_number' => 'required|min:1|string',
                'create_total_seats'  => 'required|integer|min:1',
            ]);

            $new_vehicle = $this->user->vehicle()->create([
                'vehicle_type' => $attributes['create_vehicle_type'],
                'route'        => $attributes['create_route'],
                'plate_number' => $attributes['create_plate_number'],
                'total_seats'  => $attributes['create_total_seats'],
            ]);

            Route::create([
                'vehicle_id'  => $new_vehicle->id,
                'terminal_id' => $attributes['create_route'],
            ]);
        });

        $this->create_vehicle_type = '';
        $this->create_route        = '';
        $this->create_plate_number = '';
        $this->create_total_seats  = '';

        unset($this->getVehicle);
        $this->addingVehicle(false);
    }

    public function editVehicle(int $vehicle_id): void
    {
        $vehicle = Vehicle::where('user_id', $this->user->id)->findOrFail($vehicle_id);

        $this->editingVehicle[$vehicle_id] = [
            'vehicle_type' => $vehicle->vehicle_type,
            'plate_number' => $vehicle->plate_number,
            'total_seats'  => $vehicle->total_seats,
        ];
    }

    public function updateVehicle(int $vehicle_id): void
    {
        $data = $this->validate([
            "editingVehicle.{$vehicle_id}.vehicle_type" => 'required|string',
            "editingVehicle.{$vehicle_id}.plate_number" => 'required|string',
            "editingVehicle.{$vehicle_id}.total_seats"  => 'required|integer|min:1',
        ]);

        $vehicle = Vehicle::where('user_id', $this->user->id)->findOrFail($vehicle_id);
        $vehicle->update($data['editingVehicle'][$vehicle_id]);

        unset($this->editingVehicle[$vehicle_id]);
        unset($this->getVehicle);

        $this->dispatch('vehicle-updated');
    }

    public function cancelEditVehicle(int $vehicle_id): void
    {
        unset($this->editingVehicle[$vehicle_id]);
    }

    public function deleteVehicle(int $vehicle_id): void
    {
        $vehicle = Vehicle::where('user_id', $this->user->id)->findOrFail($vehicle_id);
        $vehicle->delete();

        $this->confirmingDeleteVehicle = null;
        $this->dispatch('vehicle-deleted');

        unset($this->getVehicle);
    }

    // ─── User ────────────────────────────────────────────────────────────────────

    public function save()
    {
        $validated = $this->validate([
            'name'    => ['required'],
            'email'   => ['required', 'email'],
            'address' => ['required'],
        ]);

        $this->user->update($validated);
        $this->dispatch('user-saved');

        broadcast(new UserInfoUpdated($this->user->id));
    }

    public function deleteUser()
    {
        $this->user->delete();
        $this->redirect('/admin/users');
    }

    public function addingVehicle(bool $status)
    {
        $this->confirmingAddVehicle = $status;
    }
};
?>

<div>
    {{-- ── Header ───────────────────────────────────────────────────────────── --}}
    <div class="mb-4">
        <flux:heading size="lg">User Information</flux:heading>
        <flux:subheading>Viewing details for {{ $user->name }}</flux:subheading>
    </div>

    <div class="flex items-center gap-4 mb-6">
        <flux:avatar src="{{ $user->avatar_url }}" name="{{ $user->name }}" size="xl" />
        <div>
            <div class="font-semibold text-base text-zinc-800 dark:text-zinc-200">{{ $user->name }}</div>
            <div class="text-sm text-zinc-500">{{ $user->email }}</div>
        </div>
    </div>

    <div class="grid w-full grid-cols-2 gap-6 text-sm mb-6">
        <div>
            <span class="block text-xs text-zinc-400 font-medium uppercase tracking-wider">Role</span>
            @if ($user->role === 'operator')
                <flux:badge color="blue" size="sm" inset="top bottom">Operator</flux:badge>
            @else
                <flux:badge color="yellow" size="sm" inset="top bottom">Commuter</flux:badge>
            @endif
        </div>
        <div>
            <span class="block text-xs text-zinc-400 font-medium uppercase tracking-wider">Joined</span>
            <span class="text-zinc-700 dark:text-zinc-300 font-medium">{{ $user->created_at->format('M d, Y') }}</span>
        </div>
    </div>

    <form wire:submit="save">
        <div class="space-y-4 w-full border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 bg-zinc-50 dark:bg-zinc-800/50">
            <div class="grid w-full grid-cols-2 gap-6 mb-4">
                <flux:input label="Name"    wire:model="name"    class="w-full" />
                <flux:input label="Email"   wire:model="email"   class="w-full" />
                <flux:input label="Address" wire:model="address" class="w-full" />
            </div>
        </div>

        
        @if ($user->role === 'operator')

            <div class="flex items-center my-6">
                <flux:heading size="lg" class="mb-6 mt-3 flex-1">Vehicle Information</flux:heading>

                @if (!$confirmingAddVehicle)
                    <flux:button
                        wire:key="btn-add-vehicle"
                        variant="primary"
                        size="sm"
                        type="button"
                        wire:click="addingVehicle(true)"
                        wire:loading.attr="disabled"
                        wire:target="addingVehicle"
                    >Add Vehicle</flux:button>
                @else
                    <flux:button
                        wire:key="btn-cancel-vehicle"
                        variant="ghost"
                        size="sm"
                        type="button"
                        wire:click="addingVehicle(false)"
                        wire:loading.attr="disabled"
                        wire:target="addingVehicle"
                    >Cancel</flux:button>
                @endif
            </div>

            {{-- Add vehicle form --}}
            @if ($confirmingAddVehicle)
                <div class="space-y-4 w-full border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 bg-zinc-50 dark:bg-zinc-800/50 mb-4">
                    <x-form-heading>Add Vehicle</x-form-heading>
                    <div>
                        <x-inputs-container>
                            <x-select wire:model="create_vehicle_type" placeholder="Vehicle Type">
                                <x-select-option>Bus</x-select-option>
                                <x-select-option>Van</x-select-option>
                                <x-select-option>Multi-cab</x-select-option>
                                <x-select-option>Jeep</x-select-option>
                            </x-select>

                            <x-select wire:model="create_route" placeholder="Select Route">
                                @foreach ($this->getTerminal as $terminal)
                                    <x-select-option value="{{ $terminal->id }}">{{ $terminal->municipality }}</x-select-option>
                                @endforeach
                            </x-select>

                            <x-input label="Plate Number"        wire:model="create_plate_number" />
                            <x-input label="Total Vehicle Seats" wire:model="create_total_seats" />
                        </x-inputs-container>

                        <x-button
                            type="button"
                            size="sm"
                            wire:click="addNewVehicle()"
                        >Add</x-button>
                    </div>
                </div>
            @endif

            {{-- Vehicle list --}}
            @foreach ($this->getVehicle as $index => $vehicle)
                <div
                    class="space-y-4 w-full border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 bg-zinc-50 dark:bg-zinc-800/50 mb-4"
                    wire:key="vehicle-container-{{ $vehicle->id }}"
                >
                    {{-- Card header: badge + edit/save/cancel + delete --}}
                    <div class="flex items-center gap-2 mb-2">
                        <div class="flex-1">
                            <flux:badge color="green" size="sm" inset="top bottom">
                                Vehicle {{ $index + 1 }}
                            </flux:badge>
                        </div>

                        @if (isset($editingVehicle[$vehicle->id]))
                            {{-- Save button --}}
                            <flux:button
                                type="button"
                                variant="primary"
                                size="sm"
                                wire:click="updateVehicle({{ $vehicle->id }})"
                                wire:loading.attr="disabled"
                                wire:target="updateVehicle({{ $vehicle->id }})"
                            >
                                <flux:icon.check class="w-4 h-4 mr-1" />
                                Save
                            </flux:button>

                            {{-- Cancel edit button --}}
                            <flux:button
                                type="button"
                                variant="ghost"
                                size="sm"
                                wire:click="cancelEditVehicle({{ $vehicle->id }})"
                            >
                                Cancel
                            </flux:button>
                        @else
                            {{-- Edit button --}}
                            <flux:button
                                type="button"
                                variant="ghost"
                                size="sm"
                                wire:click="editVehicle({{ $vehicle->id }})"
                                title="Edit vehicle"
                            >
                                <flux:icon.pencil class="w-5 h-5" />
                            </flux:button>
                        @endif

                        {{-- Delete button (hidden while editing to avoid accidents) --}}
                        @unless (isset($editingVehicle[$vehicle->id]))
                            <flux:button
                                type="button"
                                variant="ghost"
                                size="sm"
                                wire:click="$set('confirmingDeleteVehicle', {{ $vehicle->id }})"
                                title="Delete vehicle"
                            >
                                <flux:icon.trash class="w-5.5 h-5.5" />
                            </flux:button>
                        @endunless
                    </div>

                    {{-- Delete confirmation --}}
                    @if ($confirmingDeleteVehicle === $vehicle->id)
                        <div class="rounded-lg border border-red-200 bg-red-50 dark:bg-red-900/20 dark:border-red-800 p-4 space-y-3">
                            <div>
                                <p class="font-semibold text-red-700 dark:text-red-400">Are you sure?</p>
                                <p class="text-sm text-red-600 dark:text-red-300 mt-1">
                                    You're about to delete <strong>{{ $vehicle->vehicle_type }}</strong> with plate number
                                    <strong>{{ $vehicle->plate_number }}</strong>.
                                    This action cannot be reversed.
                                </p>
                            </div>
                            <div class="flex gap-2">
                                <flux:spacer />
                                <flux:button
                                    size="sm"
                                    wire:click="$set('confirmingDeleteVehicle', null)"
                                >Cancel</flux:button>
                                <flux:button
                                    size="sm"
                                    variant="danger"
                                    wire:click="deleteVehicle({{ $vehicle->id }})"
                                >Yes, delete vehicle</flux:button>
                            </div>
                        </div>
                    @endif

                    {{-- Vehicle fields: editable or read-only --}}
                    <div class="grid w-full grid-cols-2 gap-6">

                        @if (isset($editingVehicle[$vehicle->id]))
                            {{-- Editable fields --}}
                            <div>
                                <flux:label>Vehicle Type</flux:label>
                                <flux:select wire:model="editingVehicle.{{ $vehicle->id }}.vehicle_type" class="w-full">
                                    <option value="Bus"       @selected($editingVehicle[$vehicle->id]['vehicle_type'] === 'Bus')>Bus</option>
                                    <option value="Van"       @selected($editingVehicle[$vehicle->id]['vehicle_type'] === 'Van')>Van</option>
                                    <option value="Multi-cab" @selected($editingVehicle[$vehicle->id]['vehicle_type'] === 'Multi-cab')>Multi-cab</option>
                                    <option value="Jeep"      @selected($editingVehicle[$vehicle->id]['vehicle_type'] === 'Jeep')>Jeep</option>
                                </flux:select>
                            </div>

                            <flux:input
                                label="Plate No."
                                wire:model="editingVehicle.{{ $vehicle->id }}.plate_number"
                                class="w-full"
                            />

                            <flux:input
                                label="Total Seats"
                                type="number"
                                min="1"
                                wire:model="editingVehicle.{{ $vehicle->id }}.total_seats"
                                class="w-full"
                            />

                            <flux:input
                                label="Date Registered"
                                value="{{ $vehicle->created_at->format('Y-m-d') }}"
                                class="w-full"
                                disabled
                            />

                        @else
                            {{-- Read-only fields --}}
                            <flux:input label="Vehicle Type"    value="{{ $vehicle->vehicle_type }}"              class="w-full" readonly />
                            <flux:input label="Plate No."       value="{{ $vehicle->plate_number }}"              class="w-full" readonly />
                            <flux:input label="Total Seats"     value="{{ $vehicle->total_seats }}"               class="w-full" readonly />
                            <flux:input label="Date Registered" value="{{ $vehicle->created_at->format('Y-m-d') }}" class="w-full" readonly />
                        @endif

                    </div>
                </div>
            @endforeach

        @endif

        <div class="flex w-full gap-2 mt-6">
            <div class="flex flex-1">
                <flux:button
                    type="button"
                    variant="danger"
                    wire:click="$set('confirmingDelete', true)"
                >Delete</flux:button>
            </div>
            <flux:button variant="primary" type="submit">Save Changes</flux:button>
        </div>
    </form>

    @if ($confirmingDelete)
        <div class="rounded-lg border border-red-200 bg-red-50 dark:bg-red-900/20 dark:border-red-800 p-4 space-y-3 mt-4">
            <div>
                <p class="font-semibold text-red-700 dark:text-red-400">Are you sure?</p>
                <p class="text-sm text-red-600 dark:text-red-300 mt-1">
                    You're about to delete <strong>{{ $user->name }}</strong>.
                    This action cannot be reversed.
                </p>
            </div>
            <div class="flex gap-2">
                <flux:spacer />
                <flux:button
                    size="sm"
                    wire:click="$set('confirmingDelete', false)"
                >Cancel</flux:button>
                <flux:button
                    size="sm"
                    variant="danger"
                    wire:click="deleteUser"
                >Yes, delete user</flux:button>
            </div>
        </div>
    @endif

</div>
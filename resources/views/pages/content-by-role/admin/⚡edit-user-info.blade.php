<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;

use App\Models\User;
use App\Models\Terminal;
use App\Models\Vehicle;
use App\Models\Route;
use App\Models\RouteList;

use App\Services\UserService;


new #[Layout('layouts.admin-layout')] class extends Component
{
    public User $user;

    public $name;
    public $username;
    public $address;

    public $confirmingAddVehicle = null;
    public bool $confirmingDelete = false;
    public array $editingVehicles = [];
    public ?int $confirmingDeleteVehicle = null;

    public $create_vehicle_type = null;
    public $create_route        = null;
    public $create_plate_number = null;
    public $create_total_seats  = null;

    public ?int $confirmingEditVehicle = null;

    public $route_list_id;

    #[Computed]
    public function getVehicle() {
        return Vehicle::where('user_id', $this->user->id)->get();
    }

    #[Computed]
    public function getTerminal() {
        return RouteList::with('operatorTicketRate')
            ->when($this->create_vehicle_type, function($q) {
                $q->where('vehicle_type', $this->create_vehicle_type);
            })
            ->get();
    }

    public function mount() {
        $this->name    = $this->user->name;
        $this->username   = $this->user->username;
        $this->address = $this->user->address;

        foreach ($this->getVehicle as $vehicle) {
            $this->editingVehicles[$vehicle->id] = [
                'vehicle_type' => $vehicle->vehicle_type,
                'total_seats'  => $vehicle->total_seats,
                'plate_number' => $vehicle->plate_number,
            ];
        }
    }

    public function save() {
        $attributes = $this->validate([
            'name'    => 'required|min:2|string',
            'username'   => 'required|min:1|string',
            'address' => 'required|min:1|string',
        ]);

        app(UserService::class)->update($this->user, $attributes);

        Flux::toast(
            variant: 'success',
            heading: 'Changes saved.',
            text: 'Your changes have been saved.'
        );
    }

    public function deleteUser() {

        // $this->user->delete();
        
        app(UserService::class)->destroy($this->user);

        $this->redirect(route('admin.users'), navigate: true);

        Flux::toast(
            variant: 'success',
            heading: 'User deleted.',
            text: 'User has been deleted.'
        );
    }

    public function addingVehicle($status) {
        $this->confirmingAddVehicle = $status;
    }

    public function addNewVehicle() {
        DB::transaction(function () {
            $attributes = $this->validate([
                'create_vehicle_type' => 'required|min:1|string',
                'create_route'        => 'required|min:1|string',
                'create_plate_number' => 'required|min:1|string',
                'create_total_seats'  => 'required|integer|min:1',
            ]);

            $route_list = RouteList::where('vehicle_type', $attributes['create_vehicle_type'])
                ->where('terminal', $attributes['create_route'])
                ->first();

            $new_vehicle = $this->user->vehicles()->create([
                'route_list_id' => $route_list->id,
                'vehicle_type' => $attributes['create_vehicle_type'],
                'plate_number' => $attributes['create_plate_number'],
                'total_seats'  => $attributes['create_total_seats'],
            ]);

            $this->editingVehicles[$new_vehicle->id] = [
                'vehicle_type' => $new_vehicle->vehicle_type,
                'total_seats'  => $new_vehicle->total_seats,
                'plate_number' => $new_vehicle->plate_number,
            ];
        });

        $this->create_vehicle_type = '';
        $this->create_route        = '';
        $this->create_plate_number = '';
        $this->create_total_seats  = '';

        unset($this->getVehicle);
        $this->addingVehicle(false);

        Flux::toast(
            variant: 'success',
            heading: 'Vehicle added.',
            text: 'New vehicle has been added.'
        );
    }

    public function editVehicle(int $vehicle_id) {
        $this->confirmingEditVehicle = $vehicle_id;
    }

    public function cancelEditVehicle() {
        // Restore original values from the database so edits are discarded
        $vehicle = Vehicle::find($this->confirmingEditVehicle);

        if ($vehicle) {
            $this->editingVehicles[$vehicle->id] = [
                'vehicle_type' => $vehicle->vehicle_type,
                'total_seats'  => $vehicle->total_seats,
                'plate_number' => $vehicle->plate_number,
            ];
        }

        $this->confirmingEditVehicle = null;
    }

    public function updateVehicle(int $vehicle_id) {
        $data = $this->validate([
            "editingVehicles.{$vehicle_id}.vehicle_type" => 'required|string',
            "editingVehicles.{$vehicle_id}.plate_number" => 'required|string',
            "editingVehicles.{$vehicle_id}.total_seats"  => 'required|integer|min:1',
        ]);

        Vehicle::where('id', $vehicle_id)
            ->where('user_id', $this->user->id)
            ->update($data['editingVehicles'][$vehicle_id]);

        $this->confirmingEditVehicle = null;
        unset($this->getVehicle);

        Flux::toast(
            variant: 'success',
            heading: 'Vehicle updated.',
            text: 'Vehicle information has been updated.'
        );
    }

    public function deleteVehicle(int $vehicle_id) {
        Vehicle::where('id', $vehicle_id)
            ->where('user_id', $this->user->id)
            ->delete();

        unset($this->editingVehicles[$vehicle_id]);
        $this->confirmingDeleteVehicle = null;
        unset($this->getVehicle);

        Flux::toast(
            variant: 'success',
            heading: 'Vehicle deleted.',
            text: 'Vehicle has been deleted.'
        );
    }

    public function updatedCreateVehicleType($value)
    {
        $routes = RouteList::where('vehicle_type', $value)->get();

        if ($routes->count() === 1) {
            $this->create_route = $routes->first()->terminal;
        } else {
            $this->create_route = null;
        }
    }
};
?>

<div>
    <flux:breadcrumbs>
        <flux:breadcrumbs.item href="{{ route('admin.users') }}" wire:navigate>Users</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ $this->user->name }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <x-pages-heading heading="Edit User Information"/>

    <div class="mt-4 mb-6 p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 flex items-center justify-between flex-wrap gap-4">

        <div class="flex items-center gap-4">
            <flux:avatar src="{{ $user->avatar_url }}" name="{{ $user->name }}" size="xl" />
            <div>
                <p class="font-medium text-base text-zinc-800 dark:text-zinc-200">{{ $user->name }}</p>
                <p class="text-sm text-zinc-500">{{ $user->user_code }}</p>
            </div>
        </div>

        <div class="flex gap-8">
            <div>
                <span class="block text-xs text-zinc-400 font-medium uppercase tracking-wider mb-1">Role</span>
                @if ($user->role === 'operator')
                    <flux:badge color="blue" size="sm">Operator</flux:badge>
                @else
                    <flux:badge color="yellow" size="sm">Commuter</flux:badge>
                @endif
            </div>
            <div>
                <span class="block text-xs text-zinc-400 font-medium uppercase tracking-wider mb-1">Joined</span>
                <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">
                    {{ $user->created_at->format('M d, Y') }}
                </span>
            </div>
            @if ($user->role === 'operator')
            <div>
                <span class="block text-xs text-zinc-400 font-medium uppercase tracking-wider mb-1">Vehicles</span>
                <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">
                    {{ $this->getVehicle->count() }}
                </span>
            </div>
            @endif
        </div>

    </div>

    <form wire:submit="save">
        <div class="w-full border border-zinc-200 dark:border-zinc-700 rounded-xl bg-white dark:bg-zinc-900 overflow-hidden">

            {{-- Section header --}}
            <div class="flex items-center gap-2 px-6 py-3 border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800">
                <flux:icon.user class="w-4 h-4 text-zinc-400" />
                <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Personal information</span>
            </div>

            <div class="p-6 space-y-4">
                <div class="grid w-full grid-cols-2 gap-6">
                    <flux:input label="Name"     wire:model="name"     class="w-full" />
                    <flux:input label="Username" wire:model="username" class="w-full" />
                    <div class="col-span-2">
                        <flux:input label="Address" wire:model="address" class="w-full" />
                    </div>

                    {{-- Read-only context fields --}}
                    <flux:input label="Role"      value="{{ ucfirst($user->role) }}"  class="w-full" readonly />
                    <flux:input label="User code" value="{{ $user->user_code }}"      class="w-full" readonly />
                </div>

                <div class="flex w-full gap-2 pt-4 border-t border-zinc-200 dark:border-zinc-700">
                    <flux:button
                        type="button"
                        variant="ghost"
                        size="sm"
                        class="text-red-500 border-red-200 hover:bg-red-50 dark:hover:bg-red-950"
                        icon="trash"
                        wire:click="$set('confirmingDelete', true)"
                    >Delete user</flux:button>
                    <flux:spacer />
                    <flux:button size="sm" variant="primary" type="submit" icon="check">
                        Save changes
                    </flux:button>
                </div>

                @if ($confirmingDelete)
                    <div class="rounded-lg border border-red-200 bg-red-50 dark:bg-red-900/20 dark:border-red-800 p-4 space-y-3">
                        <p class="font-medium text-red-700 dark:text-red-400">Are you sure?</p>
                        <p class="text-sm text-red-600 dark:text-red-300">
                            You're about to permanently delete <strong>{{ $user->name }}</strong>
                            along with all their vehicles. This cannot be undone.
                        </p>
                        <div class="flex gap-2 justify-end">
                            <flux:button size="sm" wire:click="$set('confirmingDelete', false)">
                                Cancel
                            </flux:button>
                            <flux:button size="sm" variant="danger" wire:click="deleteUser">
                                Yes, delete user
                            </flux:button>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </form>

    
    @if ($user->role === 'operator')

        <div class="flex items-center mt-8 mb-4">
            <div class="flex-1">
                <p class="text-base font-medium text-zinc-800 dark:text-zinc-200">Vehicle information</p>
                <p class="text-xs text-zinc-400">Manage vehicles assigned to this operator.</p>
            </div>
            @if (!$confirmingAddVehicle)
                <flux:button variant="primary" size="sm" icon="plus"
                    wire:click="addingVehicle(true)" wire:loading.attr="disabled">
                    Add vehicle
                </flux:button>
            @else
                <flux:button variant="ghost" size="sm"
                    wire:click="addingVehicle(false)" wire:loading.attr="disabled">
                    Cancel
                </flux:button>
            @endif
        </div>

        {{-- Add vehicle form --}}
        @if ($confirmingAddVehicle)
            <div class="w-full border border-zinc-200 dark:border-zinc-700 rounded-xl bg-white dark:bg-zinc-900 overflow-hidden mb-4">
                <div class="flex items-center gap-2 px-6 py-3 border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800">
                    <flux:icon.plus class="w-4 h-4 text-zinc-400" />
                    <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">New vehicle</span>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-2 gap-6 mb-4">
                        <x-select wire:model.live="create_vehicle_type" placeholder="Vehicle type">
                            <x-select-option value="Bus">Bus</x-select-option>
                            <x-select-option value="UV-express">UV-express</x-select-option>
                            <x-select-option value="Multi-cab">Multi-cab</x-select-option>
                            <x-select-option value="Jeep">Jeep</x-select-option>
                            <x-select-option value="Mountain-unit">Mountain Unit</x-select-option>
                        </x-select>

                        <x-select wire:model.live="create_route" placeholder="Select route">
                            <x-select-option value="" disabled selected>-- Select a Route --</x-select-option>
                            @foreach ($this->getTerminal as $route)
                                <x-select-option value="{{ $route->terminal }}">{{ $route->terminal }}</x-select-option>
                            @endforeach
                        </x-select>

                        <x-input label="Plate number" wire:model="create_plate_number" />
                        <x-input label="Total seats"  wire:model="create_total_seats" type="number" min="1" />

                        @if ($this->create_vehicle_type === 'Bus' || $this->create_vehicle_type === 'UV-express')
                             <x-input label="Group No." wire:model="create_group_no" type="number" min="1" />
                        @else
                            <x-input label="Group No."  wire:model="create_group_no" type="number" min="1" disabled />
                        @endif

                    </div>
                    <div class="flex justify-end">
                        <flux:button type="button" size="sm" variant="primary"
                            wire:click="addNewVehicle"
                            wire:loading.attr="disabled"
                            wire:target="addNewVehicle">
                            Add vehicle
                        </flux:button>
                    </div>
                </div>
            </div>
        @endif
        @foreach ($this->getVehicle as $index => $vehicle)
            <div class="w-full border border-zinc-200 dark:border-zinc-700 rounded-xl bg-white dark:bg-zinc-900 overflow-hidden mb-4"
                wire:key="vehicle-container-{{ $vehicle->id }}">

                {{-- Card header --}}
                <div class="flex items-center gap-3 px-6 py-3 border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800">
                    <flux:badge color="green" size="sm">{{ $index + 1 }}</flux:badge>
                    <span class="font-mono text-sm font-medium text-zinc-700 dark:text-zinc-300">
                        {{ $vehicle->plate_number }}
                    </span>
                    <span class="text-xs text-zinc-400">· {{ $vehicle->vehicle_type }}</span>

                    <div class="flex-1"></div>

                    @if ($confirmingEditVehicle === $vehicle->id)
                        <flux:button type="button" variant="primary" size="sm" icon="check"
                            wire:click="updateVehicle({{ $vehicle->id }})"
                            wire:loading.attr="disabled">
                            Save
                        </flux:button>
                        <flux:button type="button" variant="ghost" size="sm"
                            wire:click="cancelEditVehicle">
                            Cancel
                        </flux:button>
                    @else
                        <flux:button type="button" variant="ghost" size="sm" icon="pencil"
                            wire:click="editVehicle({{ $vehicle->id }})"/>
                        <flux:button type="button" variant="ghost" size="sm" icon="trash"
                            class="text-red-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-950"
                            wire:click="$set('confirmingDeleteVehicle', {{ $vehicle->id }})"/>
                    @endif
                </div>

                {{-- Delete confirmation --}}
                @if ($confirmingDeleteVehicle === $vehicle->id)
                    <div class="mx-6 mt-4 rounded-lg border border-red-200 bg-red-50 dark:bg-red-900/20 dark:border-red-800 p-4 space-y-3">
                        <p class="font-medium text-red-700 dark:text-red-400">Delete this vehicle?</p>
                        <p class="text-sm text-red-600 dark:text-red-300">
                            <strong>{{ $vehicle->vehicle_type }}</strong> with plate
                            <strong>{{ $vehicle->plate_number }}</strong> will be permanently removed.
                        </p>
                        <div class="flex gap-2 justify-end">
                            <flux:button size="sm" wire:click="$set('confirmingDeleteVehicle', null)">Cancel</flux:button>
                            <flux:button size="sm" variant="danger" wire:click="deleteVehicle({{ $vehicle->id }})">
                                Yes, delete
                            </flux:button>
                        </div>
                    </div>
                @endif

                {{-- Vehicle fields --}}
                <div class="p-6 grid grid-cols-2 gap-6">
                    @if ($confirmingEditVehicle === $vehicle->id)
                        <div>
                            <flux:label>Vehicle type</flux:label>
                            <flux:select wire:model="editingVehicles.{{ $vehicle->id }}.vehicle_type">
                                <option value="Bus"       @selected($editingVehicles[$vehicle->id]['vehicle_type'] === 'Bus')>Bus</option>
                                <option value="UV-express"       @selected($editingVehicles[$vehicle->id]['vehicle_type'] === 'UV-express')>UV-express</option>
                                <option value="Multi-cab" @selected($editingVehicles[$vehicle->id]['vehicle_type'] === 'Multi-cab')>Multi-cab</option>
                                <option value="Jeep"      @selected($editingVehicles[$vehicle->id]['vehicle_type'] === 'Jeep')>Jeep</option>
                            </flux:select>
                        </div>
                        <flux:input label="Plate no."   wire:model="editingVehicles.{{ $vehicle->id }}.plate_number" />
                        <flux:input label="Total seats" wire:model="editingVehicles.{{ $vehicle->id }}.total_seats" type="number" min="1" />
                        <flux:input label="Date registered" value="{{ $vehicle->created_at->format('Y-m-d') }}" disabled />
                    @else
                        <flux:input label="Vehicle type"    value="{{ $vehicle->vehicle_type }}"                readonly />
                        <flux:input label="Plate no."       value="{{ $vehicle->plate_number }}"                readonly class="font-mono" />
                        <flux:input label="Total seats"     value="{{ $vehicle->total_seats }}"                 readonly />
                        <flux:input label="Date registered" value="{{ $vehicle->created_at->format('Y-m-d') }}" readonly />
                    @endif
                </div>

            </div>
        @endforeach

    @endif

</div>
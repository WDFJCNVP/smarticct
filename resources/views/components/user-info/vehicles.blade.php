<?php

use Livewire\Component;
use Livewire\Attributes\Computed;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\RouteList;
use App\Models\VehicleGroup;
use App\Models\OperatorTicketRate;
use App\Models\Queue;
use App\Models\Post;

use Illuminate\Support\Facades\DB;

use App\Services\AuditLogsService;

new class extends Component
{
    public User $user;

    public $confirmingAddVehicle = null;
    public array $editingVehicles = [];

    public $create_vehicle_type = '';
    public $create_route        = '';
    public $create_plate_number = '';
    public $create_total_seats  = '';
    public $create_group_number = '';
    // NEW fields for adding a vehicle
    public $create_engine_number = '';
    public $create_body_number = '';
    public $create_chassis_number = '';
    public $create_driver_name = '';
    public $create_has_franchise = false;
    public $create_franchise_expiry_date = '';

    public ?int $confirmingEditVehicle = null;

    public $route_list_id;
    public $vehicles;

    protected $validationAttributes = [
     */
    protected $validationAttributes = [
        // Personal info
        'name'          => 'name',
        'email_address' => 'email address',
        'phone_number'  => 'phone number',
        'address'       => 'address',
        'otp_code'      => 'verification code',

        // Card issuance
        'cardUid'       => 'card UID',

        // Add‑vehicle fields
        'create_vehicle_type'          => 'vehicle type',
        'create_route'                 => 'route',
        'create_plate_number'          => 'plate number',
        'create_total_seats'           => 'total seats',
        'create_group_number'          => 'group number',
        'create_engine_number'         => 'engine number',
        'create_body_number'           => 'body number',
        'create_chassis_number'        => 'chassis number',
        'create_driver_name'           => 'driver name',
        'create_has_franchise'         => 'franchise verification',
        'create_franchise_expiry_date' => 'validity date of franchise',

        // Edit‑vehicle fields (array notation)
        'editingVehicles.*.plate_number'          => 'plate number',
        'editingVehicles.*.total_seats'           => 'total seats',
        'editingVehicles.*.group_number'          => 'group number',
        'editingVehicles.*.engine_number'         => 'engine number',
        'editingVehicles.*.body_number'           => 'body number',
        'editingVehicles.*.chassis_number'        => 'chassis number',
        'editingVehicles.*.driver_name'           => 'driver name',
        'editingVehicles.*.has_franchise'         => 'franchise verification',
        'editingVehicles.*.franchise_expiry_date' => 'validity date of franchise',
    ];

    public function mount(User $user): void
    {
        $this->user = $user;

        foreach ($this->getVehicle as $vehicle) {
            $this->editingVehicles[$vehicle->id] = [
                'vehicle_type'          => $vehicle->vehicle_type,
                'total_seats'           => $vehicle->total_seats,
                'plate_number'          => $vehicle->plate_number,
                'group_number'          => $this->getVehicleGroupNumber($vehicle->id),
                'engine_number'         => $vehicle->engine_number ?? '',
                'body_number'           => $vehicle->body_number ?? '',
                'chassis_number'        => $vehicle->chassis_number ?? '',
                'driver_name'           => $vehicle->driver_name ?? '',
                'has_franchise'         => (bool) $vehicle->has_franchise,
                'franchise_expiry_date' => $vehicle->franchise_expiry_date ? $vehicle->franchise_expiry_date->format('Y-m-d') : '',
            ];
        }
    }

    #[Computed]
    public function getRoute()
    {
         return RouteList::with('operatorTicketRate')
            ->get();
    }

    #[Computed]
    public function getVehicleTypeOptions() {
        return OperatorTicketRate::select('vehicle_type')->distinct()->get();
    }

    public function getVehicleGroupNumber($vehicle_id) {
        return VehicleGroup::where('vehicle_id', $vehicle_id)->value('group_number');
    }

    #[Computed]
    public function getVehicle() {
        return Vehicle::with('vehicle_group')->where('user_id', $this->user->id)->get();
    }

    /**
     * A vehicle that is currently in the dispatch queue (any status other
     * than "departed") cannot have its details changed — the record is
     * "live" and editing it mid-queue could desync the dispatch board,
     * fares already collected, or the printed queue slip.
     */
    public function isVehicleQueued(int $vehicle_id): bool
    {
        return Queue::where('vehicle_id', $vehicle_id)
            ->where('status', '!=', 'departed')
            ->exists();
    }

    /**
     * A vehicle that currently has an active/published rental post in the
     * feed cannot have its details changed — commuters may already be
     * viewing or acting on that listing (trip requests, rental offers).
     */
    public function isVehiclePosted(int $vehicle_id): bool
    {
        return Post::where('type', 'rental')
            ->where('status', 'published')
            ->where('metadata->vehicle_id', $vehicle_id)
            ->exists();
    }

    /**
     * Returns a human-readable reason why a vehicle's details are locked
     * from editing/deleting, or null if it's safe to modify.
     */
    public function vehicleLockReason(int $vehicle_id): ?string
    {
        if ($this->isVehicleQueued($vehicle_id)) {
            return 'This vehicle is currently in the queue and cannot be edited or deleted until it departs.';
        }

        if ($this->isVehiclePosted($vehicle_id)) {
            return 'This vehicle is currently posted in the feed and cannot be edited or deleted until the post is taken down.';
        }

        return null;
    }

    /**
     * Guard used by editVehicle/updateVehicle/deleteVehicle so the check is
     * enforced server-side even if the UI controls are bypassed.
     */
    protected function guardVehicleNotLocked(int $vehicle_id): bool
    {
        $reason = $this->vehicleLockReason($vehicle_id);

        if ($reason !== null) {
            Flux::toast(
                variant: 'danger',
                duration: 5000,
                heading: 'Vehicle is locked.',
                text: $reason,
            );

            return false;
        }

        return true;
    }

    #[Computed]
    public function getTerminal() {
        return RouteList::with('operatorTicketRate')
            ->when($this->create_vehicle_type, function($q) {
                $q->where('vehicle_type', $this->create_vehicle_type);
            })
            ->get();
    }

    public function addingVehicle($status) {
        $this->confirmingAddVehicle = $status;

        if ($status) {
            $this->reset([
                'create_vehicle_type',
                'create_route',
                'create_plate_number',
                'create_total_seats',
                'create_group_number',
                'create_engine_number',
                'create_body_number',
                'create_chassis_number',
                'create_driver_name',
                'create_has_franchise',
                'create_franchise_expiry_date',
            ]);
            $this->resetValidation();
        }
    }

    public function addNewVehicle() {
        // ---- FIX: ensure date fields are null when checkbox is false or value is empty ----
        if (!$this->create_has_franchise) {
            $this->create_franchise_expiry_date = null;
        } else {
            $this->create_franchise_expiry_date = $this->create_franchise_expiry_date ?: null;
        }
        // --------------------------------------------------------------------------------

        DB::transaction(function () {
            // Vehicles of no-account operators (old terminal records) may be incomplete.
            $strict = $this->user->hasAccount();

            $attributes = $this->validate([
                'create_vehicle_type' => 'required|string',
                'create_route'        => 'required|string',
                'create_plate_number' => 'required|string|unique:vehicles,plate_number',
                'create_total_seats'  => 'required|integer|min:10|max:50',
                'create_group_number' => 'required_if:create_vehicle_type,Bus,UV-express|integer|min:1|max:2',
                // ---- CHANGED: require engine/body/chassis, franchise etc. as in registration ----
                'create_engine_number'         => ($strict ? 'required' : 'nullable') . '|string|min:2',
                'create_body_number'           => ($strict ? 'required' : 'nullable') . '|string|min:2',
                'create_chassis_number'        => ($strict ? 'required' : 'nullable') . '|string|min:2',
                'create_driver_name'           => 'nullable|string|max:100',
                'create_has_franchise'         => $strict ? 'required|accepted' : 'boolean',
                'create_franchise_expiry_date' => $strict ? 'required|date|after:today' : 'nullable|date',
            ]);

            $route_list = RouteList::whereHas('operatorTicketRate', function ($query) use ($attributes) {
                $query->where('vehicle_type', $attributes['create_vehicle_type']);
            })
                ->where('terminal', $attributes['create_route'])
                ->first();

            $new_vehicle = $this->user->vehicles()->create([
                'route_list_id' => $route_list->id,
                'vehicle_type'  => $attributes['create_vehicle_type'],
                'plate_number'  => $attributes['create_plate_number'],
                'total_seats'   => $attributes['create_total_seats'],
                // new fields – use $attributes which now contain cleaned null values
                'engine_number'         => $attributes['create_engine_number'] ?: null,
                'body_number'           => $attributes['create_body_number'] ?: null,
                'chassis_number'        => $attributes['create_chassis_number'] ?: null,
                'driver_name'           => $attributes['create_driver_name'] ?: null,
                'has_franchise'         => (bool) ($attributes['create_has_franchise'] ?? false),
                'franchise_expiry_date' => $attributes['create_franchise_expiry_date'] ?? null,
            ]);

            if (in_array($attributes['create_vehicle_type'], ['Bus', 'UV-express']) && $this->create_group_number !== null) {
                $order_number = VehicleGroup::where('group_number', $this->create_group_number)
                    ->whereHas('vehicle', function($query) use ($new_vehicle) {

                    $query->where('vehicle_type', $new_vehicle->vehicle_type);

                })->max('order_number') + 1;

                $new_vehicle->vehicle_group()->create([
                    'group_number' => (int) $this->create_group_number,
                    'order_number' => $order_number,
                ]);
            }

            $this->editingVehicles[$new_vehicle->id] = [
                'vehicle_type' => $new_vehicle->vehicle_type,
                'total_seats'  => $new_vehicle->total_seats,
                'plate_number' => $new_vehicle->plate_number,
                'group_number' => $this->getVehicleGroupNumber($new_vehicle->id),
                'engine_number'         => $new_vehicle->engine_number ?? '',
                'body_number'           => $new_vehicle->body_number ?? '',
                'chassis_number'        => $new_vehicle->chassis_number ?? '',
                'has_franchise'         => (bool) $new_vehicle->has_franchise,
                'franchise_expiry_date' => $new_vehicle->franchise_expiry_date ? $new_vehicle->franchise_expiry_date->format('Y-m-d') : '',
            ];

            app(AuditLogsService::class)->create([
                'user_id'  => auth()->id(),
                'action'   => 'Vehicle Added',
                'subject'  => 'Admin added a vehicle to an operator\'s fleet',
                'channel'  => 'Web',
                'metadata' => [
                    'ip_address'   => request()->ip(),
                    'operator_id'  => $this->user->id,
                    'vehicle_id'   => $new_vehicle->id,
                    'plate_number' => $new_vehicle->plate_number,
                    'message'      => "Added vehicle (Plate: {$new_vehicle->plate_number}) to operator {$this->user->name} (User No.: {$this->user->user_code}).",
                ],
            ]);
        });

        $this->reset([
            'create_vehicle_type',
            'create_route',
            'create_plate_number',
            'create_total_seats',
            'create_group_number',
            'create_engine_number',
            'create_body_number',
            'create_chassis_number',
            'create_driver_name',
            'create_has_franchise',
            'create_franchise_expiry_date',
        ]);

        unset($this->getVehicle);
        $this->dispatch('user-updated'); // refresh the vehicle count in the page header
        $this->addingVehicle(false);

        Flux::toast(variant: 'success', duration: 4000, heading: 'Vehicle added.', text: 'New vehicle has been added.');
    }

    public function editVehicle(int $vehicle_id) {
        if (!$this->guardVehicleNotLocked($vehicle_id)) {
            $this->confirmingEditVehicle = null;
            return;
        }

        $vehicle = Vehicle::find($vehicle_id);

        if ($vehicle) {
            $this->editingVehicles[$vehicle->id] = [
                'vehicle_type' => $vehicle->vehicle_type,
                'total_seats'  => $vehicle->total_seats,
                'plate_number' => $vehicle->plate_number,
                'group_number' => $this->getVehicleGroupNumber($vehicle->id),
                'engine_number'         => $vehicle->engine_number ?? '',
                'body_number'           => $vehicle->body_number ?? '',
                'chassis_number'        => $vehicle->chassis_number ?? '',
                'driver_name'           => $vehicle->driver_name ?? '',
                'has_franchise'         => (bool) $vehicle->has_franchise,
                'franchise_expiry_date' => $vehicle->franchise_expiry_date ? $vehicle->franchise_expiry_date->format('Y-m-d') : '',
            ];
        }

        $this->resetValidation();
        $this->confirmingEditVehicle = $vehicle_id;
    }

    public function cancelEditVehicle() {
        $vehicle = Vehicle::find($this->confirmingEditVehicle);

        if ($vehicle) {
            $this->editingVehicles[$vehicle->id] = [
                'vehicle_type' => $vehicle->vehicle_type,
                'total_seats'  => $vehicle->total_seats,
                'plate_number' => $vehicle->plate_number,
                'group_number' => $this->getVehicleGroupNumber($vehicle->id),
                'engine_number'         => $vehicle->engine_number ?? '',
                'body_number'           => $vehicle->body_number ?? '',
                'chassis_number'        => $vehicle->chassis_number ?? '',
                'driver_name'           => $vehicle->driver_name ?? '',
                'has_franchise'         => (bool) $vehicle->has_franchise,
                'franchise_expiry_date' => $vehicle->franchise_expiry_date ? $vehicle->franchise_expiry_date->format('Y-m-d') : '',
            ];
        }

        $this->resetValidation();
        $this->confirmingEditVehicle = null;
    }

    public function updateVehicle(int $vehicle_id) {
        if (!$this->guardVehicleNotLocked($vehicle_id)) {
            $this->confirmingEditVehicle = null;
            return;
        }

        $vehicle = Vehicle::where('id', $vehicle_id)
            ->where('user_id', $this->user->id)
            ->firstOrFail();

        // ---- FIX: ensure date fields are null when checkbox is false or value is empty ----
        $vehicleData = &$this->editingVehicles[$vehicle_id];
        if (!$vehicleData['has_franchise']) {
            $vehicleData['franchise_expiry_date'] = null;
        } else {
            $vehicleData['franchise_expiry_date'] = $vehicleData['franchise_expiry_date'] ?: null;
        }
        // --------------------------------------------------------------------------------

        // Vehicles of no-account operators (old terminal records) may be incomplete.
        $strict = $this->user->hasAccount();

        $rules = [
            "editingVehicles.{$vehicle_id}.plate_number" => "required|string|unique:vehicles,plate_number,{$vehicle_id}",
            "editingVehicles.{$vehicle_id}.total_seats"  => 'required|integer|min:10|max:50',
            // ---- CHANGED: require engine/body/chassis, franchise etc. as in registration ----
            "editingVehicles.{$vehicle_id}.engine_number"         => ($strict ? 'required' : 'nullable') . '|string|min:2',
            "editingVehicles.{$vehicle_id}.body_number"           => ($strict ? 'required' : 'nullable') . '|string|min:2',
            "editingVehicles.{$vehicle_id}.chassis_number"        => ($strict ? 'required' : 'nullable') . '|string|min:2',
            "editingVehicles.{$vehicle_id}.driver_name"           => 'nullable|string|max:100',
            "editingVehicles.{$vehicle_id}.has_franchise"         => $strict ? 'required|accepted' : 'boolean',
            "editingVehicles.{$vehicle_id}.franchise_expiry_date" => $strict ? 'required|date|after:today' : 'nullable|date',
        ];

        if (in_array($vehicle->vehicle_type, ['Bus', 'UV-express'])) {
            $rules["editingVehicles.{$vehicle_id}.group_number"] = 'required|integer|min:1|max:2';
        }

        $data = $this->validate($rules);

        $vehicle->update([
            'plate_number' => $data['editingVehicles'][$vehicle_id]['plate_number'],
            'total_seats'  => $data['editingVehicles'][$vehicle_id]['total_seats'],
            'engine_number'         => $data['editingVehicles'][$vehicle_id]['engine_number'] ?: null,
            'body_number'           => $data['editingVehicles'][$vehicle_id]['body_number'] ?: null,
            'chassis_number'        => $data['editingVehicles'][$vehicle_id]['chassis_number'] ?: null,
            'driver_name'           => $data['editingVehicles'][$vehicle_id]['driver_name'] ?: null,
            'has_franchise'         => (bool) ($data['editingVehicles'][$vehicle_id]['has_franchise'] ?? false),
            'franchise_expiry_date' => $data['editingVehicles'][$vehicle_id]['franchise_expiry_date'] ?? null,
        ]);

        if (in_array($vehicle->vehicle_type, ['Bus', 'UV-express'])) {
            VehicleGroup::where('vehicle_id', $vehicle->id)
                ->update([
                    'group_number' => $data['editingVehicles'][$vehicle_id]['group_number'],
                ]);
        }

        $this->confirmingEditVehicle = null;
        unset($this->getVehicle);

        $this->dispatch('vehicle-updated', id: $vehicle_id);

        app(AuditLogsService::class)->create([
            'user_id'  => auth()->id(),
            'action'   => 'Vehicle Updated',
            'subject'  => 'Admin updated a vehicle in an operator\'s fleet',
            'channel'  => 'Web',
            'metadata' => [
                'ip_address'   => request()->ip(),
                'operator_id'  => $this->user->id,
                'vehicle_id'   => $vehicle->id,
                'plate_number' => $vehicle->plate_number,
                'message'      => "Updated vehicle (Plate: {$vehicle->plate_number}) belonging to operator {$this->user->name} (User No.: {$this->user->user_code}).",
            ],
        ]);

        Flux::toast(variant: 'success', duration: 4000, heading: 'Vehicle updated.', text: 'Vehicle information has been updated.');
    }

    public function deleteVehicle(int $vehicle_id) {
        if (!$this->guardVehicleNotLocked($vehicle_id)) {
            return;
        }

        $vehicle = Vehicle::where('id', $vehicle_id)
            ->where('user_id', $this->user->id)
            ->first();

        Vehicle::where('id', $vehicle_id)
            ->where('user_id', $this->user->id)
            ->delete();

        app(AuditLogsService::class)->create([
            'user_id'  => auth()->id(),
            'action'   => 'Vehicle Deleted',
            'subject'  => 'Admin deleted a vehicle from an operator\'s fleet',
            'channel'  => 'Web',
            'metadata' => [
                'ip_address'   => request()->ip(),
                'operator_id'  => $this->user->id,
                'vehicle_id'   => $vehicle_id,
                'plate_number' => $vehicle?->plate_number,
                'message'      => "Deleted vehicle (Plate: " . ($vehicle?->plate_number ?? "ID {$vehicle_id}") . ") belonging to operator {$this->user->name} (User No.: {$this->user->user_code}).",
            ],
        ]);

        unset($this->editingVehicles[$vehicle_id]);
        unset($this->getVehicle);
        $this->dispatch('user-updated'); // refresh the vehicle count in the page header

        $this->dispatch('vehicle-deleted', id: $vehicle_id);

        Flux::toast(variant: 'success', duration: 4000, heading: 'Vehicle deleted.', text: 'Vehicle has been deleted.');
    }

    public function updatedCreateVehicleType($value)
    {
        $routes = RouteList::whereHas('operatorTicketRate', function($q) use($value) {
            $q->where('vehicle_type', $value);
        })->first();

        $this->create_route = $routes ? $routes->terminal : '';
        $this->create_group_number = '';
    }
};
?>

<div>
        <div class="flex flex-col sm:flex-row sm:items-center gap-3 mt-8 mb-4">
            <div class="flex-1">
                <x-text class="text-sm lg:text-section-heading font-medium text-light-txt-primary dark:text-dark-txt-primary font-secondary">Vehicle information</x-text>
                <x-text class="text-xs lg:text-sm text-light-txt-muted dark:text-dark-txt-muted font-secondary">Manage vehicles assigned to this operator.</x-text>
            </div>
            @if (!$confirmingAddVehicle)
                <flux:button variant="primary" size="sm" icon="plus" class="w-full sm:w-auto"
                    wire:click="addingVehicle(true)" wire:loading.attr="disabled">
                    Add vehicle
                </flux:button>
            @else
                <flux:button variant="ghost" size="sm" class="w-full sm:w-auto"
                    wire:click="addingVehicle(false)" wire:loading.attr="disabled">
                    Cancel
                </flux:button>
            @endif
        </div>

        @if ($confirmingAddVehicle)
            <div class="w-full border border-light-bd-default dark:border-dark-bd-default rounded-xl bg-light-secondary dark:bg-dark-secondary overflow-hidden mb-4">
                <div class="flex items-center gap-2 px-4 sm:px-6 py-3 border-b border-light-bd-default dark:border-dark-bd-default bg-light-subtle dark:bg-dark-secondary">
                    <flux:icon.plus class="w-4 h-4 text-light-txt-muted dark:text-dark-txt-muted shrink-0" />
                    <span class="text-sm font-medium text-light-txt-body dark:text-dark-txt-body">New vehicle</span>
                </div>
                <div class="p-4 sm:p-6">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6 mb-4">
                        <div>
                            <flux:label class="mb-2 font-secondary text-md text-light-txt-primary dark:text-dark-txt-muted">Vehicle type</flux:label>
                            <flux:select wire:model.live="create_vehicle_type" placeholder="Vehicle type">
                                @forelse ($this->getVehicleTypeOptions as $vehicle)
                                    <flux:select.option value="{{ $vehicle->vehicle_type }}">{{ $vehicle->vehicle_type }}</flux:select.option>
                                @empty
                                    <flux:select.option value="">Record not found</flux:select.option>
                                @endforelse
                            </flux:select>
                            <flux:error name="create_vehicle_type" />
                        </div>

                        <div>
                            <flux:label class="mb-2 font-secondary text-md text-light-txt-primary dark:text-dark-txt-muted">Route</flux:label>
                            <flux:select wire:model.live="create_route" placeholder="Select route">
                                @foreach ($this->getRoute as $route)
                                    @if ($route->operatorTicketRate->vehicle_type === $this->create_vehicle_type)
                                        <flux:select.option value="{{ $route->terminal }}">
                                            Iriga Terminal to <strong>{{ $route->terminal }}</strong>
                                        </flux:select.option>
                                    @endif
                                @endforeach
                            </flux:select>
                            <flux:error name="create_route" />
                        </div>

                        <div>
                            <flux:label class="mb-2 font-secondary text-md text-light-txt-primary dark:text-dark-txt-muted">Plate number</flux:label>
                            <flux:input wire:model="create_plate_number" />
                            <flux:error name="create_plate_number" />
                        </div>

                        <div>
                            <flux:label class="mb-2 font-secondary text-md text-light-txt-primary dark:text-dark-txt-muted">Total seats</flux:label>
                            <flux:input wire:model="create_total_seats" type="number" min="10" max="50" />
                            <flux:error name="create_total_seats" />
                        </div>

                        <div>
                            <flux:label class="mb-2 font-secondary text-md text-light-txt-primary dark:text-dark-txt-muted">Group No.</flux:label>
                            @if ($this->create_vehicle_type === 'Bus' || $this->create_vehicle_type === 'UV-express')
                                <flux:select wire:model.live="create_group_number" placeholder="Group No.">
                                    <flux:select.option value="1">1</flux:select.option>
                                    <flux:select.option value="2">2</flux:select.option>
                                </flux:select>
                            @else
                                <flux:select placeholder="Group No." disabled>
                                    <flux:select.option value="1">1</flux:select.option>
                                    <flux:select.option value="2">2</flux:select.option>
                                </flux:select>
                            @endif
                            <flux:error name="create_group_number" />
                        </div>

                        {{-- NEW: Engine / Body / Chassis numbers --}}
                        <div>
                            <flux:label class="mb-2 font-secondary text-md text-light-txt-primary dark:text-dark-txt-muted">Engine number</flux:label>
                            <flux:input wire:model="create_engine_number" placeholder="e.g. EN-12345" />
                            <flux:error name="create_engine_number" />
                        </div>

                        <div>
                            <flux:label class="mb-2 font-secondary text-md text-light-txt-primary dark:text-dark-txt-muted">Body number</flux:label>
                            <flux:input wire:model="create_body_number" placeholder="e.g. BD-12345" />
                            <flux:error name="create_body_number" />
                        </div>

                        <div>
                            <flux:label class="mb-2 font-secondary text-md text-light-txt-primary dark:text-dark-txt-muted">Chassis number</flux:label>
                            <flux:input wire:model="create_chassis_number" placeholder="e.g. CH-123456789" />
                            <flux:error name="create_chassis_number" />
                        </div>

                        <div>
                            <flux:label class="mb-2 font-secondary text-md text-light-txt-primary dark:text-dark-txt-muted">
                                Driver name
                                <span class="font-normal text-light-txt-muted dark:text-dark-txt-muted">(optional — default, editable per queue)</span>
                            </flux:label>
                            <flux:input wire:model="create_driver_name" placeholder="e.g. Juan Dela Cruz" />
                            <flux:error name="create_driver_name" />
                        </div>

                        {{-- NEW: Compliance documents (span full width) --}}
                        <div class="sm:col-span-2 border-t border-light-bd-default dark:border-dark-bd-default pt-3 space-y-3">
                            <p class="font-secondary text-xs font-medium uppercase tracking-wide text-light-txt-muted dark:text-dark-txt-muted">Compliance documents</p>

                            <div class="flex items-start gap-3">
                                <flux:checkbox wire:model.live="create_has_franchise" />
                                <div class="flex-1 min-w-0 space-y-1">
                                    <flux:label class="font-secondary text-sm text-light-txt-body dark:text-dark-txt-primary">
                                        Franchise verified
                                        <span class="ml-1 font-normal text-light-txt-muted dark:text-dark-txt-muted">(admin confirms document was seen)</span>
                                    </flux:label>
                                    <div class="flex items-center gap-2">
                                        <span class="font-secondary text-xs text-light-txt-muted dark:text-dark-txt-muted whitespace-nowrap">Validity Date of Franchise:</span>
                                        <flux:input
                                            type="date"
                                            wire:model="create_franchise_expiry_date"
                                            :disabled="!$create_has_franchise"
                                            size="sm"
                                            class="flex-1"
                                            wire:key="franchise-{{ $create_has_franchise }}"
                                        />
                                    </div>
                                    <flux:error name="create_franchise_expiry_date" />
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="flex justify-end">
                        <flux:button type="button" size="sm" variant="primary" class="w-full sm:w-auto"
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
            @php
                $groupNumber = $this->getVehicleGroupNumber($vehicle->id);
                $lockReason  = $this->vehicleLockReason($vehicle->id);
                $isLocked    = $lockReason !== null;
                $isQueued    = $this->isVehicleQueued($vehicle->id);
            @endphp

            <flux:card class="mb-3 sm:mb-4 !p-3 sm:!p-4" wire:key="vehicle-container-{{ $vehicle->id }}">
                <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2 sm:gap-3 min-w-0">
                        <flux:badge size="sm" class="shrink-0 bg-primary">{{ $index + 1 }}</flux:badge>
                        <div class="w-8 h-8 sm:w-9 sm:h-9 rounded-lg bg-secondary dark:bg-secondary flex items-center justify-center shrink-0">
                            <flux:icon.truck class="w-4 h-4 text-white text-primary dark:text-dark-txt-primary" />
                        </div>
                        <div class="min-w-0">
                            <p class="font-primary text-sm font-bold text-light-txt-body dark:text-dark-txt-body truncate flex items-center gap-1.5">
                                {{ $vehicle->plate_number }}
                                <span class="font-normal font-secondary text-light-txt-muted dark:text-dark-txt-muted">· {{ $vehicle->vehicle_type }}</span>
                                @php($docStatus = $vehicle->documentStatus())
                                @if ($docStatus === 'expired')
                                    <flux:badge color="red" size="sm" class="font-secondary text-badge text-xs">Docs Expired</flux:badge>
                                @elseif ($docStatus === 'expiring')
                                    <flux:badge color="orange" size="sm" class="font-secondary text-badge text-xs">Docs Expiring</flux:badge>
                                @endif
                                @if ($isQueued)
                                    <flux:badge color="blue" size="sm" icon="clock" class="font-secondary text-badge text-xs">In Queue</flux:badge>
                                @elseif ($isLocked)
                                    <flux:badge color="purple" size="sm" icon="megaphone" class="font-secondary text-badge text-xs">Posted</flux:badge>
                                @endif
                            </p>
                            <p class="font-secondary text-xs text-light-txt-muted dark:text-dark-txt-muted truncate">
                                {{ $vehicle->total_seats }} seats
                                @if(in_array($vehicle->vehicle_type, ['Bus', 'UV-express']) && $groupNumber) · Group {{ $groupNumber }} @endif
                                · Registered {{ $vehicle->created_at->format('Y-m-d') }}
                            </p>
                            <p class="font-secondary text-xs text-light-txt-muted dark:text-dark-txt-muted truncate">
                                Engine: {{ $vehicle->engine_number ?? '—' }} · Body: {{ $vehicle->body_number ?? '—' }} · Chassis: {{ $vehicle->chassis_number ?? '—' }}
                            </p>
                            @if ($isLocked)
                                <p class="font-secondary text-xs text-warning dark:text-dark-warning truncate" title="{{ $lockReason }}">
                                    {{ $lockReason }}
                                </p>
                            @endif
                        </div>
                    </div>

                    <div class="flex items-center gap-1 shrink-0">
                        @if ($isLocked)
                            <flux:button type="button" variant="ghost" size="sm" icon="pencil" disabled title="{{ $lockReason }}" />
                            <flux:button type="button" variant="ghost" size="sm" icon="trash" disabled title="{{ $lockReason }}" />
                        @else
                            <flux:modal.trigger name="edit-vehicle-{{ $vehicle->id }}">
                                <flux:button type="button" variant="ghost" size="sm" icon="pencil"
                                    wire:click="editVehicle({{ $vehicle->id }})" />
                            </flux:modal.trigger>
                            <flux:modal.trigger name="delete-vehicle-{{ $vehicle->id }}">
                                <flux:button type="button" variant="ghost" size="sm" icon="trash"
                                    class="text-danger/70 dark:text-dark-danger/70 hover:text-danger dark:hover:text-dark-danger hover:bg-danger/10 dark:hover:bg-dark-danger/10"/>
                            </flux:modal.trigger>
                        @endif
                    </div>
                </div>
            </flux:card>

            {{-- Delete-vehicle confirmation modal (only reachable when not locked) --}}
            @unless ($isLocked)
                <flux:modal name="delete-vehicle-{{ $vehicle->id }}" class="md:w-96"
                    x-on:vehicle-deleted.window="if ($event.detail.id === {{ $vehicle->id }}) $flux.modal('delete-vehicle-{{ $vehicle->id }}').close()">
                    <div class="space-y-4">
                        <div>
                            <flux:heading size="lg" class="!font-primary !font-bold text-light-txt-primary dark:text-dark-txt-primary">
                                Delete this vehicle?
                            </flux:heading>
                            <flux:text class="mt-1 font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">
                                <strong>{{ $vehicle->vehicle_type }}</strong> with plate
                                <strong>{{ $vehicle->plate_number }}</strong> will be permanently removed. This cannot be undone.
                            </flux:text>
                        </div>

                        <div class="flex flex-col sm:flex-row justify-end gap-2 pt-2 border-t border-light-bd-default dark:border-dark-bd-default">
                            <flux:modal.close>
                                <flux:button type="button" variant="ghost" class="font-secondary w-full sm:w-auto">
                                    Cancel
                                </flux:button>
                            </flux:modal.close>
                            <flux:button
                                type="button"
                                variant="danger"
                                icon="trash"
                                wire:click="deleteVehicle({{ $vehicle->id }})"
                                wire:loading.attr="disabled"
                                wire:target="deleteVehicle({{ $vehicle->id }})"
                                class="font-secondary w-full sm:w-auto"
                            >
                                Yes, delete
                            </flux:button>
                        </div>
                    </div>
                </flux:modal>
            @endunless

            {{-- Edit-vehicle modal (UPDATED with wire:key on date inputs) — only reachable when not locked --}}
            @unless ($isLocked)
            <flux:modal name="edit-vehicle-{{ $vehicle->id }}" class="md:w-[28rem]"
                x-on:vehicle-updated.window="if ($event.detail.id === {{ $vehicle->id }}) $flux.modal('edit-vehicle-{{ $vehicle->id }}').close()">
                <div class="space-y-4">
                    <div>
                        <flux:heading size="lg" class="!font-primary !font-bold text-light-txt-primary dark:text-dark-txt-primary">
                            Edit Vehicle {{ $index + 1 }}
                        </flux:heading>
                        <flux:text class="mt-1 font-secondary text-xs text-light-txt-muted dark:text-dark-txt-muted">
                            Update this vehicle's details.
                        </flux:text>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <flux:label class="mb-2 font-secondary text-md text-light-txt-primary dark:text-dark-txt-muted">Vehicle type</flux:label>
                            <flux:select wire:model="editingVehicles.{{ $vehicle->id }}.vehicle_type" disabled size="sm">
                                <option value="Bus"        @selected(($editingVehicles[$vehicle->id]['vehicle_type'] ?? null) === 'Bus')>Bus</option>
                                <option value="UV-express" @selected(($editingVehicles[$vehicle->id]['vehicle_type'] ?? null) === 'UV-express')>UV-express</option>
                                <option value="Multi-cab"  @selected(($editingVehicles[$vehicle->id]['vehicle_type'] ?? null) === 'Multi-cab')>Multi-cab</option>
                                <option value="Jeep"       @selected(($editingVehicles[$vehicle->id]['vehicle_type'] ?? null) === 'Jeep')>Jeep</option>
                            </flux:select>
                        </div>

                        @if (in_array($vehicle->vehicle_type, ['Bus', 'UV-express']))
                            <div>
                                <flux:label class="mb-2 font-secondary text-md text-light-txt-primary dark:text-dark-txt-muted">Group number</flux:label>
                                <flux:select wire:model="editingVehicles.{{ $vehicle->id }}.group_number" size="sm">
                                    <flux:select.option value="1">1</flux:select.option>
                                    <flux:select.option value="2">2</flux:select.option>
                                </flux:select>
                                <flux:error name="editingVehicles.{{ $vehicle->id }}.group_number" />
                            </div>
                        @endif

                        <div>
                            <flux:label class="mb-2 font-secondary text-md text-light-txt-primary dark:text-dark-txt-muted">Plate number</flux:label>
                            <flux:input wire:model="editingVehicles.{{ $vehicle->id }}.plate_number" size="sm" />
                            <flux:error name="editingVehicles.{{ $vehicle->id }}.plate_number" />
                        </div>

                        <div>
                            <flux:label class="mb-2 font-secondary text-md text-light-txt-primary dark:text-dark-txt-muted">Total seats</flux:label>
                            <flux:input wire:model="editingVehicles.{{ $vehicle->id }}.total_seats" type="number" min="10" max="50" size="sm" />
                            <flux:error name="editingVehicles.{{ $vehicle->id }}.total_seats" />
                        </div>

                        <div>
                            <flux:label class="mb-2 font-secondary text-md text-light-txt-primary dark:text-dark-txt-muted">Date registered</flux:label>
                            <flux:input value="{{ $vehicle->created_at->format('Y-m-d') }}" disabled size="sm" />
                        </div>

                        <div>
                            <flux:label class="mb-2 font-secondary text-md text-light-txt-primary dark:text-dark-txt-muted">Engine number</flux:label>
                            <flux:input wire:model="editingVehicles.{{ $vehicle->id }}.engine_number" placeholder="e.g. EN-12345" size="sm" />
                            <flux:error name="editingVehicles.{{ $vehicle->id }}.engine_number" />
                        </div>

                        <div>
                            <flux:label class="mb-2 font-secondary text-md text-light-txt-primary dark:text-dark-txt-muted">Body number</flux:label>
                            <flux:input wire:model="editingVehicles.{{ $vehicle->id }}.body_number" placeholder="e.g. BD-12345" size="sm" />
                            <flux:error name="editingVehicles.{{ $vehicle->id }}.body_number" />
                        </div>

                        <div>
                            <flux:label class="mb-2 font-secondary text-md text-light-txt-primary dark:text-dark-txt-muted">Chassis number</flux:label>
                            <flux:input wire:model="editingVehicles.{{ $vehicle->id }}.chassis_number" placeholder="e.g. CH-123456789" size="sm" />
                            <flux:error name="editingVehicles.{{ $vehicle->id }}.chassis_number" />
                        </div>

                        <div>
                            <flux:label class="mb-2 font-secondary text-md text-light-txt-primary dark:text-dark-txt-muted">
                                Driver name
                                <span class="font-normal text-light-txt-muted dark:text-dark-txt-muted">(optional — default, editable per queue)</span>
                            </flux:label>
                            <flux:input wire:model="editingVehicles.{{ $vehicle->id }}.driver_name" placeholder="e.g. Juan Dela Cruz" size="sm" />
                            <flux:error name="editingVehicles.{{ $vehicle->id }}.driver_name" />
                        </div>

                        <div class="sm:col-span-2 border-t border-light-bd-default dark:border-dark-bd-default pt-3 space-y-3">
                            <p class="font-secondary text-xs font-medium uppercase tracking-wide text-light-txt-muted dark:text-dark-txt-muted">Compliance documents</p>

                            <div class="flex items-start gap-3">
                                <flux:checkbox wire:model.live="editingVehicles.{{ $vehicle->id }}.has_franchise" />
                                <div class="flex-1 min-w-0 space-y-1">
                                    <flux:label class="font-secondary text-sm text-light-txt-body dark:text-dark-txt-primary">
                                        Franchise verified
                                        <span class="ml-1 font-normal text-light-txt-muted dark:text-dark-txt-muted">(admin confirms document was seen)</span>
                                    </flux:label>
                                    <div class="flex items-center gap-2">
                                        <span class="font-secondary text-xs text-light-txt-muted dark:text-dark-txt-muted whitespace-nowrap">Validity Date of Franchise:</span>
                                        <flux:input
                                            type="date"
                                            wire:model="editingVehicles.{{ $vehicle->id }}.franchise_expiry_date"
                                            :disabled="!$this->editingVehicles[$vehicle->id]['has_franchise']"
                                            size="sm"
                                            class="flex-1"
                                            wire:key="edit-franchise-{{ $vehicle->id }}-{{ $this->editingVehicles[$vehicle->id]['has_franchise'] }}"
                                        />
                                    </div>
                                    <flux:error name="editingVehicles.{{ $vehicle->id }}.franchise_expiry_date" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col sm:flex-row justify-end gap-2 pt-2 border-t border-light-bd-default dark:border-dark-bd-default">
                        <flux:modal.close>
                            <flux:button type="button" variant="ghost" wire:click="cancelEditVehicle" class="font-secondary w-full sm:w-auto">
                                Cancel
                            </flux:button>
                        </flux:modal.close>
                        <flux:button type="button" variant="primary" icon="check"
                            wire:click="updateVehicle({{ $vehicle->id }})"
                            wire:loading.attr="disabled"
                            wire:target="updateVehicle({{ $vehicle->id }})"
                            class="font-secondary w-full sm:w-auto">
                            Save
                        </flux:button>
                    </div>
                </div>
            </flux:modal>
            @endunless
        @endforeach
</div>
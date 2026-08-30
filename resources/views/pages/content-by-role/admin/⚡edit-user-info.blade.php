<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;

use App\Models\User;
use App\Models\Card;
use App\Models\Terminal;
use App\Models\Vehicle;
use App\Models\Route;
use App\Models\RouteList;
use App\Models\VehicleGroup;
use App\Models\OperatorTicketRate;

use App\Services\UserService;
use App\Services\AuditLogsService;

new #[Layout('layouts.admin-layout')] class extends Component
{
    public User $user;

    public $name;
    public $email_address;
    public $phone_number;
    public $address;

    public $confirmingAddVehicle = null;
    public array $editingVehicles = [];

    // Address modal fields
    public string $house_subd = '';
    public ?int $zone_number = null;
    public string $municipality = '';
    public string $barangay = '';
    public string $province = '';      // <-- NEW

    public $create_vehicle_type = '';
    public $create_route        = '';
    public $create_plate_number = '';
    public $create_total_seats  = '';
    public $create_group_number = '';
    // NEW fields for adding a vehicle
    public $create_driver_name = '';
    public $create_has_or_cr = false;
    public $create_or_cr_expiry_date = '';
    public $create_has_franchise = false;
    public $create_franchise_expiry_date = '';

    public ?int $confirmingEditVehicle = null;

    public $route_list_id;
    public $vehicles;

    public bool $showCardPanel = false;
    public string $cardUid = '';

    /**
     * Human‑readable names for validation attributes.
     * Matches the approach used in the registration form.
     */
    protected $validationAttributes = [
        // Personal info
        'name'          => 'name',
        'email_address' => 'email address',
        'phone_number'  => 'phone number',
        'address'       => 'address',

        // Card issuance
        'cardUid'       => 'card UID',

        // Add‑vehicle fields
        'create_vehicle_type'          => 'vehicle type',
        'create_route'                 => 'route',
        'create_plate_number'          => 'plate number',
        'create_total_seats'           => 'total seats',
        'create_group_number'          => 'group number',
        'create_driver_name'           => 'driver name',
        'create_has_or_cr'             => 'OR/CR verification',
        'create_or_cr_expiry_date'     => 'OR/CR expiry date',
        'create_has_franchise'         => 'franchise verification',
        'create_franchise_expiry_date' => 'franchise expiry date',

        // Edit‑vehicle fields (array notation)
        'editingVehicles.*.plate_number'          => 'plate number',
        'editingVehicles.*.total_seats'           => 'total seats',
        'editingVehicles.*.group_number'          => 'group number',
        'editingVehicles.*.driver_name'           => 'driver name',
        'editingVehicles.*.has_or_cr'             => 'OR/CR verification',
        'editingVehicles.*.or_cr_expiry_date'     => 'OR/CR expiry date',
        'editingVehicles.*.has_franchise'         => 'franchise verification',
        'editingVehicles.*.franchise_expiry_date' => 'franchise expiry date',
    ];

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

    #[Computed]
    public function getTerminal() {
        return RouteList::with('operatorTicketRate')
            ->when($this->create_vehicle_type, function($q) {
                $q->where('vehicle_type', $this->create_vehicle_type);
            })
            ->get();
    }

    public function mount() {
        $this->name          = $this->user->name;
        $this->email_address = $this->user->email_address ?? '';
        $this->phone_number  = $this->user->phone_number ?? '';
        $this->address       = $this->user->address;

        foreach ($this->getVehicle as $vehicle) {
            $this->editingVehicles[$vehicle->id] = [
                'vehicle_type'          => $vehicle->vehicle_type,
                'total_seats'           => $vehicle->total_seats,
                'plate_number'          => $vehicle->plate_number,
                'group_number'          => $this->getVehicleGroupNumber($vehicle->id),
                'driver_name'           => $vehicle->driver_name ?? '',
                'has_or_cr'             => (bool) $vehicle->has_or_cr,
                'or_cr_expiry_date'     => $vehicle->or_cr_expiry_date ? $vehicle->or_cr_expiry_date->format('Y-m-d') : '',
                'has_franchise'         => (bool) $vehicle->has_franchise,
                'franchise_expiry_date' => $vehicle->franchise_expiry_date ? $vehicle->franchise_expiry_date->format('Y-m-d') : '',
            ];
        }
    }

    /**
     * Parse the current address string and fill the modal fields.
     * Expected format: "House/Subd, Zone X, Barangay, Municipality, Province"
     */
    public function prepareAddressModal(): void
    {
        $parts = array_map('trim', explode(',', $this->address ?? ''));

        $this->house_subd   = '';
        $this->zone_number  = null;
        $this->barangay     = '';
        $this->municipality = '';
        $this->province     = '';

        if (count($parts) >= 5) {
            $this->province     = $parts[4] ?? '';
            $this->municipality = $parts[3] ?? '';
            $this->barangay     = $parts[2] ?? '';
            $zonePart = $parts[1] ?? '';
            if (preg_match('/Zone\s+(\d+)/i', $zonePart, $matches)) {
                $this->zone_number = (int) $matches[1];
            }
            $this->house_subd   = $parts[0] ?? '';
        }
        // If address doesn't match the expected format, fields stay empty
    }

    public function saveAddress(): void
    {
        $data = $this->validate([
            'house_subd'   => 'nullable|string|max:255',
            'zone_number'  => 'required|integer|min:1|max:20',
            'municipality' => 'required|string|max:255',
            'barangay'     => 'required|string|max:255',
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

    public function issueCard(): void
    {
        if ($this->user->isDeleted()) {
            Flux::toast(
                variant: 'danger',
                duration: 4000,
                heading: 'Cannot edit this account.',
                text: 'This account was permanently deleted by the user and can no longer be modified.',
            );

            return;
        }

        $this->validate([
            'cardUid' => 'required|string|min:4|unique:cards,uid',
        ], [
            'cardUid.unique' => 'This card UID is already assigned to another user.',
            'cardUid.min'    => 'The scanned UID looks too short — please scan again.',
        ]);

        Card::create([
            'user_id' => $this->user->id,
            'uid'     => $this->cardUid,
            'balance' => 0,
        ]);

        $this->showCardPanel = false;
        $this->cardUid = '';

        $this->user->refresh();

        Flux::toast(
            variant: 'success',
            duration: 4000,
            heading: 'Card issued.',
            text: 'RFID card has been linked to ' . $this->user->name . '.',
        );
    }

    public function save() {
        if ($this->user->isDeleted()) {
            Flux::toast(
                variant: 'danger',
                duration: 4000,
                heading: 'Cannot edit this account.',
                text: 'This account was permanently deleted by the user and can no longer be modified.',
            );

            return;
        }

        $attributes = $this->validate([
            'name'          => 'required|min:2|string',
            'email_address' => 'nullable|string|email|max:255|unique:users,email_address,' . $this->user->id,
            'phone_number'  => 'required|string|regex:/^09\d{9}$/',
            'address'       => 'required|min:1|string',
        ], [
            'phone_number.regex' => 'Enter a valid mobile number (e.g. 09171234567).',
        ]);

        app(UserService::class)->update($this->user, $attributes);

        Flux::toast(
            variant: 'success',
            duration: 4000,
            heading: 'Changes saved.',
            text: 'Your changes have been saved.'
        );
    }

    public string $suspension_reason_input = '';

    public function suspendUser() {
        if ($this->user->isDeleted()) {
            Flux::toast(
                variant: 'danger',
                duration: 4000,
                heading: 'Cannot edit this account.',
                text: 'This account was permanently deleted by the user and can no longer be modified.',
            );

            return;
        }

        $this->validate([
            'suspension_reason_input' => 'required|string|min:5|max:500',
        ], [], [
            'suspension_reason_input' => 'suspension reason',
        ]);

        app(UserService::class)->suspend($this->user, $this->suspension_reason_input);

        $this->user->refresh();
        $this->suspension_reason_input = '';
        $this->modal('suspend-user')->close();

        Flux::toast(
            variant: 'success',
            duration: 4000,
            heading: 'User suspended',
            text: "{$this->user->name} has been suspended and signed out.",
        );
    }

    public function reinstateUser() {
        if ($this->user->isDeleted()) {
            Flux::toast(
                variant: 'danger',
                duration: 4000,
                heading: 'Cannot edit this account.',
                text: 'This account was permanently deleted by the user and can no longer be modified.',
            );

            return;
        }

        app(UserService::class)->reinstate($this->user);

        $this->user->refresh();
        $this->modal('reinstate-user')->close();

        Flux::toast(
            variant: 'success',
            duration: 4000,
            heading: 'User reinstated',
            text: "{$this->user->name}'s account has been reactivated.",
        );
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
                'create_driver_name',
                'create_has_or_cr',
                'create_or_cr_expiry_date',
                'create_has_franchise',
                'create_franchise_expiry_date',
            ]);
            $this->resetValidation();
        }
    }

    public function addNewVehicle() {
        // ---- FIX: ensure date fields are null when checkbox is false or value is empty ----
        if (!$this->create_has_or_cr) {
            $this->create_or_cr_expiry_date = null;
        } else {
            $this->create_or_cr_expiry_date = $this->create_or_cr_expiry_date ?: null;
        }

        if (!$this->create_has_franchise) {
            $this->create_franchise_expiry_date = null;
        } else {
            $this->create_franchise_expiry_date = $this->create_franchise_expiry_date ?: null;
        }
        // --------------------------------------------------------------------------------

        DB::transaction(function () {
            $attributes = $this->validate([
                'create_vehicle_type' => 'required|string',
                'create_route'        => 'required|string',
                'create_plate_number' => 'required|string|unique:vehicles,plate_number',
                'create_total_seats'  => 'required|integer|min:10|max:50',
                'create_group_number' => 'required_if:create_vehicle_type,Bus,UV-express|integer|min:1|max:2',
                // ---- CHANGED: require driver_name, OR/CR, franchise etc. as in registration ----
                'create_driver_name'           => 'required|string|min:2',
                'create_has_or_cr'             => 'required|accepted',
                'create_or_cr_expiry_date'     => 'required|date|after:today',
                'create_has_franchise'         => 'required|accepted',
                'create_franchise_expiry_date' => 'required|date|after:today',
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
                'driver_name'           => $attributes['create_driver_name'] ?? '',
                'has_or_cr'             => (bool) ($attributes['create_has_or_cr'] ?? false),
                'or_cr_expiry_date'     => $attributes['create_or_cr_expiry_date'] ?? null,
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
                'driver_name'           => $new_vehicle->driver_name ?? '',
                'has_or_cr'             => (bool) $new_vehicle->has_or_cr,
                'or_cr_expiry_date'     => $new_vehicle->or_cr_expiry_date ? $new_vehicle->or_cr_expiry_date->format('Y-m-d') : '',
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
            'create_driver_name',
            'create_has_or_cr',
            'create_or_cr_expiry_date',
            'create_has_franchise',
            'create_franchise_expiry_date',
        ]);

        unset($this->getVehicle);
        $this->addingVehicle(false);

        Flux::toast(variant: 'success', duration: 4000, heading: 'Vehicle added.', text: 'New vehicle has been added.');
    }

    public function editVehicle(int $vehicle_id) {
        $vehicle = Vehicle::find($vehicle_id);

        if ($vehicle) {
            $this->editingVehicles[$vehicle->id] = [
                'vehicle_type' => $vehicle->vehicle_type,
                'total_seats'  => $vehicle->total_seats,
                'plate_number' => $vehicle->plate_number,
                'group_number' => $this->getVehicleGroupNumber($vehicle->id),
                'driver_name'           => $vehicle->driver_name ?? '',
                'has_or_cr'             => (bool) $vehicle->has_or_cr,
                'or_cr_expiry_date'     => $vehicle->or_cr_expiry_date ? $vehicle->or_cr_expiry_date->format('Y-m-d') : '',
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
                'driver_name'           => $vehicle->driver_name ?? '',
                'has_or_cr'             => (bool) $vehicle->has_or_cr,
                'or_cr_expiry_date'     => $vehicle->or_cr_expiry_date ? $vehicle->or_cr_expiry_date->format('Y-m-d') : '',
                'has_franchise'         => (bool) $vehicle->has_franchise,
                'franchise_expiry_date' => $vehicle->franchise_expiry_date ? $vehicle->franchise_expiry_date->format('Y-m-d') : '',
            ];
        }

        $this->resetValidation();
        $this->confirmingEditVehicle = null;
    }

    public function updateVehicle(int $vehicle_id) {
        $vehicle = Vehicle::where('id', $vehicle_id)
            ->where('user_id', $this->user->id)
            ->firstOrFail();

        // ---- FIX: ensure date fields are null when checkbox is false or value is empty ----
        $vehicleData = &$this->editingVehicles[$vehicle_id];
        if (!$vehicleData['has_or_cr']) {
            $vehicleData['or_cr_expiry_date'] = null;
        } else {
            $vehicleData['or_cr_expiry_date'] = $vehicleData['or_cr_expiry_date'] ?: null;
        }
        if (!$vehicleData['has_franchise']) {
            $vehicleData['franchise_expiry_date'] = null;
        } else {
            $vehicleData['franchise_expiry_date'] = $vehicleData['franchise_expiry_date'] ?: null;
        }
        // --------------------------------------------------------------------------------

        $rules = [
            "editingVehicles.{$vehicle_id}.plate_number" => "required|string|unique:vehicles,plate_number,{$vehicle_id}",
            "editingVehicles.{$vehicle_id}.total_seats"  => 'required|integer|min:10|max:50',
            // ---- CHANGED: require driver_name, OR/CR, franchise etc. as in registration ----
            "editingVehicles.{$vehicle_id}.driver_name"           => 'required|string|min:2',
            "editingVehicles.{$vehicle_id}.has_or_cr"             => 'required|accepted',
            "editingVehicles.{$vehicle_id}.or_cr_expiry_date"     => 'required|date|after:today',
            "editingVehicles.{$vehicle_id}.has_franchise"         => 'required|accepted',
            "editingVehicles.{$vehicle_id}.franchise_expiry_date" => 'required|date|after:today',
        ];

        if (in_array($vehicle->vehicle_type, ['Bus', 'UV-express'])) {
            $rules["editingVehicles.{$vehicle_id}.group_number"] = 'required|integer|min:1|max:2';
        }

        $data = $this->validate($rules);

        $vehicle->update([
            'plate_number' => $data['editingVehicles'][$vehicle_id]['plate_number'],
            'total_seats'  => $data['editingVehicles'][$vehicle_id]['total_seats'],
            'driver_name'           => $data['editingVehicles'][$vehicle_id]['driver_name'] ?? '',
            'has_or_cr'             => (bool) ($data['editingVehicles'][$vehicle_id]['has_or_cr'] ?? false),
            'or_cr_expiry_date'     => $data['editingVehicles'][$vehicle_id]['or_cr_expiry_date'] ?? null,
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
    {{-- Breadcrumbs moved to the right, aligned with the heading --}}
    <div class="flex items-center justify-between mb-4">
        <x-pages-heading heading="Edit User Information"/>
        <flux:breadcrumbs>
            <flux:breadcrumbs.item href="{{ route('admin.users') }}" wire:navigate>Back to Users</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>{{ $this->user->name }}</flux:breadcrumbs.item>
        </flux:breadcrumbs>
    </div>

    @if ($this->user->card === null)
        <flux:callout variant="warning" icon="exclamation-circle">
            <flux:callout.heading>This user has no RFID card assigned</flux:callout.heading>
            <flux:callout.text class="flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-4">
                <div class="text-sm sm:text-base">
                    They won't be able to tap at the terminal until a card is issued and linked to their account.
                </div>
                <flux:button
                    size="sm"
                    icon="credit-card"
                    wire:click="$set('showCardPanel', true)"
                    class="w-full sm:w-auto shrink-0"
                >
                    Issue card now
                </flux:button>
            </flux:callout.text>
        </flux:callout>
    @endif

    {{-- Card issuance panel --}}
    @if ($showCardPanel)
        <div class="mt-3 rounded-xl border border-light-bd-default dark:border-dark-bd-default bg-light-secondary dark:bg-dark-secondary overflow-hidden">

            <div class="flex items-center gap-2 px-4 sm:px-5 py-3 border-b border-light-bd-default dark:border-dark-bd-default bg-light-subtle dark:bg-dark-secondary">
                <flux:icon name="credit-card" class="w-5 h-5 text-light-txt-muted dark:text-dark-txt-muted shrink-0" />
                <span class="font-primary font-bold text-light-txt-primary dark:text-dark-txt-primary" style="font-size: var(--text-card-title)">
                    Issue RFID card
                </span>
                <div class="flex-1"></div>
                <flux:button
                    size="sm"
                    variant="ghost"
                    icon="x-mark"
                    wire:click="$set('showCardPanel', false)"
                    aria-label="Close"
                />
            </div>

            {{-- Scan state banner --}}
            <div @class([
                'flex items-start gap-3 px-4 sm:px-5 py-3 border-b border-light-bd-default dark:border-dark-bd-default',
                'bg-blue-50 dark:bg-blue-950/40'   => empty($cardUid),
                'bg-green-50 dark:bg-green-950/40' => !empty($cardUid),
            ])>
                <flux:icon
                    :name="empty($cardUid) ? 'credit-card' : 'check-circle'"
                    @class([
                        'w-5 h-5 shrink-0 mt-0.5',
                        'text-blue-500 dark:text-blue-400'   => empty($cardUid),
                        'text-green-600 dark:text-green-400' => !empty($cardUid),
                    ])
                />
                <div class="min-w-0 space-y-0.5">
                    @if (empty($cardUid))
                        <p class="font-secondary font-semibold text-sm text-blue-700 dark:text-blue-300">Ready to scan</p>
                        <p class="font-secondary text-helper leading-snug text-blue-600 dark:text-blue-400">
                            Hold the new RFID card near the reader — the UID fills in automatically.
                        </p>
                    @else
                        <p class="font-secondary font-semibold text-sm text-green-700 dark:text-green-300">Card detected</p>
                        <p class="font-secondary text-helper leading-snug text-green-600 dark:text-green-400 break-all">
                            UID {{ $cardUid }} captured. Review the assignment below then click Assign card.
                        </p>
                    @endif
                </div>
            </div>

            <div class="p-4 sm:p-5 space-y-4">
                <flux:field>
                    <flux:label class="flex items-center gap-1.5 font-secondary font-medium uppercase tracking-wide text-nav-label text-light-txt-muted dark:text-dark-txt-muted">
                        <flux:icon name="credit-card" class="w-3.5 h-3.5" />
                        Card UID
                    </flux:label>
                    <flux:input
                        id="card-uid-input"
                        wire:model.live="cardUid"
                        placeholder="Tap the card on the reader..."
                        autocomplete="off"
                        class="font-mono tracking-widest"
                        autofocus
                    />
                    <flux:error name="cardUid" />
                    <flux:description class="font-secondary text-helper text-light-txt-muted dark:text-dark-txt-muted">
                        The UID is captured automatically by the RFID reader. Do not type this manually.
                    </flux:description>
                </flux:field>

                {{-- Assignment preview --}}
                @if (!empty($cardUid))
                    <div class="rounded-lg bg-light-subtle dark:bg-dark-secondary border border-light-bd-default dark:border-dark-bd-default p-3">
                        <p class="font-secondary font-medium uppercase tracking-wide text-nav-label text-light-txt-muted dark:text-dark-txt-muted mb-2">
                            Card will be assigned to
                        </p>
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                            <div class="flex items-center gap-2 min-w-0">
                                <flux:avatar name="{{ $user->name }}" size="sm" class="shrink-0" />
                                <div class="min-w-0">
                                    <p class="font-secondary font-semibold text-sm text-light-txt-primary dark:text-dark-txt-primary truncate">{{ $user->name }}</p>
                                    <p class="font-secondary font-mono text-helper text-light-txt-muted dark:text-dark-txt-muted truncate">{{ $user->user_code }} · {{ ucfirst($user->role) }}</p>
                                </div>
                            </div>
                            <div class="sm:text-right shrink-0">
                                <p class="font-secondary text-nav-label uppercase tracking-wide text-light-txt-muted dark:text-dark-txt-muted">UID</p>
                                <p class="font-mono font-semibold text-sm text-blue-600 dark:text-blue-400 break-all">{{ $cardUid }}</p>
                            </div>
                        </div>
                    </div>
                @endif

                <div class="flex flex-col sm:flex-row gap-2 pt-1">
                    <flux:button
                        type="button"
                        class="flex-1 font-secondary py-2! text-sm! lg:text-md!"
                        wire:click="$set('showCardPanel', false)"
                    >
                        Cancel
                    </flux:button>
                    <flux:button
                        type="button"
                        variant="primary"
                        icon="credit-card"
                        class="flex-1 font-secondary py-2! text-sm! lg:text-md!"
                        wire:click="issueCard"
                        wire:loading.attr="disabled"
                        wire:target="issueCard"
                        :disabled="empty($cardUid)"
                    >
                        Assign card to user
                    </flux:button>
                </div>
            </div>
        </div>
    @endif

    {{-- User profile header --}}
    <div class="mt-4 mb-6 p-4 sm:p-5 rounded-xl border border-light-bd-default dark:border-dark-bd-default bg-light-secondary dark:bg-dark-secondary">
        <div class="flex items-center gap-4">
            <div class="p-1 rounded-xl bg-primary/10 dark:bg-primary/25 shrink-0">
                <flux:avatar src="{{ $user->avatar_url }}" name="{{ $user->name }}" size="xl" />
            </div>
            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2 flex-wrap">
                    <x-text class="font-primary text-base sm:text-lg font-bold text-light-txt-primary dark:text-dark-txt-primary truncate">{{ $user->name }}</x-text>
                    @unless ($user->card)
                        <span class="inline-flex items-center shrink-0 text-[11px] sm:text-xs font-semibold px-2 py-0.5 rounded-full bg-red-50 text-red-600 dark:bg-red-950/40 dark:text-dark-danger">
                            No card
                        </span>
                    @endunless
                </div>
                <div class="flex items-center gap-1.5 mt-0.5 min-w-0">
                    @if ($user->card)
                        <span class="font-secondary text-xs sm:text-sm text-light-txt-muted dark:text-dark-txt-muted font-mono truncate">{{ $user->card->card_number }}</span>
                        <span class="text-light-bd-strong dark:text-dark-bd-strong text-xs">·</span>
                    @endif
                    <span class="font-secondary text-xs sm:text-sm text-light-txt-muted dark:text-dark-txt-muted truncate">{{ $user->user_code }}</span>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-2 {{ $user->role === 'operator' ? 'sm:grid-cols-3' : 'sm:grid-cols-2' }} gap-2 sm:gap-3 mt-4">
            <div class="rounded-lg bg-light-subtle dark:bg-dark-subtle px-3 py-2">
                <span class="block text-[10px] sm:text-xs font-medium uppercase tracking-wider text-light-txt-muted dark:text-dark-txt-muted mb-1">Role</span>
                @if ($user->role === 'operator')
                    <flux:badge color="blue" size="lg">Operator</flux:badge>
                @else
                    <flux:badge color="yellow" size="lg">Commuter</flux:badge>
                @endif
            </div>
            <div class="rounded-lg bg-light-subtle dark:bg-dark-subtle px-3 py-2">
                <span class="block text-[10px] sm:text-xs font-medium uppercase tracking-wider text-light-txt-muted dark:text-dark-txt-muted mb-1">Joined</span>
                <span class="font-secondary text-lg font-medium text-light-txt-primary dark:text-dark-txt-primary">
                    {{ $user->created_at->format('M d, Y') }}
                </span>
            </div>
            @if ($user->role === 'operator')
                <div class="rounded-lg bg-light-subtle dark:bg-dark-subtle px-3 py-2">
                    <span class="block text-[10px] sm:text-xs font-medium uppercase tracking-wider text-light-txt-muted dark:text-dark-txt-muted mb-1">Vehicles</span>
                    <span class="font-secondary text-lg font-medium text-light-txt-primary dark:text-dark-txt-primary">
                        {{ $this->getVehicle->count() }}
                    </span>
                </div>
            @endif
        </div>
    </div>

    {{-- Personal information form --}}
    <form wire:submit="save">
        <div class="w-full border border-light-bd-default dark:border-dark-bd-default rounded-xl bg-light-secondary dark:bg-dark-secondary overflow-hidden">
            <div class="flex items-center gap-3 px-4 sm:px-6 py-3 border-b border-light-bd-default dark:border-dark-bd-default bg-light-subtle/50 dark:bg-dark-secondary">
                <flux:icon.user class="w-5 h-5 lg:w-6 lg:h-6 text-light-txt-muted dark:text-dark-txt-muted shrink-0" />
                <span class="text-md lg:text-section-heading font-primary font-bold text-light-txt-body dark:text-dark-txt-body">Personal information</span>
            </div>

            <div class="p-4 sm:p-6 space-y-4">
                <div class="grid w-full grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6">
                    <flux:input label="Name" wire:model="name" class="w-full font-secondary" :readonly="$user->isDeleted()" />
                    <div>
                        <flux:input
                            label="Email"
                            wire:model="email_address"
                            type="email"
                            placeholder="Optional"
                            class="w-full font-secondary"
                            :readonly="$user->isDeleted()"
                        />
                    </div>
                    <div>
                        <flux:input
                            label="Mobile number"
                            wire:model="phone_number"
                            type="tel"
                            placeholder="09XXXXXXXXX"
                            class="w-full font-secondary"
                            :readonly="$user->isDeleted()"
                        />
                    </div>
                    <div>
                        <flux:field>
                            <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">
                                Address
                            </flux:label>

                            @if ($user->isDeleted())
                                <div class="w-full font-secondary text-table-row bg-light-subtle dark:bg-dark-subtle text-light-txt-muted dark:text-dark-txt-muted border border-light-bd-default dark:border-dark-bd-default rounded-lg px-3 py-2.5 opacity-70">
                                    {{ $address ?: 'No address on file' }}
                                </div>
                            @else
                                <flux:modal.trigger name="address-modal">
                                    <button
                                        type="button"
                                        wire:click="prepareAddressModal"
                                        class="w-full text-left font-secondary text-table-row bg-light-primary dark:bg-dark-surface text-light-txt-body dark:text-dark-txt-primary border border-light-bd-default dark:border-dark-bd-default rounded-lg px-3 py-2.5 transition-shadow duration-200 focus:outline-none focus:ring-2 focus:ring-secondary/50"
                                    >
                                        @if ($address)
                                            {{ $address }}
                                        @else
                                            <span class="text-light-txt-muted dark:text-dark-txt-muted">Tap to set address</span>
                                        @endif
                                    </button>
                                </flux:modal.trigger>
                            @endif
                            <flux:error name="address" />
                        </flux:field>
                    </div>

                    {{-- Role and user code (always readonly) --}}
                    <flux:input
                        label="Role"
                        value="{{ ucfirst($user->role) }}"
                        class="w-full font-secondary opacity-70"
                        icon:trailing="lock-closed"
                        readonly
                    />
                    <flux:input
                        label="User code"
                        value="{{ $user->user_code }}"
                        class="w-full font-secondary opacity-70"
                        icon:trailing="lock-closed"
                        readonly
                    />
                </div>

                <div class="flex flex-col sm:flex-row sm:items-center gap-3 w-full pt-4 dark:border-dark-bd-default">

                    {{-- Admins can only suspend accounts — never delete them.
                         A commuter who has permanently deleted their own
                         account can no longer be suspended/reinstated. --}}
                    @if ($user->isDeleted())
                        <flux:badge color="zinc" size="sm" class="font-secondary text-badge text-xs w-full sm:w-auto text-center order-2 sm:order-1">
                            Account permanently deleted by user
                        </flux:badge>
                    @elseif ($user->isSuspended())
                        <flux:modal.trigger name="reinstate-user">
                            <flux:button
                                type="button"
                                variant="ghost"
                                size="sm"
                                class="text-emerald-600 border-emerald-200 hover:bg-emerald-50 dark:hover:bg-emerald-950 w-full sm:w-auto order-2 sm:order-1"
                                icon="check-circle"
                            >Reinstate user</flux:button>
                        </flux:modal.trigger>
                    @else
                        <flux:modal.trigger name="suspend-user">
                            <flux:button
                                type="button"
                                variant="ghost"
                                size="sm"
                                class="text-amber-600 border-amber-200 hover:bg-amber-50 dark:hover:bg-amber-950 w-full sm:w-auto order-2 sm:order-1"
                                icon="no-symbol"
                            >Suspend user</flux:button>
                        </flux:modal.trigger>
                    @endif

                    <flux:spacer class="hidden sm:block" />

                    <flux:button
                        size="sm"
                        variant="primary"
                        type="submit"
                        icon="check"
                        class="w-full sm:w-auto order-1 sm:order-2"
                        :disabled="$user->isDeleted()"
                    >
                        Save changes
                    </flux:button>

                </div>
            </div>
        </div>
    </form>

    @if ($user->isSuspended())
        <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 dark:border-amber-900 dark:bg-amber-950/40 p-4">
            <flux:heading size="sm" class="!font-primary !font-bold text-amber-700 dark:text-amber-400">
                This account is currently suspended
            </flux:heading>
            <flux:text class="mt-1 font-secondary text-sm text-amber-700 dark:text-amber-400">
                Reason: {{ $user->userStatus?->suspension_reason }}
                @if ($user->userStatus?->suspended_at)
                    <br>Suspended on {{ $user->userStatus->suspended_at->format('F d, Y g:i A') }}
                    @if ($user->userStatus?->suspendedBy)
                        by {{ $user->userStatus->suspendedBy->name }}
                    @endif
                @endif
            </flux:text>
        </div>
    @endif

    {{-- Suspend-user confirmation modal --}}
    <flux:modal name="suspend-user" class="md:w-96">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg" class="!font-primary !font-bold text-light-txt-primary dark:text-dark-txt-primary">
                    Suspend user?
                </flux:heading>
                <flux:text class="mt-1 font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">
                    <strong>{{ $user->name }}</strong> will be signed out immediately and won't be able to log in
                    @if ($user->role === 'operator')
                        or queue their vehicle
                    @endif
                    until this suspension is lifted. They'll need to visit the terminal office in person to have their account reviewed.
                </flux:text>
            </div>

            <flux:textarea
                wire:model="suspension_reason_input"
                label="Reason for suspension"
                placeholder="e.g. Expired OR/CR, expired franchise, reported misuse..."
                rows="3"
            />

            <div class="flex flex-col sm:flex-row justify-end gap-2 pt-2 border-t border-light-bd-default dark:border-dark-bd-default">
                <flux:modal.close>
                    <flux:button type="button" variant="ghost" class="font-secondary w-full sm:w-auto">
                        Cancel
                    </flux:button>
                </flux:modal.close>
                <flux:button
                    type="button"
                    variant="danger"
                    icon="no-symbol"
                    wire:click="suspendUser"
                    wire:loading.attr="disabled"
                    wire:target="suspendUser"
                    class="font-secondary w-full sm:w-auto"
                >
                    Yes, suspend user
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Reinstate-user confirmation modal --}}
    <flux:modal name="reinstate-user" class="md:w-96">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg" class="!font-primary !font-bold text-light-txt-primary dark:text-dark-txt-primary">
                    Reinstate user?
                </flux:heading>
                <flux:text class="mt-1 font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">
                    Only confirm this after <strong>{{ $user->name }}</strong> has submitted their required documents
                    (e.g. updated OR/CR, franchise) in person and they've been verified. Their account will be reactivated immediately.
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
                    variant="primary"
                    icon="check-circle"
                    wire:click="reinstateUser"
                    wire:loading.attr="disabled"
                    wire:target="reinstateUser"
                    class="font-secondary w-full sm:w-auto"
                >
                    Yes, reinstate user
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Operator vehicle section --}}
    @if ($user->role === 'operator')
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

                        {{-- NEW: Dedicated driver --}}
                        <div>
                            <flux:label class="mb-2 font-secondary text-md text-light-txt-primary dark:text-dark-txt-muted">Dedicated driver</flux:label>
                            <flux:input wire:model="create_driver_name" placeholder="e.g. Juan dela Cruz" />
                            <flux:error name="create_driver_name" />
                        </div>

                        {{-- NEW: Compliance documents (span full width) --}}
                        <div class="sm:col-span-2 border-t border-light-bd-default dark:border-dark-bd-default pt-3 space-y-3">
                            <p class="font-secondary text-xs font-medium uppercase tracking-wide text-light-txt-muted dark:text-dark-txt-muted">Compliance documents</p>

                            <div class="flex items-start gap-3">
                                <flux:checkbox wire:model.live="create_has_or_cr" />
                                <div class="flex-1 min-w-0 space-y-1">
                                    <flux:label class="font-secondary text-sm text-light-txt-body dark:text-dark-txt-primary">
                                        OR/CR verified
                                        <span class="ml-1 font-normal text-light-txt-muted dark:text-dark-txt-muted">(admin confirms document was seen)</span>
                                    </flux:label>
                                    <div class="flex items-center gap-2">
                                        <span class="font-secondary text-xs text-light-txt-muted dark:text-dark-txt-muted whitespace-nowrap">Expiration Date:</span>
                                        <flux:input
                                            type="date"
                                            wire:model="create_or_cr_expiry_date"
                                            :disabled="!$create_has_or_cr"
                                            size="sm"
                                            class="flex-1"
                                            wire:key="or-cr-{{ $create_has_or_cr }}"
                                        />
                                    </div>
                                    <flux:error name="create_or_cr_expiry_date" />
                                </div>
                            </div>

                            <div class="flex items-start gap-3">
                                <flux:checkbox wire:model.live="create_has_franchise" />
                                <div class="flex-1 min-w-0 space-y-1">
                                    <flux:label class="font-secondary text-sm text-light-txt-body dark:text-dark-txt-primary">
                                        Franchise verified
                                        <span class="ml-1 font-normal text-light-txt-muted dark:text-dark-txt-muted">(admin confirms document was seen)</span>
                                    </flux:label>
                                    <div class="flex items-center gap-2">
                                        <span class="font-secondary text-xs text-light-txt-muted dark:text-dark-txt-muted whitespace-nowrap">Expiration Date:</span>
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
                            </p>
                            <p class="font-secondary text-xs text-light-txt-muted dark:text-dark-txt-muted truncate">
                                {{ $vehicle->total_seats }} seats
                                @if(in_array($vehicle->vehicle_type, ['Bus', 'UV-express']) && $groupNumber) · Group {{ $groupNumber }} @endif
                                · Registered {{ $vehicle->created_at->format('Y-m-d') }}
                                @if($vehicle->driver_name) · Driver: {{ $vehicle->driver_name }} @endif
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-1 shrink-0">
                        <flux:modal.trigger name="edit-vehicle-{{ $vehicle->id }}">
                            <flux:button type="button" variant="ghost" size="sm" icon="pencil"
                                wire:click="editVehicle({{ $vehicle->id }})" />
                        </flux:modal.trigger>
                        <flux:modal.trigger name="delete-vehicle-{{ $vehicle->id }}">
                            <flux:button type="button" variant="ghost" size="sm" icon="trash"
                                class="text-red-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-950"/>
                        </flux:modal.trigger>
                    </div>
                </div>
            </flux:card>

            {{-- Delete-vehicle confirmation modal --}}
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

            {{-- Edit-vehicle modal (UPDATED with wire:key on date inputs) --}}
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

                        <div class="sm:col-span-2">
                            <flux:label class="mb-2 font-secondary text-md text-light-txt-primary dark:text-dark-txt-muted">Dedicated driver</flux:label>
                            <flux:input wire:model="editingVehicles.{{ $vehicle->id }}.driver_name" placeholder="e.g. Juan dela Cruz" size="sm" />
                            <flux:error name="editingVehicles.{{ $vehicle->id }}.driver_name" />
                        </div>

                        <div class="sm:col-span-2 border-t border-light-bd-default dark:border-dark-bd-default pt-3 space-y-3">
                            <p class="font-secondary text-xs font-medium uppercase tracking-wide text-light-txt-muted dark:text-dark-txt-muted">Compliance documents</p>

                            <div class="flex items-start gap-3">
                                <flux:checkbox wire:model.live="editingVehicles.{{ $vehicle->id }}.has_or_cr" />
                                <div class="flex-1 min-w-0 space-y-1">
                                    <flux:label class="font-secondary text-sm text-light-txt-body dark:text-dark-txt-primary">
                                        OR/CR verified
                                        <span class="ml-1 font-normal text-light-txt-muted dark:text-dark-txt-muted">(admin confirms document was seen)</span>
                                    </flux:label>
                                    <div class="flex items-center gap-2">
                                        <span class="font-secondary text-xs text-light-txt-muted dark:text-dark-txt-muted whitespace-nowrap">Expiration Date:</span>
                                        <flux:input
                                            type="date"
                                            wire:model="editingVehicles.{{ $vehicle->id }}.or_cr_expiry_date"
                                            :disabled="!$this->editingVehicles[$vehicle->id]['has_or_cr']"
                                            size="sm"
                                            class="flex-1"
                                            wire:key="edit-or-cr-{{ $vehicle->id }}-{{ $this->editingVehicles[$vehicle->id]['has_or_cr'] }}"
                                        />
                                    </div>
                                    <flux:error name="editingVehicles.{{ $vehicle->id }}.or_cr_expiry_date" />
                                </div>
                            </div>

                            <div class="flex items-start gap-3">
                                <flux:checkbox wire:model.live="editingVehicles.{{ $vehicle->id }}.has_franchise" />
                                <div class="flex-1 min-w-0 space-y-1">
                                    <flux:label class="font-secondary text-sm text-light-txt-body dark:text-dark-txt-primary">
                                        Franchise verified
                                        <span class="ml-1 font-normal text-light-txt-muted dark:text-dark-txt-muted">(admin confirms document was seen)</span>
                                    </flux:label>
                                    <div class="flex items-center gap-2">
                                        <span class="font-secondary text-xs text-light-txt-muted dark:text-dark-txt-muted whitespace-nowrap">Expiration Date:</span>
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
        @endforeach
    @endif

    {{-- Address modal (with pre‑fill) – UPDATED with Province field --}}
    <flux:modal
        name="address-modal"
        :closable="false"
        class="w-[calc(100%-2rem)] sm:max-w-lg md:max-w-2xl lg:max-w-3xl mx-auto rounded-xl overflow-hidden"
        x-on:address-saved.window="$flux.modal('address-modal').close()"
    >
        <div class="flex flex-col p-4 sm:p-6 !pr-4 sm:!pr-6 space-y-5 overflow-y-auto max-h-[70vh]">
            <div class="flex items-start justify-between">
                <div>
                    <flux:heading size="xl" class="!font-primary !font-bold text-light-txt-primary dark:text-dark-txt-primary">
                        Set address
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
                <flux:error name="house_subd" class="font-secondary text-helper text-danger dark:text-dark-danger mt-1" />
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
                <flux:error name="zone_number" class="font-secondary text-helper text-danger dark:text-dark-danger mt-1" />
            </flux:field>

            <flux:field>
                <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">Barangay</flux:label>
                <flux:input
                    wire:model="barangay"
                    placeholder="e.g. San Roque"
                    class="font-secondary text-table-row bg-light-primary dark:bg-dark-surface text-light-txt-body dark:text-dark-txt-primary border-light-bd-default dark:border-dark-bd-default placeholder:text-light-txt-muted dark:placeholder:text-dark-txt-muted"
                />
                <flux:error name="barangay" class="font-secondary text-helper text-danger dark:text-dark-danger mt-1" />
            </flux:field>

            <flux:field>
                <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">Municipality / City</flux:label>
                <flux:input
                    wire:model="municipality"
                    placeholder="e.g. Iriga City"
                    class="font-secondary text-table-row bg-light-primary dark:bg-dark-surface text-light-txt-body dark:text-dark-txt-primary border-light-bd-default dark:border-dark-bd-default placeholder:text-light-txt-muted dark:placeholder:text-dark-txt-muted"
                />
                <flux:error name="municipality" class="font-secondary text-helper text-danger dark:text-dark-danger mt-1" />
            </flux:field>

            {{-- NEW Province field --}}
            <flux:field>
                <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">Province</flux:label>
                <flux:input
                    wire:model="province"
                    placeholder="e.g. Camarines Sur"
                    class="font-secondary text-table-row bg-light-primary dark:bg-dark-surface text-light-txt-body dark:text-dark-txt-primary border-light-bd-default dark:border-dark-bd-default placeholder:text-light-txt-muted dark:placeholder:text-dark-txt-muted"
                />
                <flux:error name="province" class="font-secondary text-helper text-danger dark:text-dark-danger mt-1" />
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

</div>
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

new #[Layout('layouts.admin-layout')] class extends Component
{
    public User $user;

    public $name;
    public $username;
    public $email_address;
    public $phone_number;
    public $address;

    public $confirmingAddVehicle = null;
    public array $editingVehicles = [];

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

    public $create_vehicle_type = '';
    public $create_route        = '';
    public $create_plate_number = '';
    public $create_total_seats  = '';
    public $create_group_number = '';

    public ?int $confirmingEditVehicle = null;

    public $route_list_id;
    public $vehicles;

    public bool $showCardPanel = false;
    public string $cardUid = '';

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

    #[Computed]
    public function getBarangays()
    {
        return self::BARANGAYS;
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
        $this->username      = $this->user->username;
        $this->email_address = $this->user->email_address ?? '';
        $this->phone_number  = $this->user->phone_number ?? '';
        $this->address       = $this->user->address;

        foreach ($this->getVehicle as $vehicle) {
            $this->editingVehicles[$vehicle->id] = [
                'vehicle_type' => $vehicle->vehicle_type,
                'total_seats'  => $vehicle->total_seats,
                'plate_number' => $vehicle->plate_number,
                'group_number' => $this->getVehicleGroupNumber($vehicle->id),
            ];
        }
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

    public function issueCard(): void
    {
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
            heading: 'Card issued.',
            text: 'RFID card has been linked to ' . $this->user->name . '.',
        );
    }

    public function verifyUser() {
        $attributes = [
            'type'     => 'verified',
        ];

        app(UserService::class)->update($this->user, $attributes);

        Flux::toast(
            variant: 'success',
            heading: 'User verified.',
            text: 'Your changes have been saved.'
        );
    }

    public function save() {
        $attributes = $this->validate([
            'name'          => 'required|min:2|string',
            'username'      => 'required|min:1|string',
            'email_address' => 'nullable|string|email|max:255|unique:users,email_address,' . $this->user->id,
            'phone_number'  => 'required|string|regex:/^09\d{9}$/',
            'address'       => 'required|min:1|string',
        ], [
            'phone_number.regex' => 'Enter a valid mobile number (e.g. 09171234567).',
        ]);

        app(UserService::class)->update($this->user, $attributes);

        Flux::toast(
            variant: 'success',
            heading: 'Changes saved.',
            text: 'Your changes have been saved.'
        );
    }

    public function deleteUser() {
        app(UserService::class)->destroy($this->user);
        $this->redirect(route('admin.users'), navigate: true);
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
            ]);
            $this->resetValidation();
        }
    }

    public function addNewVehicle() {
        DB::transaction(function () {
            $attributes = $this->validate([
                'create_vehicle_type' => 'required|string',
                'create_route'        => 'required|string',
                'create_plate_number' => 'required|string|unique:vehicles,plate_number',
                'create_total_seats'  => 'required|integer|min:10|max:50',
                'create_group_number' => 'nullable|integer|min:1|max:2',
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
            ];
        });

        $this->reset([
            'create_vehicle_type',
            'create_route',
            'create_plate_number',
            'create_total_seats',
            'create_group_number',
        ]);

        unset($this->getVehicle);
        $this->addingVehicle(false);

        Flux::toast(variant: 'success', heading: 'Vehicle added.', text: 'New vehicle has been added.');
    }

    public function editVehicle(int $vehicle_id) {
        $vehicle = Vehicle::find($vehicle_id);

        if ($vehicle) {
            $this->editingVehicles[$vehicle->id] = [
                'vehicle_type' => $vehicle->vehicle_type,
                'total_seats'  => $vehicle->total_seats,
                'plate_number' => $vehicle->plate_number,
                'group_number' => $this->getVehicleGroupNumber($vehicle->id),
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
            ];
        }

        $this->resetValidation();
        $this->confirmingEditVehicle = null;
    }

    public function updateVehicle(int $vehicle_id) {
        $vehicle = Vehicle::where('id', $vehicle_id)
            ->where('user_id', $this->user->id)
            ->firstOrFail();

        $rules = [
            "editingVehicles.{$vehicle_id}.plate_number" => "required|string|unique:vehicles,plate_number,{$vehicle_id}",
            "editingVehicles.{$vehicle_id}.total_seats"  => 'required|integer|min:10|max:50',
        ];

        if (in_array($vehicle->vehicle_type, ['Bus', 'UV-express'])) {
            $rules["editingVehicles.{$vehicle_id}.group_number"] = 'required|integer|min:1|max:2';
        }

        $data = $this->validate($rules);

        $vehicle->update([
            'plate_number' => $data['editingVehicles'][$vehicle_id]['plate_number'],
            'total_seats'  => $data['editingVehicles'][$vehicle_id]['total_seats'],
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

        Flux::toast(variant: 'success', heading: 'Vehicle updated.', text: 'Vehicle information has been updated.');
    }

    public function deleteVehicle(int $vehicle_id) {
        Vehicle::where('id', $vehicle_id)
            ->where('user_id', $this->user->id)
            ->delete();

        unset($this->editingVehicles[$vehicle_id]);
        unset($this->getVehicle);

        $this->dispatch('vehicle-deleted', id: $vehicle_id);

        Flux::toast(variant: 'success', heading: 'Vehicle deleted.', text: 'Vehicle has been deleted.');
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
    <flux:breadcrumbs class="mb-4">
        <flux:breadcrumbs.item href="{{ route('admin.users') }}" wire:navigate>Users</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ $this->user->name }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <x-pages-heading heading="Edit User Information"/>

    @if ($this->user->card === null && $this->user->type === 'verified')
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
    @elseif($this->user->card === null && $this->user->type === 'pending')
        <flux:callout variant="warning" icon="clock">
            <flux:callout.heading>This user's account is pending for verification</flux:callout.heading>
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
                    <flux:input label="Name"     wire:model="name"     class="w-full font-secondary" />
                    <flux:input label="Username" wire:model="username" class="w-full font-secondary" />
                    <div>
                        <flux:input
                            label="Email"
                            wire:model="email_address"
                            type="email"
                            placeholder="Optional"
                            class="w-full font-secondary"
                        />
                    </div>
                    <div>
                        <flux:input
                            label="Mobile number"
                            wire:model="phone_number"
                            type="tel"
                            placeholder="09XXXXXXXXX"
                            class="w-full font-secondary"
                        />
                    </div>
                    <div class="sm:col-span-2">
                        {{-- Address field replaced with modal trigger button --}}
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
                    </div>
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
                @if ($user->type === 'pending')
                    <div>
                        <flux:label class="mb-4">Valid Id</flux:label>
                        <img src="{{ Storage::url($user->valid_id) }}" />
                    </div>
                @endif  

                <div class="flex flex-col sm:flex-row sm:items-center gap-3 w-full pt-4 border-t border-light-bd-default dark:border-dark-bd-default">

                    <flux:modal.trigger name="delete-user">

                        <flux:button
                            type="button"
                            variant="ghost"
                            size="sm"
                            class="text-red-500 border-red-200 hover:bg-red-50 dark:hover:bg-red-950 w-full sm:w-auto order-2 sm:order-1"
                            icon="trash"
                        >Delete user</flux:button>

                    </flux:modal.trigger>

                    <flux:spacer class="hidden sm:block" />

                    <flux:button size="sm" variant="primary" type="submit" icon="check" class="w-full sm:w-auto order-1 sm:order-2">
                        Save changes
                    </flux:button>

                    @if ($user->type === 'pending')

                        <flux:button size="sm" variant="primary" type="button" icon="check" wire:click="verifyUser" class="w-full sm:w-auto order-1 sm:order-2">
                            Verify this user
                        </flux:button>

                    @endif

                </div>
            </div>
        </div>
    </form>

    {{-- Delete-user confirmation modal --}}
    <flux:modal name="delete-user" class="md:w-96">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg" class="!font-primary !font-bold text-light-txt-primary dark:text-dark-txt-primary">
                    Delete user?
                </flux:heading>
                <flux:text class="mt-1 font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">
                    You're about to permanently delete <strong>{{ $user->name }}</strong>
                    @if ($user->role === 'operator')
                        along with all their vehicles.
                    @else
                        .
                    @endif
                    This cannot be undone.
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
                    wire:click="deleteUser"
                    wire:loading.attr="disabled"
                    wire:target="deleteUser"
                    class="font-secondary w-full sm:w-auto"
                >
                    Yes, delete user
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
                            @error('create_vehicle_type')
                                <p class="font-secondary text-xs text-red-500 dark:text-red-400 mt-1">{{ $message }}</p>
                            @enderror
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
                            @error('create_route')
                                <p class="font-secondary text-xs text-red-500 dark:text-red-400 mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <flux:label class="mb-2 font-secondary text-md text-light-txt-primary dark:text-dark-txt-muted">Plate number</flux:label>
                            <flux:input wire:model="create_plate_number" />
                            @error('create_plate_number')
                                <p class="font-secondary text-xs text-red-500 dark:text-red-400 mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <flux:label class="mb-2 font-secondary text-md text-light-txt-primary dark:text-dark-txt-muted">Total seats</flux:label>
                            <flux:input wire:model="create_total_seats" type="number" min="10" max="50" />
                            @error('create_total_seats')
                                <p class="font-secondary text-xs text-red-500 dark:text-red-400 mt-1">{{ $message }}</p>
                            @enderror
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
                            @error('create_group_number')
                                <p class="font-secondary text-xs text-red-500 dark:text-red-400 mt-1">{{ $message }}</p>
                            @enderror
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
                            <p class="font-primary text-sm font-bold text-light-txt-body dark:text-dark-txt-body truncate">
                                {{ $vehicle->plate_number }}
                                <span class="font-normal font-secondary text-light-txt-muted dark:text-dark-txt-muted">· {{ $vehicle->vehicle_type }}</span>
                            </p>
                            <p class="font-secondary text-xs text-light-txt-muted dark:text-dark-txt-muted truncate">
                                {{ $vehicle->total_seats }} seats
                                @if(in_array($vehicle->vehicle_type, ['Bus', 'UV-express']) && $groupNumber) · Group {{ $groupNumber }} @endif
                                · Registered {{ $vehicle->created_at->format('Y-m-d') }}
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

            {{-- Edit-vehicle modal --}}
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
                                @error("editingVehicles.{$vehicle->id}.group_number")
                                    <p class="font-secondary text-xs text-red-500 dark:text-red-400 mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        @endif

                        <div>
                            <flux:label class="mb-2 font-secondary text-md text-light-txt-primary dark:text-dark-txt-muted">Plate number</flux:label>
                            <flux:input wire:model="editingVehicles.{{ $vehicle->id }}.plate_number" size="sm" />
                            @error("editingVehicles.{$vehicle->id}.plate_number")
                                <p class="font-secondary text-xs text-red-500 dark:text-red-400 mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <flux:label class="mb-2 font-secondary text-md text-light-txt-primary dark:text-dark-txt-muted">Total seats</flux:label>
                            <flux:input wire:model="editingVehicles.{{ $vehicle->id }}.total_seats" type="number" min="10" max="50" size="sm" />
                            @error("editingVehicles.{$vehicle->id}.total_seats")
                                <p class="font-secondary text-xs text-red-500 dark:text-red-400 mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <flux:label class="mb-2 font-secondary text-md text-light-txt-primary dark:text-dark-txt-muted">Date registered</flux:label>
                            <flux:input value="{{ $vehicle->created_at->format('Y-m-d') }}" disabled size="sm" />
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

    <flux:modal name="address-modal" class="md:w-[26rem]" x-on:address-saved.window="$flux.modal('address-modal').close()">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg" class="!font-primary !font-bold text-light-txt-primary dark:text-dark-txt-primary">
                    Set address
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

</div>
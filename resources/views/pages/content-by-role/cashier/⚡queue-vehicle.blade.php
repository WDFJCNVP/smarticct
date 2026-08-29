<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Queue;
use App\Models\CashTransaction;

use App\Models\Card;
use App\Models\RouteList;
use App\Models\Vehicle;
use App\Models\User;
use App\Models\Notification;
use App\Models\UserNotification;
use App\Http\Controllers\Api\CardController;

use App\Events\NotificationEvent;
use App\Services\AuditLogsService;

new class extends Component
{
    public bool $card_focused = true;
    public string $card_state = 'ready';
    public string $card_number = '';
    public string $operator_name = '';
    public string $driver_name = '';
    public string $operator_tickets_price;

    public $route_list_id;

    public bool $cashMode = false;
    public $cash_operator_id = null;
    public string $operatorSearch = '';
    public $amount_received = null;
    public bool $showCommuterAlert = false;
    public bool $showInsufficientAmountAlert = false;

    public function queueVehicle() {
        if ($this->cashMode) {
            if (empty($this->amount_received) || $this->amount_received < $this->queueFee) {
                $this->showInsufficientAmountAlert = true;
                return;
            }
            if (!$this->cash_operator_id) {
                Flux::toast(
                    variant: 'warning',
                    heading: 'Missing Operator',
                    text: 'Please select an operator for cash payment.'
                );
                return;
            }

            try {
                DB::transaction(function () {
                    $queue = Queue::create([
                        'user_id'         => $this->selectedOperator->id,
                        'vehicle_id'      => $this->selectedVehicle->id,
                        'vehicle_type'    => $this->selectedVehicle->vehicle_type,
                        'destination'     => $this->selectedVehicle->route_list->terminal,
                        'plate_number'    => $this->selectedVehicle->plate_number,
                        'driver_name'     => $this->driver_name,
                        'status'          => 'staging',
                        'time_queued'     => now(),
                        'seat_capacity'   => $this->selectedVehicle->total_seats ?? 0,
                        'seat_count'      => 0,
                        'slot_position'   => Queue::where('destination', $this->selectedVehicle->route_list->terminal)
                            ->whereIn('status', ['staging', 'loading'])
                            ->max('slot_position') + 1,
                    ]);

                    CashTransaction::create([
                        'processed_by'    => auth()->id(),
                        'operator_id'     => $this->selectedOperator->id,
                        'vehicle_id'      => $this->selectedVehicle->id,
                        'queue_id'        => $queue->id,
                        'amount'          => $this->queueFee,
                        'amount_received' => $this->amount_received,
                        'change'          => $this->change,
                        'reference_no'    => 'CASH-' . now()->format('YmdHis') . '-' . $queue->id,
                        'notes'           => 'Cash payment for queueing vehicle #' . $queue->id,
                        'status'          => 'success',
                    ]);

                    app(AuditLogsService::class)->create([
                        'user_id'  => auth()->id(),
                        'action'   => 'Queued Vehicle',
                        'subject'  => 'Vehicle Queued Successfully (Cash)',
                        'channel'  => 'Web',
                        'metadata' => [
                            'ip_address'   => request()->ip(),
                            'payment_mode' => 'cash',
                            'queue_id'     => $queue->id,
                            'message'      => "Vehicle queued (ID: {$queue->id}) for operator: {$this->selectedOperator->name}",
                        ],
                    ]);

                    $notification = Notification::create([
                        'type'    => 'Queued',
                        'title'   => 'Vehicle Queued',
                        'message' => "Your {$queue->vehicle_type} with plate number {$queue->plate_number} has joined the queue.",
                        'metadata' => json_encode(['plate_number' => $queue->plate_number, 'vehicle_type' => $queue->vehicle_type]),
                    ]);

                    UserNotification::create([
                        'notification_id' => $notification->id,
                        'user_id'         => $this->selectedOperator->id,
                    ]);

                    broadcast(new NotificationEvent());

                    Flux::toast(
                        variant: 'success',
                        heading: 'Vehicle Queued Successfully',
                        text: 'Cash payment recorded. Change: ₱' . number_format($this->change, 2)
                    );

                    $this->disableCashMode();
                });

                // Cash payment succeeded — go back to the live queue page.
                $this->redirect(route('user.queue'), navigate: true);
                return;

            } catch (\Exception $e) {
                Flux::toast(
                    variant: 'warning',
                    heading: 'Failed to Queue Vehicle',
                    text: $e->getMessage()
                );
            }

            return;
        }

        // --- Card mode ---
        try {
            $request = new Request();
            $request->merge([
                'uid'              => $this->card_number,
                'driver_name'      => $this->driver_name,
                'vehicle_id'       => $this->selectedVehicle->id,
                'transaction_type' => 'operator_payment',
                'amount'           => $this->selectedVehicle->route_list->operatorTicketRate->queueing_fee,
                'destination'      => $this->selectedVehicle->route_list->terminal,
                'vehicle_type'     => $this->selectedVehicle->vehicle_type,
                'plate_number'     => $this->selectedVehicle->plate_number,
            ]);

            $response = (new CardController())->tap($request);
            $responseData = $response->getData(true);
        } catch (\Exception $e) {
            Flux::toast(
                variant: 'warning',
                heading: 'Failed to Queue Vehicle',
                text: $e->getMessage()
            );

            return;
        }

        if ($responseData['success'] === true) {
            Flux::toast(
                variant: 'success',
                heading: 'Vehicle Queued Successfully',
                text: $responseData['message']
            );

            app(AuditLogsService::class)->create([
                'user_id' => auth()->id(),
                'action'  => 'Queued Vehicle',
                'subject' => 'Vehicle Queued Successfully (Card)',
                'channel' => 'Web',
                'metadata' => [
                    'ip_address' => request()->ip(),
                    'message'    => "Vehicle was successfully queued (Vehicle ID: {$this->selectedVehicle->id}).",
                ],
            ]);

            $this->clearCard();

            // Card payment succeeded — go back to the live queue page.
            $this->redirect(route('user.queue'), navigate: true);
            return;
        }
        else {
            Flux::toast(
                variant: 'warning',
                heading: 'Failed to Queue Vehicle',
                text: $responseData['message'] ?? 'An error occurred while queuing the vehicle.'
            );

            app(AuditLogsService::class)->create([
                'user_id' => auth()->id(),
                'action'  => 'Failed to Queue Vehicle',
                'subject' => 'Vehicle Queue Failure',
                'channel' => 'Web',
                'metadata' => [
                    'ip_address' => request()->ip(),
                    'message' => "{$responseData['message']} (Vehicle ID: {$this->selectedVehicle->id}).",
                ],
            ]);
        }
    }

    #[Computed]
    public function selectedVehicle()
    {
        $operator = null;

        if ($this->cashMode && $this->cash_operator_id) {
            $operator = User::find($this->cash_operator_id);
        } elseif (!$this->cashMode && $this->cardRecord?->user && $this->cardRecord->user->role === 'operator') {
            $operator = $this->cardRecord->user;
        }

        if (!$operator || !$this->route_list_id) {
            return null;
        }

        return $operator->vehicles->where('id', $this->route_list_id)->first();
    }

    #[Computed]
    public function selectedOperator()
    {
        if ($this->cashMode && $this->cash_operator_id) {
            return User::find($this->cash_operator_id);
        }

        if (!$this->cashMode && $this->cardRecord && $this->cardRecord->user && $this->cardRecord->user->role === 'operator') {
            return $this->cardRecord->user;
        }

        return null;
    }

    #[Computed]
    public function cardRecord() {
        if (empty($this->card_number)) {
            return null;
        }

        return Card::with('user.vehicles.route_list.operatorTicketRate')
            ->where('uid', $this->card_number)
            ->first();
    }

    #[Computed]
    public function allOperators()
    {
        return User::where('role', 'operator')->has('vehicles')->get();
    }

    #[Computed]
    public function filteredOperators()
    {
        if (empty($this->operatorSearch)) {
            return collect();
        }

        return $this->allOperators->filter(function ($op) {
            return stripos($op->name, $this->operatorSearch) !== false;
        });
    }

    #[Computed]
    public function queueFee()
    {
        if ($this->selectedVehicle && $this->selectedVehicle->route_list && $this->selectedVehicle->route_list->operatorTicketRate) {
            return $this->selectedVehicle->route_list->operatorTicketRate->queueing_fee;
        }
        return 0;
    }

    #[Computed]
    public function change()
    {
        if ($this->amount_received && $this->queueFee > 0) {
            return max(0, $this->amount_received - $this->queueFee);
        }
        return 0;
    }

    public function updatedRouteListId()
    {
        // Pre-fill from the vehicle's registered driver, but leave it editable —
        // a different driver can show up for the same vehicle on a given day.
        $this->driver_name = $this->selectedVehicle?->driver_name ?? '';
    }

    public function selectOperator($id)
    {
        $operator = User::find($id);
        if ($operator) {
            $this->cash_operator_id = $id;
            $this->operatorSearch = $operator->name;
            $this->route_list_id = null;
            $this->amount_received = null;
            $this->driver_name = '';
        }
    }

    public function updatedOperatorSearch()
    {
        if ($this->cash_operator_id) {
            $this->cash_operator_id = null;
            $this->route_list_id = null;
            $this->amount_received = null;
        }
    }

    public function updatedCardNumber()
    {
        $this->route_list_id = null;
        $this->driver_name = '';

        if ($this->cardRecord) {
            if ($this->cardRecord->user->role === 'operator') {
                $this->operator_name = $this->cardRecord->user->name;
                $this->card_state = 'success';
                $this->cashMode = false;
                $this->cash_operator_id = null;
                $this->operatorSearch = '';
            } else {
                $this->operator_name = '';
                $this->card_state = 'warn';
                $this->card_number = '';
                $this->showCommuterAlert = true;
            }
        }
    }

    public function enableCashMode()
    {
        $this->cashMode = true;
        $this->card_number = '';
        $this->operator_name = '';
        $this->card_state = 'ready';
        $this->cash_operator_id = null;
        $this->operatorSearch = '';
        $this->route_list_id = null;
        $this->driver_name = '';
        $this->amount_received = null;
    }

    public function disableCashMode()
    {
        $this->cashMode = false;
        $this->cash_operator_id = null;
        $this->operatorSearch = '';
        $this->route_list_id = null;
        $this->driver_name = '';
        $this->amount_received = null;
    }

    public function updatedCashOperatorId()
    {
        $this->route_list_id = null;
        $this->amount_received = null;
        if ($this->cash_operator_id) {
            $operator = User::find($this->cash_operator_id);
            $this->operator_name = $operator ? $operator->name : '';
        }
    }

    public function cardScanned(): void
    {
        // handled by updatedCardNumber
    }

    public function clearCard(): void
    {
        $this->card_number = '';
        $this->operator_name = '';
        $this->driver_name = '';
        $this->route_list_id = null;
        $this->card_state = 'ready';
        $this->cashMode = false;
        $this->cash_operator_id = null;
        $this->operatorSearch = '';
        $this->amount_received = null;
    }

    public function cardBlurred(): void
    {
        if ($this->card_state !== 'success') {
            $this->card_state = 'warn';
        }
    }

    public function cardFocused(): void
    {
        if ($this->card_state !== 'success') {
            $this->card_state = 'ready';
        }
    }

    public function refocus(): void
    {
        $this->card_state = 'ready';
        $this->dispatch('focus-rfid-input');
    }

    public function render()
    {
        $role = auth()->user()->role;

        return $this->view()->layout('layouts.' . $role . '-layout');
    }
};
?>

<div>

    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-2 sm:gap-4 mb-6">
        <flux:breadcrumbs class="order-1 sm:order-2 shrink-0 sm:pt-1">
            <flux:breadcrumbs.item href="{{ route('user.queue') }}" wire:navigate>Back to Live Queue</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>Queue Vehicle</flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div class="order-2 sm:order-1 w-full sm:w-auto">
            <x-heading
                size="xl"
                class="!font-primary !font-bold !text-light-txt-primary dark:!text-dark-txt-primary"
                style="font-size: var(--text-page-title)"
            >
                Queue Vehicle
            </x-heading>
            <x-text variant="subtle" class="!font-secondary mt-1 block" style="font-size: var(--text-helper)">
                Tap an RFID card to queue an operator's vehicle, or pay with cash.
            </x-text>
        </div>
    </div>

        <x-card class="!p-0">
            <div @class([
                'flex items-center gap-3 p-4 rounded-t-xl border-b',
                'bg-primary/5 dark:bg-primary/10 border-primary/10 dark:border-primary/20'    => $card_state === 'ready',
                'bg-success/10 dark:bg-dark-success/10 border-success/20 dark:border-dark-success/20' => $card_state === 'success',
                'bg-danger/10 dark:bg-dark-danger/10 border-danger/20 dark:border-dark-danger/20'     => $card_state === 'warn',
            ])>
                <flux:icon
                    :name="$card_state === 'success' ? 'check-circle' : ($card_state === 'warn' ? 'exclamation-triangle' : 'credit-card')"
                    @class([
                        'w-5 h-5 shrink-0',
                        'text-primary dark:text-dark-txt-primary'      => $card_state === 'ready',
                        'text-success dark:text-dark-success'          => $card_state === 'success',
                        'text-danger dark:text-dark-danger'            => $card_state === 'warn',
                    ])
                />

                <div class="flex-1 min-w-0">
                    <p @class([
                        'font-secondary text-sm font-medium',
                        'text-light-txt-primary dark:text-dark-txt-primary' => $card_state === 'ready',
                        'text-success dark:text-dark-success'                => $card_state === 'success',
                        'text-danger dark:text-dark-danger'                  => $card_state === 'warn',
                    ])>
                        @if($card_state === 'ready') Get your RFID card ready
                        @elseif($card_state === 'success') Card scanned successfully
                        @else Input field lost focus or invalid card
                        @endif
                    </p>
                    <p @class([
                        'font-secondary text-xs',
                        'text-light-txt-muted dark:text-dark-txt-muted' => $card_state === 'ready',
                        'text-success/80 dark:text-dark-success/80'     => $card_state === 'success',
                        'text-danger/80 dark:text-dark-danger/80'       => $card_state === 'warn',
                    ])>
                        @if($card_state === 'ready')
                            Hold the card near the reader — the number fills in automatically
                        @elseif($card_state === 'success')
                            UID {{ $card_number }} captured
                        @else
                            {{ $card_state === 'warn' && $card_number ? 'This card does not belong to an operator.' : 'Click the input field below to re‑focus, then tap the card' }}
                        @endif
                    </p>
                </div>

                @if($card_state === 'success')
                    <button wire:click="clearCard"
                        class="text-light-txt-muted hover:text-light-txt-primary dark:text-dark-txt-muted dark:hover:text-dark-txt-primary transition shrink-0"
                        aria-label="Scan a different card">
                        <flux:icon name="x-mark" class="w-5 h-5" />
                    </button>
                @endif
            </div>

            <div class="p-5 space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-end gap-4">
                    <div class="flex-1 min-w-[200px]">
                        <flux:field>
                            <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary flex items-center gap-1.5">
                                <flux:icon name="credit-card" class="w-3.5 h-3.5" />
                                Card UID
                            </flux:label>
                            <x-input
                                id="rfid-input"
                                wire:model.live.debounce.300ms="card_number"
                                wire:focus="cardFocused"
                                wire:blur="cardBlurred"
                                placeholder="Tap your card on the reader..."
                                autocomplete="off"
                                class="font-mono tracking-widest mt-1"
                                autofocus
                                :disabled="$cashMode"
                            />
                            @error('card_number')
                                <flux:error>{{ $message }}</flux:error>
                            @enderror
                        </flux:field>
                    </div>
                    <div class="shrink-0 flex flex-col gap-1.5 w-full sm:w-auto">
                        <span class="text-xs invisible select-none hidden sm:block" aria-hidden="true">Actions</span>
                        <div class="flex items-center mb-1 gap-2 h-9 w-full sm:w-auto">
                            <flux:button
                                wire:click="enableCashMode"
                                variant="ghost"
                                size="sm"
                                class="font-secondary w-full sm:w-auto justify-center"
                                :disabled="$cashMode || $card_state === 'success'"
                            >
                                Pay with Cash
                            </flux:button>
                            @if($cashMode)
                                <flux:button
                                    wire:click="disableCashMode"
                                    variant="danger"
                                    size="sm"
                                    class="font-secondary w-full sm:w-auto justify-center"
                                >
                                    Cancel Cash
                                </flux:button>
                            @endif
                        </div>
                    </div>
                </div>

                @if($cashMode)
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-1 border-t border-light-bd-default dark:border-dark-bd-default">
                        <flux:field class="pt-4">
                            <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">
                                Search Operator
                            </flux:label>
                            <div class="relative">
                                <x-input
                                    wire:model.live.debounce.300ms="operatorSearch"
                                    placeholder="Type operator name..."
                                    class="w-full mt-1"
                                    autocomplete="off"
                                />
                                @if(!empty($this->operatorSearch) && is_null($this->cash_operator_id))
                                    <div class="absolute z-20 w-full mt-1 bg-light-secondary dark:bg-dark-subtle border border-light-bd-strong dark:border-dark-bd-strong rounded-lg shadow-xl max-h-48 overflow-y-auto">
                                        @if($this->filteredOperators->count())
                                            @foreach($this->filteredOperators as $op)
                                                <div
                                                    wire:click="selectOperator({{ $op->id }})"
                                                    class="px-3 py-2 hover:bg-light-subtle dark:hover:bg-dark-primary cursor-pointer font-secondary text-sm text-light-txt-body dark:text-dark-txt-body"
                                                >
                                                    {{ $op->name }}
                                                </div>
                                            @endforeach
                                        @else
                                            <div class="px-3 py-2 font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">No operator found.</div>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </flux:field>

                        <flux:field class="pt-4">
                            <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">
                                Amount Received
                            </flux:label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3 mt-1 text-light-txt-muted dark:text-dark-txt-muted">₱</span>
                                <x-input
                                    wire:model.live.debounce.300ms="amount_received"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    placeholder="0.00"
                                    class="pl-7 mt-1"
                                />
                            </div>
                        </flux:field>
                    </div>
                @endif
            </div>
        </x-card>

        @if($this->selectedOperator)
            <div class="mt-6 grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">

                <div class="lg:col-span-2 space-y-6">
                    <x-card class="!p-0 overflow-hidden">
                        <div class="px-4 sm:px-5 py-3 border-b border-light-bd-default dark:border-dark-bd-default bg-light-secondary/50 dark:bg-dark-secondary/50">
                            <h3 class="font-primary text-card-title font-semibold text-light-txt-primary dark:text-dark-txt-primary">
                                Operator's Details
                            </h3>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 p-4 sm:p-5">
                            <flux:field>
                                <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary flex items-center gap-1.5 mb-2">
                                    Operator name
                                    <span class="text-light-txt-muted dark:text-dark-txt-muted font-normal">&middot; {{ $cashMode ? 'selected' : 'from card' }}</span>
                                </flux:label>
                                <div class="flex items-center gap-2 h-9 px-3 rounded-lg bg-light-subtle dark:bg-dark-subtle border border-light-bd-default dark:border-dark-bd-default">
                                    <flux:icon name="user" class="w-3.5 h-3.5 text-light-txt-muted dark:text-dark-txt-muted shrink-0" />
                                    <span class="font-secondary text-sm text-light-txt-body dark:text-dark-txt-body truncate">{{ $this->selectedOperator->name }}</span>
                                </div>
                            </flux:field>

                            <flux:field>
                                <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary mb-2">
                                    Driver name
                                    <span class="text-light-txt-muted dark:text-dark-txt-muted font-normal">
                                        &middot; {{ ! $this->selectedVehicle ? 'select a vehicle first' : ($this->selectedVehicle->driver_name ? 'from vehicle record, editable' : 'no driver on file') }}
                                    </span>
                                </flux:label>
                                <x-input
                                    wire:model="driver_name"
                                    placeholder="{{ $this->selectedVehicle ? 'Enter driver\'s name' : 'Select a vehicle to enable this field' }}"
                                    size="sm"
                                    :disabled="!$this->selectedVehicle"
                                />
                            </flux:field>
                        </div>
                    </x-card>

                    <x-card class="!p-0 overflow-hidden">
                        <div class="px-4 sm:px-5 py-3 border-b border-light-bd-default dark:border-dark-bd-default bg-light-secondary/50 dark:bg-dark-secondary/50">
                            <h3 class="font-primary text-card-title font-semibold text-light-txt-primary dark:text-dark-txt-primary">
                                Vehicles
                            </h3>
                        </div>

                        <flux:radio.group wire:model.live="route_list_id" class="flex flex-col gap-2 w-full p-4 sm:p-5">
                            @forelse ($this->selectedOperator->vehicles as $vehicle)
                                <label @class([
                                    'flex items-center justify-between gap-3 p-3 rounded-lg border cursor-pointer transition',
                                    'border-primary/30 bg-primary/5 dark:border-primary/40 dark:bg-primary/10' => (string) $route_list_id === (string) $vehicle->id,
                                    'border-light-bd-default dark:border-dark-bd-default hover:bg-light-subtle dark:hover:bg-dark-subtle' => (string) $route_list_id !== (string) $vehicle->id,
                                ])>
                                    <div class="min-w-0">
                                        <p @class([
                                            'font-secondary text-sm font-medium truncate',
                                            'text-light-txt-primary dark:text-dark-txt-primary' => (string) $route_list_id === (string) $vehicle->id,
                                            'text-light-txt-body dark:text-dark-txt-body' => (string) $route_list_id !== (string) $vehicle->id,
                                        ])>
                                            {{ $vehicle->vehicle_type ?? 'Unknown Vehicle' }} &middot; {{ $vehicle->plate_number ?? 'No Plate' }}
                                        </p>
                                        <p @class([
                                            'font-secondary text-xs mt-0.5 truncate',
                                            'text-light-txt-muted dark:text-dark-txt-muted',
                                        ])>
                                            Iriga Terminal to {{ $vehicle->route_list?->terminal ?? 'N/A' }}
                                        </p>
                                    </div>
                                    <flux:radio value="{{ $vehicle->id }}" class="shrink-0" />
                                </label>
                            @empty
                                <p class="font-secondary text-xs text-light-txt-muted dark:text-dark-txt-muted py-4 text-center">This operator has no vehicles.</p>
                            @endforelse
                        </flux:radio.group>
                    </x-card>
                </div>

                <div class="lg:col-span-1 lg:sticky lg:top-4 space-y-4">
                    <x-card class="!p-0 overflow-hidden">
                        <div class="px-4 sm:px-5 py-3 border-b border-light-bd-default dark:border-dark-bd-default bg-light-secondary/50 dark:bg-dark-secondary/50">
                            <h3 class="font-primary text-card-title font-semibold text-light-txt-primary dark:text-dark-txt-primary">
                                Summary
                            </h3>
                        </div>

                        <div class="p-4 sm:p-5">
                            @if ($this->selectedVehicle)
                                <div class="divide-y divide-light-bd-default dark:divide-dark-bd-default font-secondary text-sm">
                                    <div class="flex items-center justify-between py-1.5">
                                        <span class="text-light-txt-muted dark:text-dark-txt-muted">Plate no.</span>
                                        <span class="font-medium text-light-txt-primary dark:text-dark-txt-primary">{{ $this->selectedVehicle->plate_number }}</span>
                                    </div>
                                    <div class="flex items-center justify-between py-1.5">
                                        <span class="text-light-txt-muted dark:text-dark-txt-muted">Vehicle type</span>
                                        <span class="text-light-txt-body dark:text-dark-txt-body">{{ $this->selectedVehicle->vehicle_type }}</span>
                                    </div>
                                    <div class="flex items-center justify-between py-1.5">
                                        <span class="text-light-txt-muted dark:text-dark-txt-muted">Route</span>
                                        <span class="text-light-txt-body dark:text-dark-txt-body">Iriga &rarr; {{ $this->selectedVehicle->route_list?->terminal ?? 'N/A' }}</span>
                                    </div>
                                    <div class="flex items-center justify-between py-1.5">
                                        <span class="text-light-txt-muted dark:text-dark-txt-muted">Driver</span>
                                        <span class="text-light-txt-body dark:text-dark-txt-body">{{ $driver_name ?: '—' }}</span>
                                    </div>
                                    @if($cashMode)
                                        <div class="flex items-center justify-between py-1.5">
                                            <span class="text-light-txt-muted dark:text-dark-txt-muted">Queue fee</span>
                                            <span class="font-medium text-light-txt-primary dark:text-dark-txt-primary">₱{{ number_format($this->queueFee, 2) }}</span>
                                        </div>
                                        <div class="flex items-center justify-between py-1.5">
                                            <span class="text-light-txt-muted dark:text-dark-txt-muted">Amount received</span>
                                            <span class="text-light-txt-body dark:text-dark-txt-body">{{ $this->amount_received ? '₱'.number_format($this->amount_received, 2) : '—' }}</span>
                                        </div>
                                        <div class="flex items-center justify-between py-1.5">
                                            <span class="text-light-txt-muted dark:text-dark-txt-muted">Change</span>
                                            <span class="font-semibold text-success dark:text-dark-success">₱{{ number_format($this->change, 2) }}</span>
                                        </div>
                                    @endif
                                    <div class="flex items-center justify-between pt-3">
                                        <span class="text-light-txt-muted dark:text-dark-txt-muted">Tickets price</span>
                                        <span class="font-semibold text-base text-light-txt-primary dark:text-dark-txt-primary">
                                            ₱{{ number_format($this->selectedVehicle->route_list?->operatorTicketRate?->queueing_fee ?? 0, 2) }}
                                        </span>
                                    </div>
                                </div>
                            @else
                                <div class="flex flex-col items-center justify-center py-6 text-center text-light-txt-muted dark:text-dark-txt-muted">
                                    <flux:icon name="cursor-arrow-rays" class="w-8 h-8 mb-2 stroke-1" />
                                    <x-text variant="subtle" class="!font-secondary" style="font-size: var(--text-timestamp)">Select a vehicle to see its breakdown</x-text>
                                </div>
                            @endif

                            <x-button
                                variant="primary"
                                size="sm"
                                class="mt-4 w-full !font-secondary"
                                :disabled="!$this->selectedVehicle"
                                wire:click="queueVehicle"
                            >
                                Queue this vehicle
                            </x-button>
                        </div>
                    </x-card>
                </div>

            </div>
        @else
            <x-card class="!rounded-xl !border !border-dashed !border-light-bd-strong dark:!border-dark-bd-strong !bg-light-secondary dark:!bg-dark-secondary !text-center !p-8 mt-6">
                <flux:icon name="identification" class="w-8 h-8 mx-auto text-light-txt-muted dark:text-dark-txt-muted mb-2" />
                <x-text variant="subtle" class="!font-secondary block" style="font-size: var(--text-table-row)">
                    No operator selected yet.
                </x-text>
                <x-text variant="subtle" class="!font-secondary block mt-1" style="font-size: var(--text-timestamp)">
                    Tap an operator's RFID card, or use "Pay with Cash" to search for one.
                </x-text>
            </x-card>
        @endif

    <flux:modal wire:model.live="showCommuterAlert" class="max-w-sm">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg">Invalid Card</flux:heading>
                <flux:subheading>This card belongs to a commuter.</flux:subheading>
            </div>
            <x-text variant="subtle" class="!font-secondary block" style="font-size: var(--text-table-row)">
                Only operator cards can be used for queue payments. Please scan an operator card or use the <strong class="text-light-txt-primary dark:text-dark-txt-primary">Pay with Cash</strong> option.
            </x-text>
            <div class="flex justify-end">
                <flux:button wire:click="$set('showCommuterAlert', false)" variant="primary">
                    Got it
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal wire:model.live="showInsufficientAmountAlert" class="max-w-sm">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg" class="!text-danger dark:!text-dark-danger">Insufficient Amount</flux:heading>
                <flux:subheading>Amount received is less than the queue fee.</flux:subheading>
            </div>
            <x-text variant="subtle" class="!font-secondary block" style="font-size: var(--text-table-row)">
                The queue fee is <strong class="text-light-txt-primary dark:text-dark-txt-primary">₱{{ number_format($this->queueFee, 2) }}</strong>. Please enter an amount equal to or greater than the fee.
            </x-text>
            <div class="flex justify-end">
                <flux:button wire:click="$set('showInsufficientAmountAlert', false)" variant="primary">
                    Got it
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
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
use App\Http\Controllers\Api\CardController;

use App\Services\AuditLogsService;

new #[Layout('layouts.cashier-layout')] class extends Component
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

    public function selectOperator($id)
    {
        $operator = User::find($id);
        if ($operator) {
            $this->cash_operator_id = $id;
            $this->operatorSearch = $operator->name;
            $this->route_list_id = null;
            $this->amount_received = null;
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
};
?>

<div>
    <div class="mx-auto max-w-5xl px-4 sm:px-6 py-8">

        <div class="flex flex-wrap items-center justify-between gap-2 mb-6">
            <flux:breadcrumbs>
                <flux:breadcrumbs.item href="{{ route('user.queue') }}" wire:navigate>Live Queue</flux:breadcrumbs.item>
                <flux:breadcrumbs.item>Queue Vehicle</flux:breadcrumbs.item>
            </flux:breadcrumbs>

            <flux:button
                href="{{ route('user.queue') }}"
                wire:navigate
                variant="ghost"
                icon="arrow-left"
                size="sm"
                class="font-secondary"
            >
                Back to Live Queue
            </flux:button>
        </div>

        <div class="mb-6">
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

        <x-card class="overflow-hidden">
            <div @class([
                'flex items-center gap-3 p-4 rounded-t-xl border-b',
                'bg-blue-50 dark:bg-blue-950/40 border-blue-100 dark:border-blue-900'   => $card_state === 'ready',
                'bg-green-50 dark:bg-green-950/40 border-green-100 dark:border-green-900' => $card_state === 'success',
                'bg-red-50 dark:bg-red-950/40 border-red-100 dark:border-red-900'     => $card_state === 'warn',
            ])>
                <flux:icon
                    :name="$card_state === 'success' ? 'check-circle' : ($card_state === 'warn' ? 'exclamation-triangle' : 'credit-card')"
                    @class([
                        'w-5 h-5 shrink-0',
                        'text-blue-600 dark:text-blue-400'   => $card_state === 'ready',
                        'text-green-600 dark:text-green-400' => $card_state === 'success',
                        'text-red-600 dark:text-red-400'     => $card_state === 'warn',
                    ])
                />

                <div class="flex-1 min-w-0">
                    <p @class([
                        'text-sm font-medium',
                        'text-blue-900 dark:text-blue-100'   => $card_state === 'ready',
                        'text-green-900 dark:text-green-100' => $card_state === 'success',
                        'text-red-900 dark:text-red-100'     => $card_state === 'warn',
                    ])>
                        @if($card_state === 'ready') Get your RFID card ready
                        @elseif($card_state === 'success') Card scanned successfully
                        @else Input field lost focus or invalid card
                        @endif
                    </p>
                    <p @class([
                        'text-xs',
                        'text-blue-600 dark:text-blue-300'   => $card_state === 'ready',
                        'text-green-600 dark:text-green-300' => $card_state === 'success',
                        'text-red-600 dark:text-red-300'     => $card_state === 'warn',
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
                        class="text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200 transition shrink-0"
                        aria-label="Scan a different card">
                        <flux:icon name="x-mark" class="w-5 h-5" />
                    </button>
                @endif
            </div>

            <div class="p-5 space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-end gap-4">
                    <div class="flex-1 min-w-[200px]">
                        <flux:field>
                            <flux:label class="flex items-center gap-1.5 text-xs">
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
                                class="font-mono tracking-widest"
                                autofocus
                                :disabled="$cashMode"
                            />
                            @error('card_number')
                                <flux:error>{{ $message }}</flux:error>
                            @enderror
                        </flux:field>
                    </div>
                    <div class="shrink-0 flex flex-col gap-1.5">
                        <span class="text-xs invisible select-none hidden sm:block" aria-hidden="true">Actions</span>
                        <div class="flex items-center mb-1 gap-2 h-9">
                            <flux:button
                                wire:click="enableCashMode"
                                variant="ghost"
                                size="sm"
                                class="font-secondary"
                                :disabled="$cashMode || $card_state === 'success'"
                            >
                                Pay with Cash
                            </flux:button>
                            @if($cashMode)
                                <flux:button
                                    wire:click="disableCashMode"
                                    variant="ghost"
                                    size="sm"
                                    class="bg-red-500! font-secondary text-white! dark:text-red-400"
                                >
                                    Cancel Cash
                                </flux:button>
                            @endif
                        </div>
                    </div>
                </div>

                @if($cashMode)
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-1 border-t border-zinc-100 dark:border-zinc-800">
                        <flux:field class="pt-4">
                            <flux:label class="text-xs">Search Operator</flux:label>
                            <div class="relative">
                                <x-input
                                    wire:model.live.debounce.300ms="operatorSearch"
                                    placeholder="Type operator name..."
                                    class="w-full"
                                    autocomplete="off"
                                />
                                @if(!empty($this->operatorSearch) && is_null($this->cash_operator_id))
                                    <div class="absolute z-10 w-full mt-1 bg-white dark:bg-dark-secondary border border-zinc-200 dark:border-dark-bd-default rounded-lg shadow-lg max-h-48 overflow-y-auto">
                                        @if($this->filteredOperators->count())
                                            @foreach($this->filteredOperators as $op)
                                                <div
                                                    wire:click="selectOperator({{ $op->id }})"
                                                    class="px-3 py-2 hover:bg-zinc-100 dark:hover:bg-dark-subtle cursor-pointer text-sm text-zinc-700 dark:text-zinc-200"
                                                >
                                                    {{ $op->name }}
                                                </div>
                                            @endforeach
                                        @else
                                            <div class="px-3 py-2 text-sm text-zinc-500 dark:text-zinc-400">No operator found.</div>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </flux:field>

                        <flux:field class="pt-4">
                            <flux:label class="text-xs">Amount Received</flux:label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-zinc-400 dark:text-zinc-500">₱</span>
                                <x-input
                                    wire:model.live.debounce.300ms="amount_received"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    placeholder="0.00"
                                    class="pl-7"
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
                    <x-card>
                        <div>
                            <x-pages-heading class="mt-0!">
                                Operator's Details
                            </x-pages-heading>

                            <div class="grid grid-cols-1 mt-5! sm:grid-cols-2 gap-4">
                                <flux:field>
                                    <flux:label class="text-xs flex items-center gap-1.5 mb-2">
                                        Operator name
                                        <span class="text-zinc-400 dark:text-zinc-500 font-normal">· {{ $cashMode ? 'selected' : 'from card' }}</span>
                                    </flux:label>
                                    <div class="flex items-center gap-2 h-9 px-3 rounded-lg bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700">
                                        <flux:icon name="user" class="w-3.5 h-3.5 text-zinc-400 shrink-0" />
                                        <span class="text-sm text-zinc-700 dark:text-zinc-200 truncate">{{ $this->selectedOperator->name }}</span>
                                    </div>
                                </flux:field>

                                <flux:field>
                                    <flux:label class="text-xs mb-2">Driver name</flux:label>
                                    <x-input wire:model="driver_name" placeholder="Enter driver's name" class="h-9" />
                                </flux:field>
                            </div>
                        </div>
                    </x-card>

                    <x-card>
                        <div>
                            <x-pages-heading class="!mt-0">
                                Vehicles
                            </x-pages-heading>

                            <flux:radio.group wire:model.live="route_list_id" class="mt-5! flex flex-col gap-2 w-full">
                                @forelse ($this->selectedOperator->vehicles as $vehicle)
                                    <label @class([
                                        'flex items-center justify-between gap-3 p-3 rounded-lg border cursor-pointer transition',
                                        'border-blue-300 bg-blue-50 dark:border-blue-700 dark:bg-blue-950/40' => (string) $route_list_id === (string) $vehicle->id,
                                        'border-zinc-200 dark:border-zinc-700 hover:bg-zinc-50 dark:hover:bg-zinc-800/50' => (string) $route_list_id !== (string) $vehicle->id,
                                    ])>
                                        <div class="min-w-0">
                                            <p @class([
                                                'text-sm font-medium truncate',
                                                'text-blue-900 dark:text-blue-100' => (string) $route_list_id === (string) $vehicle->id,
                                            ])>
                                                {{ $vehicle->vehicle_type ?? 'Unknown Vehicle' }} · {{ $vehicle->plate_number ?? 'No Plate' }}
                                            </p>
                                            <p @class([
                                                'text-xs mt-0.5 truncate',
                                                'text-blue-600 dark:text-blue-300' => (string) $route_list_id === (string) $vehicle->id,
                                                'text-zinc-500 dark:text-zinc-400' => (string) $route_list_id !== (string) $vehicle->id,
                                            ])>
                                                Iriga Terminal to {{ $vehicle->route_list?->terminal ?? 'N/A' }}
                                            </p>
                                        </div>
                                        <flux:radio value="{{ $vehicle->id }}" class="shrink-0" />
                                    </label>
                                @empty
                                    <p class="text-xs text-zinc-400 py-4 text-center">This operator has no vehicles.</p>
                                @endforelse
                            </flux:radio.group>
                        </div>
                    </x-card>
                </div>

                <div class="lg:col-span-1 lg:sticky lg:top-4 space-y-4">
                    <x-card>
                        <div>
                        <x-pages-heading class="mt-0!">
                            Summary
                        </x-pages-heading>

                        <div class="border-t mt-5! border-zinc-100 dark:border-zinc-800 pt-3">
                            @if ($this->selectedVehicle)
                                <table class="w-full text-sm">
                                    <tbody>
                                        <tr>
                                            <td class="text-zinc-500 dark:text-zinc-400 py-1.5">Plate no.</td>
                                            <td class="text-right font-medium py-1.5">{{ $this->selectedVehicle->plate_number }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-zinc-500 dark:text-zinc-400 py-1.5">Vehicle type</td>
                                            <td class="text-right py-1.5">{{ $this->selectedVehicle->vehicle_type }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-zinc-500 dark:text-zinc-400 py-1.5">Route</td>
                                            <td class="text-right py-1.5">Iriga → {{ $this->selectedVehicle->route_list?->terminal ?? 'N/A' }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-zinc-500 dark:text-zinc-400 py-1.5">Driver</td>
                                            <td class="text-right py-1.5">{{ $driver_name ?: '—' }}</td>
                                        </tr>
                                        @if($cashMode)
                                            <tr>
                                                <td class="text-zinc-500 dark:text-zinc-400 py-1.5">Queue fee</td>
                                                <td class="text-right font-medium py-1.5">₱{{ number_format($this->queueFee, 2) }}</td>
                                            </tr>
                                            <tr>
                                                <td class="text-zinc-500 dark:text-zinc-400 py-1.5">Amount received</td>
                                                <td class="text-right py-1.5">{{ $this->amount_received ? '₱'.number_format($this->amount_received, 2) : '—' }}</td>
                                            </tr>
                                            <tr>
                                                <td class="text-zinc-500 dark:text-zinc-400 py-1.5">Change</td>
                                                <td class="text-right font-semibold text-green-600 dark:text-green-400 py-1.5">₱{{ number_format($this->change, 2) }}</td>
                                            </tr>
                                        @endif
                                        <tr class="border-t border-zinc-100 dark:border-zinc-800">
                                            <td class="text-zinc-500 dark:text-zinc-400 pt-3">Tickets price</td>
                                            <td class="text-right font-semibold text-base pt-3">
                                                ₱{{ number_format($this->selectedVehicle->route_list?->operatorTicketRate?->queueing_fee ?? 0, 2) }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            @else
                                <div class="flex flex-col items-center justify-center py-6 text-center text-zinc-400 dark:text-zinc-500">
                                    <flux:icon name="cursor-arrow-rays" class="w-8 h-8 mb-2 stroke-1" />
                                    <p class="text-xs">Select a vehicle to see its breakdown</p>
                                </div>
                            @endif
                        </div>

                        <x-button
                            size="sm"
                            class="mt-4 w-full"
                            :disabled="!$this->selectedVehicle"
                            wire:click="queueVehicle"
                        >
                            Queue this vehicle
                        </x-button>
                        </div>
                    </x-card>
                </div>

            </div>
        @endif

    </div>

    <flux:modal wire:model.live="showCommuterAlert" class="max-w-sm">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg">Invalid Card</flux:heading>
                <flux:subheading>This card belongs to a commuter.</flux:subheading>
            </div>
            <p class="text-sm text-zinc-600 dark:text-zinc-300">
                Only operator cards can be used for queue payments. Please scan an operator card or use the <strong>Pay with Cash</strong> option.
            </p>
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
                <flux:heading size="lg" class="text-red-600 dark:text-red-400">Insufficient Amount</flux:heading>
                <flux:subheading>Amount received is less than the queue fee.</flux:subheading>
            </div>
            <p class="text-sm text-zinc-600 dark:text-zinc-300">
                The queue fee is <strong>₱{{ number_format($this->queueFee, 2) }}</strong>. Please enter an amount equal to or greater than the fee.
            </p>
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
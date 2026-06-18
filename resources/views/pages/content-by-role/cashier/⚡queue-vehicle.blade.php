<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Illuminate\Http\Request;

use App\Models\Card;
use App\Models\RouteList;
use App\Models\Vehicle;
use App\Http\Controllers\Api\CardController;

new #[Layout('layouts.cashier-layout')] class extends Component
{
    public bool $card_focused = true;
    public string $card_state = 'ready';
    public string $card_number = '';
    public string $operator_name = '';
    public string $driver_name = '';
    public string $operator_tickets_price;

    public $route_list_id; 

    public function queueVehicle() {
        $request = new Request();
        $request->merge([
            'uid'              => $this->card_number,
            'driver_name'      => $this->driver_name,
            'vehicle_id'       => $this->selectedVehicle->id,
            'transaction_type' => 'operator_payment',
            'amount'           => $this->selectedVehicle->route_list->operatorTicketRate->base_fare,
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
        } 
        else {
            Flux::toast(
                variant: 'warning',
                heading: 'Failed to Queue Vehicle',
                text: $responseData['message'] ?? 'An error occurred while queuing the vehicle.'
            );
        } 
    }

    #[Computed]
    public function selectedVehicle()
    {
        if (!$this->route_list_id || !$this->cardRecord?->user) {
            return null;
        }

        return $this->cardRecord->user->vehicles
            ->where('id', $this->route_list_id)
            ->first();
    }

    #[Computed]
    public function cardRecord() {
        if (empty($this->card_number)) {
            return null;
        }

        return Card::with('user.vehicles.route_list') // route_list = hasMany
            ->where('uid', $this->card_number)
            ->first();
    }

    public function setUserProperty(): void {
        $card = $this->cardRecord;

        if ($card && $card->user) {
            $this->operator_name = $card->user->name;
        } else {
            $this->operator_name = '';
            $this->dispatch('notify', ['type' => 'error', 'message' => 'No operator linked to this card']);
        }
    }

    public function cardScanned(): void
    {
        $this->card_state = $this->card_number !== '' ? 'success' : 'ready';
        $this->route_list_id = null;
        $this->driver_name = '';

        if ($this->card_state === 'success') {
            $this->setUserProperty();
        }
    }

    public function clearCard(): void
    {
        $this->card_number = '';
        $this->operator_name = '';
        $this->driver_name = '';
        $this->route_list_id = null;
        $this->card_state = 'warn';
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

    <flux:breadcrumbs class="mb-6">
        <flux:breadcrumbs.item href="{{ route('cashier.queue') }}" wire:navigate>Live Queue</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>Queue Vehicles</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <x-card>
        <div @class([
            'flex items-center gap-3 p-4 rounded-t-xl',
            'bg-blue-50 dark:bg-blue-950/40'   => $card_state === 'ready',
            'bg-green-50 dark:bg-green-950/40' => $card_state === 'success',
            'bg-red-50 dark:bg-red-950/40'     => $card_state === 'warn',
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
                        Hold the card near the reader — the number fills in automatically
                    @elseif($card_state === 'success')
                        UID {{ $card_number }} captured — form reset for this operator
                    @else
                        Click the input field below to re-focus, then tap the card
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

        <div class="p-4">
            <flux:field class="mb-4">
                <flux:label class="flex items-center gap-1.5 text-xs">
                    <flux:icon name="credit-card" class="w-3.5 h-3.5" />
                    Card UID
                </flux:label>
                <x-input
                    id="rfid-input"
                    wire:model.live="card_number"
                    wire:keydown.enter="cardScanned"
                    wire:focus="cardFocused"
                    wire:blur="cardBlurred"
                    placeholder="Tap your card on the reader..."
                    autocomplete="off"
                    class="font-mono tracking-widest"
                    autofocus
                />
                @error('card_number')
                    <flux:error>{{ $message }}</flux:error>
                @enderror
            </flux:field>
        </div>
    </x-card>

    @if ($this->cardRecord && $this->cardRecord->user)
        <div class="mt-4 grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">

            <div class="lg:col-span-2 space-y-4">
                <x-card>
                    <x-pages-heading class="mt-1 mb-4">
                        Operator's Details
                    </x-pages-heading>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <flux:label class="text-xs flex items-center gap-1.5 mb-1.5">
                                Operator name
                                <span class="text-zinc-400 dark:text-zinc-500 font-normal">· from card</span>
                            </flux:label>
                            <div class="flex items-center gap-2 h-9 px-3 rounded-lg bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700">
                                <flux:icon name="lock-closed" class="w-3.5 h-3.5 text-zinc-400 shrink-0" />
                                <span class="text-sm text-zinc-700 dark:text-zinc-200 truncate">{{ $operator_name }}</span>
                            </div>
                        </div>

                        <flux:field>
                            <flux:label class="text-xs">Driver name</flux:label>
                            <x-input wire:model="driver_name" placeholder="Enter driver's name" />
                        </flux:field>
                    </div>
                </x-card>

                <x-card>
                    <x-pages-heading class="mt-1 mb-3">
                        Operator's Vehicles
                    </x-pages-heading>

                    <flux:radio.group wire:model.live="route_list_id" class="flex flex-col gap-2 w-full">
                        @forelse ($this->cardRecord->user->vehicles as $vehicle)
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
                            <p class="text-xs text-zinc-400 py-4 text-center">No vehicles linked to this operator.</p>
                        @endforelse
                    </flux:radio.group>
                </x-card>
            </div>

            <div class="lg:col-span-1 lg:sticky lg:top-4">
                <x-card>
                    <x-pages-heading class="mt-1 mb-3">
                        Summary
                    </x-pages-heading>

                    <div class="border-t border-zinc-100 dark:border-zinc-800 pt-3">
                        @if ($this->selectedVehicle)
                            <table class="w-full text-sm">
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
                                <tr class="border-t border-zinc-100 dark:border-zinc-800">
                                    <td class="text-zinc-500 dark:text-zinc-400 pt-3">Base fare</td>
                                    <td class="text-right font-semibold text-base pt-3">
                                        ₱{{ number_format($this->selectedVehicle->route_list?->operatorTicketRate?->base_fare ?? 0, 2) }}
                                    </td>
                                </tr>
                            </table>
                        @else
                            <div class="flex flex-col items-center justify-center py-6 text-center text-zinc-400 dark:text-zinc-500">
                                <flux:icon name="cursor-arrow-rays" class="w-8 h-8 mb-2 stroke-1" />
                                <p class="text-xs">Select a vehicle to see its breakdown</p>
                            </div>
                        @endif
                    </div>
                </x-card>

                <x-button
                    size="sm"
                    class="mt-4 w-full"
                    :disabled="!$this->selectedVehicle"
                    wire:click="queueVehicle"
                >
                    Queue this vehicle
                </x-button>
            </div>

        </div>
    @endif

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
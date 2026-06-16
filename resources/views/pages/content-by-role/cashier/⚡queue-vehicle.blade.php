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
    public function selectedVehicle() {
        if (!$this->route_list_id || !$this->cardRecord) {
            return null;
        }

        return Vehicle::with('route_list.operatorTicketRate')
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
        if ($this->card_state === 'success') {
            $this->setUserProperty();
        }
    }

    public function clearCard(): void
    {
        $this->card_number = '';
        $this->operator_name = '';
        $this->card_state  = 'warn';
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

            @if($card_state === 'success')
                {{-- Fixed: Changed method to clearCard so it resets layout state safely --}}
                <button wire:click="clearCard"
                    class="text-zinc-400 hover:text-zinc-600 transition"
                    aria-label="Clear card">
                    <flux:icon name="x-mark" class="w-6 h-6" />
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
            
            <div class="lg:col-span-2">
                <x-card class="h-full">
                    <x-pages-heading class="mt-1">
                        Operator's Details
                    </x-pages-heading>

                    <div class="space-y-6 mt-4">
                        <x-inputs-container>
                            <x-input wire:model="operator_name" label="Name" readonly/>
                            <x-input wire:model="driver_name" label="Driver Name" placeholder="Enter driver's name" />
                        </x-inputs-container>

                        <div>
                            <x-pages-heading class="mt-1 mb-3">
                                Operator's Vehicles
                            </x-pages-heading>

                            <flux:radio.group wire:model.live="route_list_id" variant="cards" class="grid grid-rows-1 md:grid-rows-1 gap-4 w-full">
                                @foreach ($this->cardRecord->user->vehicles as $vehicle)

                                    <flux:radio 
                                        value="{{ $vehicle->id }}" 
                                        label="{{ $vehicle->vehicle_type ?? 'Unknown Vehicle' }} - {{ $vehicle->plate_number ?? 'No Plate' }}"
                                        description="Iriga Terminal to {{ $vehicle->route_list?->terminal ?? 'N/A' }}" 
                                    />
                                @endforeach
                            </flux:radio.group>
                        </div>
                    </div>
                </x-card>
            </div>

            <div class="lg:col-span-1 lg:sticky lg:top-4">
                <x-card>
                    <x-pages-heading class="mt-1">
                        Summary
                    </x-pages-heading>
                    
                    <div class="mt-4 border-t border-zinc-100 dark:border-zinc-800 pt-4">

                        @if ($this->selectedVehicle)

                            <div class="flex items-start justify-between gap-4">
                                
                                <div class="flex flex-col gap-1 min-w-0">
                                    <x-text>
                                        {{ $this->driver_name }}
                                    </x-text>
                                    <span class="text-sm font-semibold text-zinc-900 dark:text-zinc-100 truncate">
                                        {{ $this->selectedVehicle->vehicle_type }}
                                    </span>
                                    <span class="text-xs font-mono text-zinc-500 uppercase tracking-wider">
                                        {{ $this->selectedVehicle->plate_number }}
                                    </span>
                                    <span class="text-xs text-zinc-600 dark:text-zinc-400 mt-1 line-clamp-2">
                                        Iriga → {{ $this->selectedVehicle?->route_list->terminal ?? 'N/A' }}
                                    </span>
                                </div>

                                <div class="shrink-0 text-right">
                                    <span class="text-base font-bold text-zinc-950 dark:text-white whitespace-nowrap">
                                        ₱ {{ number_format($this->selectedVehicle?->route_list->operatorTicketRate?->base_fare ?? 0, 2) }}
                                    </span>
                                </div>
                            </div>
                        @else
                            <div class="flex flex-col items-center justify-center py-6 text-center text-zinc-400 dark:text-zinc-500">
                                <flux:icon name="cursor-arrow-rays" class="w-8 h-8 mb-2 stroke-1" />
                                <p class="text-xs">Please select an operator vehicle to calculate the breakdown summary.</p>
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
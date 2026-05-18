<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate; 
use App\Models\Card;
use App\Http\Controllers\Api\CardController;
use Illuminate\Http\Request;

new #[Layout('components.dashboard.operator-dashboard')] class extends Component
{

    public $vehicles = [
        'Van'       => ['plate-number' => 'AS34DF', 'routes' => 'Naga', 'rates' => 50],
        'Multi-cab' => ['plate-number' => 'D4E5F6', 'routes' => 'Nabua', 'rates' => 15],
        'Jeep'      => ['plate-number' => 'G7H8I9', 'routes' => 'Pili', 'rates' => 25],
    ];

    #[Validate('required')]
    public $driver_name;
    
    #[Validate('required')]
    public $selectedVehicle;

    public $data;

    public function submit() {

        $this->validate();

        $card = Card::where('uid', auth()->user()->card->uid)->first();

        $request = new Request([
            'uid' => $card->uid,
            'name' => $this->driver_name,
            'transaction_type' => 'operator_payment',
            'amount' => $this->vehicles[$this->selectedVehicle]['rates'] ?? null,
            'destination' => $this->vehicles[$this->selectedVehicle]['routes'] ?? null,
            'vehicle_type' => $this->selectedVehicle,
            'plate_number' => $this->vehicles[$this->selectedVehicle]['plate-number'] ?? null,
        ]);

        $controller = app(CardController::class);
        $response = $controller->tap($request);

        $this->data = json_decode($response->getContent(), true);

    }
};


?>

<div>
    <form wire:submit="submit">

        <flux:field class="mb-4">
            <flux:label>Driver Name</flux:label>

            <flux:input name="driver_name" wire:model.="driver_name" required/>
            <flux:error name="driver_name" />
        </flux:field>

        <flux:radio.group wire:model.live="selectedVehicle" label="Select Vehicle" variant="cards" class="max-sm:flex-col mb-4">
            @foreach($vehicles as $vehicle => $details)
                <flux:radio 
                    value="{{ $vehicle }}" 
                    label="{{ $vehicle }}"  
                >
                    <x-slot name="description">
                        {{ $details['plate-number'] }} <br/> {{ $details['routes'] }} <br/> ₱{{ $details['rates'] }}
                    </x-slot>
                </flux:radio>
            @endforeach
        </flux:radio.group>

        {{-- Error Message --}}
        @error('driver_name')
            <p class="text-red-500 text-sm">{{ $message }}</p>
        @enderror
        
        @error('selectedVehicle')
            <p class="text-red-500 text-sm">{{ $message }}</p>
        @enderror

        {{-- Summary --}}
        @if($driver_name && $selectedVehicle)
            <div class="p-4 mb-4 bg-blue-50 border border-blue-100 rounded-lg shadow-sm">
                <h4 class="font-bold text-blue-900 mb-2">Assignment Summary</h4>
                <div class="text-sm text-blue-800 space-y-1">
                    <p><strong>Driver:</strong> {{ $driver_name }}</p>
                    <p><strong>Vehicle:</strong> {{ $selectedVehicle }}</p>
                    <p><strong>Plate Number:</strong> {{ $vehicles[$selectedVehicle]['plate-number'] }}</p>
                    <p><strong>Route:</strong> {{ $vehicles[$selectedVehicle]['routes'] }}</p>
                    <p><strong>Rate:</strong> ₱{{ $vehicles[$selectedVehicle]['rates'] }}</p>
                </div>
            </div>
        @endif
        
        @if ($data)
            <div class="p-4 mb-4 bg-blue-50 border border-blue-100 rounded-lg shadow-sm">
                <h4 class="font-bold text-blue-900 mb-2">Response</h4>
                <div class="text-sm text-blue-800 space-y-1">
                    <p><strong>Driver:</strong> {{ $this->data['message'] }}</p>
                </div>
            </div>
        @endif

        <flux:button type="submit" variant="primary" class="w-full">
           Confirm
        </flux:button>
    </form>
</div>
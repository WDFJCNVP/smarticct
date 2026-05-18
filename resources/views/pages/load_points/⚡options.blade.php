<?php

use Livewire\Component;
use Livewire\Attributes\Validate; 
use Livewire\Attributes\Layout;
use App\Models\Card;
use App\Models\TopUpTransaction;
use Illuminate\Support\Facades\Http;

new #[Layout('components.dashboard.operator-dashboard')]class extends Component
{   
    #[Validate('required|in:P100,P200,P500')]
    public $selectedPackage;

    public $packages = [
        'P100' => ['points' => 100,  'price' => 100],
        'P200' => ['points' => 200,  'price' => 200],
        'P500' => ['points' => 500,  'price' => 500],
    ];

    public function checkout()
    {
        $this->validate();

        // 1. Setup API Keys (pulling directly from config)
        $secretKey = config('services.paymongo.secret_key');
        $baseUrl   = config('services.paymongo.base_url');

        $user = auth()->user();

     
        
        // Find the user's active card
        $card = Card::where('user_id', $user->id)
                    ->where('status', 'active')
                    ->firstOrFail();


        $points = $this->packages[$this->selectedPackage]['points'];
        $amountCentavos = $points * 100;

        // 3. Save to DB as pending
        $topUp = TopUpTransaction::create([
            'user_id'        => $user->id,
            'card_id'        => $card->id,
            'points_to_load' => $points,
            'amount_paid'    => $points,
            'status'         => 'pending',
        ]);

        // 4. Create PayMongo Checkout Session
        $response = Http::withBasicAuth($secretKey, '')
            ->withoutVerifying()
            ->post("{$baseUrl}/checkout_sessions", [
                'data' => [
                    'attributes' => [
                        'billing' => [
                            'name'  => $user->name,
                            'email' => $user->email,
                            'phone' => $user->phone ?? '09000000000',
                        ],
                        'line_items' => [[
                            'currency' => 'PHP',
                            'amount'   => $amountCentavos,
                            'name'     => "{$points} Points Top-up",
                            'quantity' => 1,
                        ]],
                        'payment_method_types' => ['gcash', 'paymaya', 'card'],
                        'success_url' => route('topup.success'),
                        'cancel_url'  => route('topup.cancel'),
                        'description' => "Top-up {$points} pts for card {$card->uid}",
                        'metadata'    => [
                            'topup_id' => $topUp->id,
                        ],
                    ],
                ],
            ]);

        if ($response->failed()) {
            $topUp->update(['status' => 'failed']);
            session()->flash('error', 'PayMongo Connection Error.');
            return;
        }

        $sessionData = $response->json('data');

        // 5. Update with session ID
        $topUp->update([
            'checkout_session_id' => $sessionData['id'],
        ]);

        // 6. Redirect to PayMongo
        return redirect()->away($sessionData['attributes']['checkout_url']);
    }

}; ?>

<div>
    <form wire:submit.prevent="checkout" class="space-y-6">
        {{-- Radio Group --}}
        <flux:radio.group wire:model.live="selectedPackage" label="Available Points" variant="cards" class="max-sm:flex-col">
            @foreach($packages as $key => $pkg)
                <flux:radio value="{{ $key }}" label="₱{{ $pkg['price'] }}" description="{{ $pkg['points'] }} points" />
            @endforeach
        </flux:radio.group>

        {{-- Error Message --}}
        @error('selectedPackage')
            <p class="text-red-500 text-sm">{{ $message }}</p>
        @enderror

        {{-- Summary --}}
        @if($selectedPackage)
            <div class="p-4 bg-gray-50 rounded-lg">
                <p>You will load: <strong>{{ $packages[$selectedPackage]['points'] }} points</strong></p>
            </div>
        @endif

        {{-- Submit Button --}}
        <flux:button type="submit" variant="primary" class="w-full">
            Next — Pay via PayMongo
        </flux:button>
    </form>
</div>
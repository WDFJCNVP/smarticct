<?php

namespace App\Services;
    
use App\Models\TopUpTransaction;
use App\Models\User;
use App\Models\Card;
use Illuminate\Support\Facades\Http;

class CheckoutSessionService
{
   
    public function createCheckoutSession(User $user, Card $card, float $amountInPesos): string
    {

        $pointsToCredit = $amountInPesos;
        
        // PayMongo expects the amount in centavos (PHP 100.00 = 10000)
        $amountInCents = (int) ($amountInPesos * 100);

        $response = Http::withBasicAuth(config('services.paymongo.secret_key'), '')
            ->acceptJson()
            ->post('https://api.paymongo.com/v1/checkout_sessions', [
                'data' => [
                    'attributes' => [
                        'line_items' => [
                            [
                                'name' => 'Smart ICCT Wallet Top-Up',
                                'amount' => $amountInCents,
                                'currency' => 'PHP',
                                'quantity' => 1,
                                'description' => "Crediting {$pointsToCredit} Points to {$user->name}",
                            ]
                        ],
                        'payment_method_types' => ['gcash', 'paymaya', 'card', 'qrph'],
                        'send_email_receipt' => true,
                        'show_description' => true,
                        'show_line_items' => true,
                        'success_url' => route('topup.success'),
                        'cancel_url' => route('topup.cancel'),
                    ]
                ]
            ]);

        if ($response->failed()) {
            throw new \Exception('Failed to initiate PayMongo session: ' . $response->body());
        }

        $sessionData = $response->json('data');

        // Log the intent attached to the specific card
        TopUpTransaction::create([
            'user_id' => $user->id,
            'card_id' => $card->id, // Store the card being topped up
            'checkout_session_id' => $sessionData['id'],
            'amount_paid' => $amountInPesos,
            'points_credited' => $amountInPesos, // 1 PHP = 1 Point
            'status' => 'pending',
        ]);

        return $sessionData['attributes']['checkout_url'];
    }
}

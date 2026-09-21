<?php

namespace App\Services;

use App\Models\Card;
use App\Models\TopUpTransaction;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CheckoutSessionService
{

    public function createCheckoutSession(User $user, Card $card, float $amountInPesos): string
    {
        $amountInCents = (int) round($amountInPesos * 100);

        try {
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
                                    'description' => "Top-up of ₱{$amountInPesos} for {$user->name}",
                                ],
                            ],
                            'payment_method_types' => ['gcash', 'paymaya', 'card', 'qrph'],
                            'send_email_receipt' => true,
                            'show_description' => true,
                            'show_line_items' => true,
                            'success_url' => route('topup.success'),
                            'cancel_url' => route('topup.cancel'),
                        ],
                    ],
                ]);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            
            Log::error('PayMongo checkout session request could not connect', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            throw new \Exception('Could not reach the payment provider. Please try again.');
        }

        if ($response->failed()) {

            Log::error('PayMongo checkout session creation returned an error', [
                'user_id' => $user->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \Exception('Failed to initiate PayMongo session: ' . $response->body());
        }

        $sessionData = $response->json('data');

        TopUpTransaction::create([
            'user_id' => $user->id,
            'card_id' => $card->id,
            'checkout_session_id' => $sessionData['id'],
            'amount_paid' => $amountInPesos,
            'points_credited' => 0,
            'status' => 'pending',
        ]);

        return $sessionData['attributes']['checkout_url'];
    }
}
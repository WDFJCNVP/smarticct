<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Http;
use App\Models\Payment;

class CreateCheckoutSessionController extends Controller
{
    public function createCheckoutSession($amount, $description)
    {
        // PayMongo expects the amount in cents (e.g., PHP 100.00 = 10000)
        $amountInCents = $amount * 100; 

        $response = Http::withBasicAuth(env('PAYMONGO_SECRET_KEY'), '')
            ->post('https://api.paymongo.com/v1/checkout_sessions', [
                'data' => [
                    'attributes' => [
                        'line_items' => [
                            [
                                'name' => $description,
                                'amount' => $amountInCents,
                                'currency' => 'PHP',
                                'quantity' => 1,
                            ]
                        ],
                        'payment_method_types' => ['gcash', 'paymaya', 'card'],
                        'success_url' => route('payment.success'),
                        'cancel_url' => route('payment.cancel'),
                    ]
                ]
            ]);

        $checkoutData = $response->json()['data'];

        // Securely log the intent in your database BEFORE redirecting
        Payment::create([
            'user_id' => auth()->id(),
            'checkout_session_id' => $checkoutData['id'],
            'amount' => $amount,
            'status' => 'pending',
        ]);

        // Redirect user to PayMongo's hosted checkout
        return redirect($checkoutData['attributes']['checkout_url']);
    }
}

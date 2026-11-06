<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Services\RentalPaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Receives PayMongo's messages for RENTAL first payments only.
 * It has its own URL and its own secret, so the working top-up and cashout
 * webhooks are not touched. Both webhooks may receive the same checkout events;
 * each one ignores sessions that are not its own.
 */
class RentalPaymongoController extends Controller
{
    public function handle(Request $request, RentalPaymentService $service)
    {
        $payload   = $request->getContent();
        $signature = $request->header('Paymongo-Signature');
        $secret    = config('services.paymongo.rental_webhook_secret');

        if (! $this->isValidSignature($payload, $signature, $secret)) {
            Log::warning('RENTAL WEBHOOK: Invalid signature, rejecting.');
            abort(403, 'Invalid signature.');
        }

        try {
            $event     = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
            $eventId   = $event['data']['id'] ?? null;
            $type      = $event['data']['attributes']['type'] ?? '';
            $session   = $event['data']['attributes']['data'] ?? null;
            $sessionId = $session['id'] ?? null;

            if (! $sessionId) {
                Log::error('RENTAL WEBHOOK: event is missing the checkout session id.', ['type' => $type]);

                return response()->json(['status' => 'success']);
            }

            if ($type === 'checkout_session.payment.paid') {
                // The method the customer actually used (gcash, paymaya, card, qrph).
                $method   = $session['attributes']['payment_method_used'] ?? null;
                $centavos = $session['attributes']['payments'][0]['attributes']['amount'] ?? null;
                $pesos    = $centavos !== null ? round($centavos / 100, 2) : null;

                $outcome = $service->handlePaid($sessionId, $eventId, $method, $pesos);

                Log::info('RENTAL WEBHOOK: paid event handled.', ['outcome' => $outcome, 'session' => $sessionId]);
            } elseif (in_array($type, ['checkout_session.payment.failed', 'checkout_session.payment.expired'], true)) {
                $service->handleFailedOrExpired($sessionId, $type, $eventId);
            } else {
                Log::info('RENTAL WEBHOOK: Unhandled event type received.', ['type' => $type]);
            }
        } catch (\Throwable $e) {
            // Unlike the top-up webhook, answer with an error here so PayMongo sends the
            // message again later. The handler is safe to repeat (row locks, status check,
            // unique reference number), and a paid booking must not be left unconfirmed.
            Log::error('RENTAL WEBHOOK: Processing failed after signature passed.', [
                'error' => $e->getMessage(),
            ]);

            return response()->json(['status' => 'error'], 500);
        }

        return response()->json(['status' => 'success']);
    }

    private function isValidSignature(string $payload, ?string $sigHeader, ?string $secret): bool
    {
        if (! $sigHeader || ! $secret) {
            return false;
        }

        $parts = [];
        foreach (explode(',', $sigHeader) as $part) {
            $data = explode('=', $part, 2);
            if (count($data) === 2) {
                $parts[trim($data[0])] = trim($data[1]);
            }
        }

        $timestamp = $parts['t'] ?? '';
        $received  = ! empty($parts['li']) ? $parts['li'] : ($parts['te'] ?? '');
        $expected  = hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);

        return hash_equals($expected, $received);
    }
}
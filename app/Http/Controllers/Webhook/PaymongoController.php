<?php
namespace App\Http\Controllers\Webhook;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\TopUpTransaction;
use App\Models\Card;
class PaymongoController extends Controller
{
    public function handleWebhook(Request $request)
    {
        $signatureHeader = $request->header('Paymongo-Signature');
        $webhookSecret = config('services.paymongo.webhook_secret');
        $payload = $request->getContent();

        if (empty($payload)) {
            Log::error('WEBHOOK DEBUG: Payload is EMPTY! XAMPP is dropping the body.');
        } else {
            Log::info('WEBHOOK DEBUG: Payload received successfully. Length: ' . strlen($payload));
        }

        // 1. Verify Signature
        if (!$this->isValidSignature($payload, $signatureHeader, $webhookSecret)) {
            Log::warning('WEBHOOK: Invalid signature, rejecting.');
            abort(403, 'Invalid signature.');
        }

        // 2. Process the Event — wrapped so a bug here can't trigger PayMongo's retry/auto-disable
        try {
            $event = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
            $type = $event['data']['attributes']['type'] ?? '';

            if ($type === 'checkout_session.payment.paid') {
                $checkoutSessionId = $event['data']['attributes']['data']['id'] ?? null;

                if (!$checkoutSessionId) {
                    Log::error('WEBHOOK: payment.paid event missing checkout session id.', ['event' => $event]);
                } else {
                    // 3. Atomic Database Crediting
                    DB::transaction(function () use ($checkoutSessionId) {
                        $transaction = TopUpTransaction::where('checkout_session_id', $checkoutSessionId)
                            ->lockForUpdate()
                            ->first();

                        if (!$transaction || $transaction->status === 'paid') {
                            return; // Stop if already paid or unknown session
                        }

                        $transaction->update(['status' => 'paid']);
                        $card = Card::where('id', $transaction->card_id)->lockForUpdate()->first();
                        if ($card) {
                            $card->increment('balance', $transaction->points_credited);
                            Log::info("Credited PHP {$transaction->points_credited} to Card ID {$card->id}");
                        }
                    });
                }
            } elseif (in_array($type, [
                'checkout_session.payment.failed',
                'checkout_session.payment.expired',
            ])) {
                // 4. Handle failed/expired payments so transactions don't stay 'pending' forever
                $checkoutSessionId = $event['data']['attributes']['data']['id'] ?? null;

                if ($checkoutSessionId) {
                    DB::transaction(function () use ($checkoutSessionId, $type) {
                        $transaction = TopUpTransaction::where('checkout_session_id', $checkoutSessionId)
                            ->lockForUpdate()
                            ->first();

                        if (!$transaction || $transaction->status === 'paid') {
                            return; // Don't downgrade a transaction that already succeeded
                        }

                        $transaction->update(['status' => 'failed']);
                        Log::info("TopUpTransaction {$transaction->id} marked failed ({$type}).");
                    });
                }
            } else {
                Log::info('WEBHOOK: Unhandled event type received.', ['type' => $type]);
            }
        } catch (\Throwable $e) {
            // Signature already passed, so this is a bug in OUR handling, not a fake request.
            // Still return 200 so PayMongo doesn't retry/disable — just log it for us to fix.
            Log::error('WEBHOOK: Processing failed after signature passed.', [
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json(['status' => 'success']);
    }

    private function isValidSignature(string $payload, ?string $sigHeader, ?string $secret): bool
    {
        if (!$sigHeader || !$secret) return false;
        $parts = [];
        foreach (explode(',', $sigHeader) as $part) {
            $data = explode('=', $part, 2);
            if (count($data) === 2) {
                $parts[trim($data[0])] = trim($data[1]);
            }
        }
        $timestamp = $parts['t'] ?? '';
        $received  = $parts['te'] ?? ($parts['li'] ?? '');
        $expected  = hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);
        return hash_equals($expected, $received);
    }
}
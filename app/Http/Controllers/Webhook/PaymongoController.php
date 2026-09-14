<?php
namespace App\Http\Controllers\Webhook;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\CardTransaction;
use App\Models\TopUpTransaction;
use App\Models\Card;
use App\Models\Notification;
use App\Models\UserNotification;
use App\Events\NotificationEvent;
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
                    // The payment method the customer actually used to pay (gcash, paymaya, card, qrph)
                    $paymentMethod = $event['data']['attributes']['data']['attributes']['payment_method_used'] ?? null;

                    // 3. Atomic Database Crediting
                    DB::transaction(function () use ($checkoutSessionId, $paymentMethod) {
                        $transaction = TopUpTransaction::where('checkout_session_id', $checkoutSessionId)
                            ->lockForUpdate()
                            ->first();

                        if (!$transaction || $transaction->status === 'paid') {
                            return; // Stop if already paid or unknown session
                        }

                        $transaction->update([
                            'status' => 'paid',
                            'payment_method' => $paymentMethod,
                        ]);
                        $card = Card::where('id', $transaction->card_id)->lockForUpdate()->first();
                        if ($card) {
                            $card->increment('balance', $transaction->points_credited);
                            Log::info("Credited PHP {$transaction->points_credited} to Card ID {$card->id}");
                        }

                        if ($transaction->user_id) {
                            $notification = Notification::create([
                                'type'    => 'TopUp',
                                'title'   => 'Top-up successful',
                                'message' => "₱" . number_format($transaction->points_credited, 2) . " has been added to your card.",
                                'metadata' => [
                                    'amount'               => $transaction->points_credited,
                                    'checkout_session_id'  => $checkoutSessionId,
                                ],
                            ]);

                            UserNotification::create([
                                'notification_id' => $notification->id,
                                'user_id'         => $transaction->user_id,
                            ]);
                        }
                    });

                    try {
                        broadcast(new NotificationEvent());
                    } catch (\Exception $e) {
                        // The top-up already succeeded above — a broadcast/websocket
                        // hiccup should only cost real-time UI refresh.
                        Log::warning('Top-up succeeded but notification broadcast failed', ['error' => $e->getMessage()]);
                    }
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

                        if ($transaction->user_id) {
                            $reason = $type === 'checkout_session.payment.expired' ? 'expired' : 'failed';

                            $notification = Notification::create([
                                'type'    => 'TopUp',
                                'title'   => 'Top-up ' . $reason,
                                'message' => "Your ₱" . number_format($transaction->points_credited, 2) . " top-up did not go through. No balance was added.",
                                'metadata' => [
                                    'amount'              => $transaction->points_credited,
                                    'checkout_session_id' => $checkoutSessionId,
                                    'reason'              => $reason,
                                ],
                            ]);

                            UserNotification::create([
                                'notification_id' => $notification->id,
                                'user_id'         => $transaction->user_id,
                            ]);
                        }
                    });

                    try {
                        broadcast(new NotificationEvent());
                    } catch (\Exception $e) {
                        Log::warning('Top-up failure processed but notification broadcast failed', ['error' => $e->getMessage()]);
                    }
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

    // private function isValidSignature(string $payload, ?string $sigHeader, ?string $secret): bool
    // {
    //     if (!$sigHeader || !$secret) return false;
    //     $parts = [];
    //     foreach (explode(',', $sigHeader) as $part) {
    //         $data = explode('=', $part, 2);
    //         if (count($data) === 2) {
    //             $parts[trim($data[0])] = trim($data[1]);
    //         }
    //     }
    //     $timestamp = $parts['t'] ?? '';
    //     $received  = $parts['te'] ?? ($parts['li'] ?? '');
    //     $expected  = hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);
    //     return hash_equals($expected, $received);
    // }

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
        $received  = $parts['li'] ?? ($parts['te'] ?? '');
        $expected  = hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);

        Log::warning('SIG DEBUG', [
            'raw_header' => $sigHeader,
            'timestamp'  => $timestamp,
            'expected'   => $expected,
            'received'   => $received,
            'payload_len'=> strlen($payload),
            'payload_first_50' => substr($payload, 0, 50),
        ]);

        return hash_equals($expected, $received);
    }

    public function handleDisbursementWebhook(Request $request)
    {
        $payload = $request->getContent();
        $signatureHeader = $request->header('Paymongo-Signature');
        $webhookSecret = config('services.paymongo.disbursement_webhook_secret');

        if (!$this->isValidSignature($payload, $signatureHeader, $webhookSecret)) {
            Log::warning('DISBURSEMENT WEBHOOK: Invalid signature, rejecting.');
            abort(403, 'Invalid signature.');
        }

        try {
            $event = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
            $type = $event['data']['attributes']['type'] ?? null;
            $referenceNumber = $event['data']['attributes']['data']['attributes']['reference_number'] ?? null;

            if (!$referenceNumber) {
                Log::error('DISBURSEMENT WEBHOOK: missing reference_number.', ['event' => $event]);
                return response()->json(['message' => 'ok']); // ack anyway, nothing to match
            }

            DB::transaction(function () use ($type, $referenceNumber, $event) {
                $transaction = CardTransaction::where('reference_no', $referenceNumber)
                    ->lockForUpdate()
                    ->first();

                if (!$transaction || in_array($transaction->status, ['success', 'failed'])) {
                    return; // already terminal, or unknown reference — idempotent no-op
                }

                if ($type === 'transfer.outward.successful') {
                    $transaction->update(['status' => 'success']);

                    if ($transaction->processed_by) {
                        $notification = Notification::create([
                            'type'    => 'Withdrawal',
                            'title'   => 'Withdrawal completed',
                            'message' => "Your ₱" . number_format($transaction->amount, 2) . " withdrawal has been sent.",
                            'metadata' => [
                                'amount'       => $transaction->amount,
                                'reference_no' => $transaction->reference_no,
                            ],
                        ]);

                        UserNotification::create([
                            'notification_id' => $notification->id,
                            'user_id'         => $transaction->processed_by,
                        ]);
                    }
                } elseif ($type === 'transfer.outward.failed') {
                    $attrs = $event['data']['attributes']['data']['attributes'] ?? [];

                    $transaction->update([
                        'status' => 'failed',
                        'message' => $transaction->message
                            . " | Failed: {$attrs['provider_error']} ({$attrs['provider_error_code']})",
                    ]);

                    // Only operator withdrawals are backed by a real card balance
                    // (card_id is null for admin withdrawals, whose balance is
                    // computed on the fly from successful/pending withdrawals) —
                    // so only refund when there's an actual card to credit.
                    $card = $transaction->card_id
                        ? $transaction->card()->lockForUpdate()->first()
                        : null;

                    if ($card) {
                        $card->increment('balance', $transaction->amount); // refund since it never left
                    }

                    if ($transaction->processed_by) {
                        $notification = Notification::create([
                            'type'    => 'Withdrawal',
                            'title'   => 'Withdrawal failed',
                            'message' => "Your ₱" . number_format($transaction->amount, 2) . " withdrawal failed"
                                . ($card ? " and was refunded to your card." : "."),
                            'metadata' => [
                                'amount'       => $transaction->amount,
                                'reference_no' => $transaction->reference_no,
                            ],
                        ]);

                        UserNotification::create([
                            'notification_id' => $notification->id,
                            'user_id'         => $transaction->processed_by,
                        ]);
                    }
                }
            });

            try {
                broadcast(new NotificationEvent());
            } catch (\Exception $e) {
                Log::warning('Disbursement processed but notification broadcast failed', ['error' => $e->getMessage()]);
            }
        } catch (\Throwable $e) {
            Log::error('DISBURSEMENT WEBHOOK: Processing failed after signature passed.', [
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json(['message' => 'ok']);
    }
}
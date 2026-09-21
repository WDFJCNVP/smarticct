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
use App\Events\PaymongoMoneyTransferEvent;

class PaymongoController extends Controller
{
    public function handleWebhook(Request $request)
    {
        $signatureHeader = $request->header('Paymongo-Signature');
        $webhookSecret = config('services.paymongo.webhook_secret');
        $payload = $request->getContent();

        // 1. Verify Signature
        if (!$this->isValidSignature($payload, $signatureHeader, $webhookSecret)) {
            Log::warning('WEBHOOK: Invalid signature, rejecting.');
            abort(403, 'Invalid signature.');
        }

        try {
            $event = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
            $eventId = $event['data']['id'] ?? null;
            $type = $event['data']['attributes']['type'] ?? '';

            if ($type === 'checkout_session.payment.paid') {
                $checkoutSession = $event['data']['attributes']['data'] ?? null;
                $checkoutSessionId = $checkoutSession['id'] ?? null;

                if (!$checkoutSessionId) {
                    Log::error('WEBHOOK: payment.paid event missing checkout session id.', ['event' => $event]);
                } else {

                    // The payment method the customer actually used to pay (gcash, paymaya, card, qrph)
                    $paymentMethod = $event['data']['attributes']['data']['attributes']['payment_method_used'] ?? null;


                    $confirmedPayment = $checkoutSession['attributes']['payments'][0]['attributes'] ?? null;
                    $confirmedAmountCentavos = $confirmedPayment['amount'] ?? null;
                    $confirmedAmountPesos = $confirmedAmountCentavos !== null
                        ? round($confirmedAmountCentavos / 100, 2)
                        : null;

                    //Database Crediting
                    DB::transaction(function () use ($checkoutSessionId, $paymentMethod, $eventId, $confirmedAmountPesos) {

                        $transaction = TopUpTransaction::where('checkout_session_id', $checkoutSessionId)
                            ->lockForUpdate()
                            ->first();

                        if (!$transaction || $transaction->status === 'paid') {
                            return; // Stop if already paid or unknown session
                        }

                        if ($confirmedAmountPesos === null) {
                
                            $transaction->update([
                                'status' => 'failed',
                                'failure_reason' => 'Paid webhook received but payload had no confirmable payment amount — needs manual review.',
                                'paymongo_event_id' => $eventId,
                            ]);
 
                            Log::error('WEBHOOK: paid event missing a confirmable payment amount.', [
                                'transaction_id' => $transaction->id,
                                'checkout_session_id' => $checkoutSessionId,
                            ]);
 
                            return;
                        }
 
                        if ((float) $transaction->amount_paid !== $confirmedAmountPesos) {

                            Log::warning('WEBHOOK: confirmed payment amount does not match quoted amount_paid.', [
                                'transaction_id' => $transaction->id,
                                'quoted' => $transaction->amount_paid,
                                'confirmed' => $confirmedAmountPesos,
                            ]);
                        }

                        $transaction->update([
                            'status' => 'paid',
                            'payment_method' => $paymentMethod,
                            'points_credited' => $confirmedAmountPesos,
                            'paymongo_event_id' => $eventId,
                        ]);

                        $card = Card::where('id', $transaction->card_id)->lockForUpdate()->first();
                        if ($card) {
                            $card->increment('balance', $confirmedAmountPesos);
                            Log::info("Credited PHP {$transaction->points_credited} to Card ID {$card->id}");
                        }

                        if ($transaction->user_id) {
                            $notification = Notification::create([
                                'type'    => 'TopUp',
                                'title'   => 'Top-up successful',
                                'message' => "₱" . number_format($confirmedAmountPesos, 2) . " has been added to your card.",
                                'metadata' => [
                                    'amount'               => $confirmedAmountPesos,
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
                    DB::transaction(function () use ($checkoutSessionId, $type, $eventId) {
                        $transaction = TopUpTransaction::where('checkout_session_id', $checkoutSessionId)
                            ->lockForUpdate()
                            ->first();

                        if (!$transaction || $transaction->status === 'paid') {
                            return; // Don't downgrade a transaction that already succeeded
                        }

                        $reason = $type === 'checkout_session.payment.expired'
                            ? 'Checkout session expired unpaid.'
                            : 'Payment failed at PayMongo.';

                        $transaction->update([
                            'status' => $type === 'checkout_session.payment.expired' ? 'expired' : 'failed',
                            'failure_reason' => $reason,
                            'paymongo_event_id' => $eventId,
                        ]);
                        Log::info("TopUpTransaction {$transaction->id} marked {$transaction->status} ({$type}).");

                        if ($transaction->user_id) {
                            $notification = Notification::create([
                                'type'    => 'TopUp',
                                'title'   => 'Top-up ' . $transaction->status,
                                'message' => "Your ₱" . number_format($transaction->amount_paid, 2) . " top-up did not go through. No balance was added.",
                                'metadata' => [
                                    'amount'              => $transaction->amount_paid,
                                    'checkout_session_id' => $checkoutSessionId,
                                    'reason'              => $transaction->status,
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
        $received = !empty($parts['li']) ? $parts['li'] : ($parts['te'] ?? '');
        $expected  = hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);

        if (config('app.debug')) {
            Log::debug('SIG DEBUG', [
                'raw_header' => $sigHeader,
                'timestamp'  => $timestamp,
                'expected'   => $expected,
                'received'   => $received,
                'payload_len'=> strlen($payload),
                'payload_first_50' => substr($payload, 0, 50),
            ]);
        }

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

                    if ($transaction->card_id) {
                        $card = $transaction->card()->lockForUpdate()->first();
                        if ($card) {
                            $card->decrement('balance', $transaction->amount); // deduct only now, on confirmed success
                        }
                    }

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

                    if ($transaction->processed_by) {
                        $notification = Notification::create([
                            'type'    => 'Withdrawal',
                            'title'   => 'Withdrawal failed',
                            'message' => "Your ₱" . number_format($transaction->amount, 2) . " withdrawal failed",
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
                broadcast(new PaymongoMoneyTransferEvent());

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
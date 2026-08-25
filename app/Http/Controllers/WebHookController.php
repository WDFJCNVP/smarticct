<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\TopUpTransaction;
use App\Models\Card;
use App\Models\Notification;
use App\Models\UserNotification;
use App\Events\NotificationEvent;

class WebHookController extends Controller
{
    public function handle(Request $request) 
    {

        Log::info('Webhook hit', ['body' => $request->getContent()]);

        // 1. Verify Signature
        $signature = $request->header('Paymongo-Signature');

        $secret    = config('services.paymongo.webhook_secret');

        if (!$this->isValidSignature($request->getContent(), $signature, $secret)) {
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        header('Paymongo-Signature');
        $secret    = config('services.paymongo.webhook_secret');

        if (!$this->isValidSignature($request->getContent(), $signature, $secret)) {
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        // 2. Read the event
        $event = $request->json('data');
        $type  = $event['attributes']['type'] ?? '';

        Log::info('PayMongo webhook received', ['type' => $type]);

        // 3. Handle checkout_session.payment.paid
        if ($type === 'checkout_session.payment.paid') {
            $sessionData = $event['attributes']['data'];
            $sessionId   = $sessionData['id'];
            $metadata    = $sessionData['attributes']['metadata'] ?? [];
            $topupId     = $metadata['topup_id'] ?? null;
            $paymentMethod = $sessionData['attributes']['payment_method_used'] ?? null;

            if (!$topupId) {
                return response()->json(['error' => 'Missing topup_id in metadata'], 422);
            }

            // 4. Use a DB transaction so both updates succeed or both fail
            DB::transaction(function () use ($topupId, $paymentMethod) {
                $topUp = TopUpTransaction::findOrFail($topupId);

                if ($topUp->status === 'paid') return;

                $topUp->update([
                    'status'         => 'paid',
                    'payment_method' => $paymentMethod,
                ]);

                //  Add points 
                Card::where('id', $topUp->card_id)
                    ->increment('balance', $topUp->points_to_load);

                $notification = Notification::create([
                    'type'    => 'Top-up',
                    'title'   => 'Points Loaded',
                    'message' => "You've successfully loaded {$topUp->points_to_load} points to your card.",
                ]);

                UserNotification::create([
                    'notification_id' => $notification->id,
                    'user_id'         => $topUp->user_id,
                ]);

                broadcast(new NotificationEvent());

                Log::info('Top-up credited', [
                    'topup_id'       => $topUp->id,
                    'card_id'   => $topUp->card_id,
                    'points_added'   => $topUp->points_to_load,
                ]);
            });
        }

        // Always return 200 to acknowledge receipt
        return response()->json(['received' => true], 200);
    }

    private function isValidSignature(string $payload, ?string $sigHeader, string $secret): bool
    {
        if (!$sigHeader) return false;

        $parts = [];
            foreach (explode(',', $sigHeader) as $part) {
                [$k, $v] = explode('=', $part, 2);
                $parts[$k] = $v;
        }

        $timestamp = $parts['t'] ?? '';
        $received  = $parts['te'] ?? ($parts['li'] ?? '');
        $expected  = hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);

        return hash_equals($expected, $received);
    }
}
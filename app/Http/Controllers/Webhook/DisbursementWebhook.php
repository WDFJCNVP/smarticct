<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DisbursementWebhook extends Controller
{
    public function handleDisbursementWebhook(Request $request)
    {
        $payload = $request->getContent();
        $signatureHeader = $request->header('Paymongo-Signature');

        // verify using PAYMONGO_DISBURSEMENT_WEBHOOK_SECRET, same pattern as your existing PaymongoController

        $event = json_decode($payload, true);
        $type = $event['data']['attributes']['type'] ?? null;
        $referenceNumber = $event['data']['attributes']['data']['attributes']['reference_number'] ?? null;

        DB::transaction(function () use ($referenceNumber, $type) {
            // 1. Lock the row to prevent race conditions
            $transaction = CardTransaction::where('reference_no', $referenceNumber)
                ->lockForUpdate()
                ->first();

            if (!$transaction) {
                return; // Exit transaction silently or log it
            }

            // 2. Idempotency Guard: Only process if it is strictly 'pending'
            if ($transaction->status !== 'pending') {
                return;
            }

            // 3. Process the state change securely
            if ($type === 'transfer.outward.successful') {
                $transaction->update(['status' => 'success']);
            } elseif ($type === 'transfer.outward.failed') {
                $transaction->update(['status' => 'failed']);
                $transaction->card->increment('balance', $transaction->amount); 
        }

        return response()->json(['message' => 'Handled']);
    }
}

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

        $transaction = CardTransaction::where('reference_no', $referenceNumber)->first();

        if (!$transaction) {
            return response()->json(['message' => 'Transaction not found'], 404);
        }

        if ($type === 'transfer.outward.successful') {
            $transaction->update(['status' => 'success']);
        } elseif ($type === 'transfer.outward.failed') {
            $transaction->update(['status' => 'failed']);
            $transaction->card->increment('balance', $transaction->amount); // refund since it never actually left
        }

        return response()->json(['message' => 'Handled']);
    }
}

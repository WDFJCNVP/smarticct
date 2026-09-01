<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class OperatorDisbursementService
{
    protected string $baseUrl = 'https://api.paymongo.com/v2';

    public function createWithdrawal(array $data): array
    {
        $referenceNumber = 'WD-' . now()->format('YmdHis') . '-' . Str::random(4);

        $response = Http::withBasicAuth(config('services.paymongo.secret_key'), '')
            ->post("{$this->baseUrl}/batch_transfers", [
                'transfers' => [[
                    'provider'    => $data['provider'], // 'instapay' or 'pesonet'
                    'amount'      => (int) round($data['amount'] * 100), // centavos
                    'currency'    => 'PHP',
                    'purpose'     => 'Disbursement',
                    'description' => 'Operator card withdrawal',
                    'reference_number' => $referenceNumber,
                    'destination_account' => [
                        'number' => $data['account_number'],
                        'name'   => $data['account_name'],
                        'bic'    => 'PAEYPHM2XXX',
                    ],
                    'callback_url' => route('webhooks.paymongo.disbursement'),
                    'metadata' => ['operator_id' => $data['operator_id']],
                ]],
            ]);

        return [
            'response' => $response->json(),
            'reference_number' => $referenceNumber,
            'successful' => $response->successful(),
        ];
    }
}

<?php

namespace App\Services;

use Illuminate\Support\Str;
use RuntimeException;

class OperatorDisbursementService
{
    public function __construct(
        protected PaymongoDisbursementService $paymongo
    ) {}

    public function createWithdrawal(array $data): array
    {
        $referenceNumber = 'WD-' . now()->format('YmdHis') . '-' . Str::random(4);

        try {
            $response = $this->paymongo->createTransfer([
                'provider'                   => $data['provider'],
                'amount'                     => (int) round($data['amount'] * 100), // pesos → centavos
                'purpose'                    => 'Disbursement',
                'description'                => 'Operator card withdrawal',
                'reference_number'           => $referenceNumber,
                'destination_account_number' => $data['account_number'],
                'destination_account_name'   => $data['account_name'],
                'destination_bic'            => $data['bic'], // the operator's dropdown selection, used correctly now
                'metadata'                   => ['operator_id' => $data['operator_id']],
            ]);

            return [
                'response'          => $response,
                'reference_number'  => $referenceNumber,
                'successful'        => true,
            ];
        } catch (RuntimeException $e) {
            return [
                'response'          => ['error' => $e->getMessage()],
                'reference_number'  => $referenceNumber,
                'successful'        => false,
            ];
        }
    }
}
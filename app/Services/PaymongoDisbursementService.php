<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class PaymongoDisbursementService
{
    protected string $baseUrl = 'https://api.paymongo.com/v2';

    protected function client()
    {
        return Http::withBasicAuth(config('services.paymongo.secret_key'), '')
            ->acceptJson()
            ->asJson();
    }

    public function listReceivingInstitutions(string $provider = 'instapay'): array
    {
        $response = $this->client()->get("https://api.paymongo.com/v1/wallets/receiving_institutions", [
            'provider' => $provider,
        ]);
                                                                                                                                                                        
        if ($response->failed()) {
            throw new RuntimeException('Failed to fetch receiving institutions: ' . $response->body());
        }

        return $response->json('data', []);
    }

    public function createTransfer(array $data): array
    {
        $referenceNumber = $data['reference_number'] ?? (string) Str::uuid();

        $payload = [
            'transfers' => [[
                'provider' => $data['provider'], // instapay | pesonet
                'amount' => $data['amount'], // centavos
                'currency' => 'PHP',
                'purpose' => $data['purpose'] ?? 'Disbursement',
                'description' => $data['description'] ?? 'Terminal revenue withdrawal',
                'reference_number' => $referenceNumber,
                'source_account' => [
                    'number' => config('services.paymongo.source_account_number'),
                    'name' => config('services.paymongo.source_account_name'),
                    'bic' => config('services.paymongo.source_account_bic'),
                ],
                'destination_account' => [
                    'number' => $data['destination_account_number'],
                    'name' => $data['destination_account_name'],
                    'bic' => $data['destination_bic'],
                ],
                'callback_url' => config('services.paymongo.callback_url'),
                'metadata' => $data['metadata'] ?? [],
            ]],
        ];

        $response = $this->client()->post("{$this->baseUrl}/batch_transfers", $payload);

        if ($response->failed()) {
            throw new RuntimeException('PayMongo transfer failed: ' . $response->body());
        }

        return $response->json('data');
    }

    public function retrieveTransfer(string $transferId): array
    {
        $response = $this->client()->get("{$this->baseUrl}/transfers/{$transferId}");

        if ($response->failed()) {
            throw new RuntimeException('Failed to retrieve transfer: ' . $response->body());
        }

        return $response->json('data');
    }
}

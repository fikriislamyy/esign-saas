<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class PakasirService
{
    protected string $baseUrl;
    protected string $project;
    protected string $apiKey;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.pakasir.base_url'), '/');
        $this->project = config('services.pakasir.project');
        $this->apiKey = config('services.pakasir.api_key');
    }

    public function createQris(string $orderId, int $amountIdr): array
    {
        $response = Http::asJson()
            ->post($this->baseUrl.'/api/transactioncreate/qris', [
                'project' => $this->project,
                'order_id' => $orderId,
                'amount' => $amountIdr,
                'api_key' => $this->apiKey,
            ]);

        $response->throw();

        return $response->json();
    }

    public function transactionDetail(string $orderId, int $amountIdr): array
    {
        $response = Http::get($this->baseUrl.'/api/transactiondetail', [
            'project' => $this->project,
            'order_id' => $orderId,
            'amount' => $amountIdr,
            'api_key' => $this->apiKey,
        ]);

        $response->throw();

        return $response->json();
    }
}

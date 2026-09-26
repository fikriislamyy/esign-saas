<?php

namespace Tests\Unit\Services;

use App\Services\PakasirService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PakasirServiceTest extends TestCase
{
    public function test_is_configured_true_with_both_keys(): void
    {
        config(['services.pakasir.project' => 'p', 'services.pakasir.api_key' => 'k']);

        $service = new PakasirService();

        $this->assertTrue($service->isConfigured());
    }

    public function test_is_configured_false_with_project_blank(): void
    {
        config(['services.pakasir.project' => '', 'services.pakasir.api_key' => 'k']);

        $service = new PakasirService();

        $this->assertFalse($service->isConfigured());
    }

    public function test_is_configured_false_with_key_blank(): void
    {
        config(['services.pakasir.project' => 'p', 'services.pakasir.api_key' => '']);

        $service = new PakasirService();

        $this->assertFalse($service->isConfigured());
    }

    public function test_base_url_trailing_slash_is_trimmed(): void
    {
        config([
            'services.pakasir.base_url' => 'https://example.com/',
            'services.pakasir.project' => 'p',
            'services.pakasir.api_key' => 'k',
        ]);
        Http::fake([
            'example.com/api/transactioncreate/qris' => Http::response(['qr' => 'data:image/png;base64,...'], 200),
        ]);

        $service = new PakasirService();
        $result = $service->createQris('order-1', 100000);

        $this->assertIsArray($result);
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'https://example.com/api/transactioncreate/qris');
        });
    }

    public function test_default_base_url_when_config_null(): void
    {
        config([
            'services.pakasir.base_url' => null,
            'services.pakasir.project' => 'p',
            'services.pakasir.api_key' => 'k',
        ]);
        Http::fake([
            'app.pakasir.com/api/transactioncreate/qris' => Http::response(['qr' => 'data'], 200),
        ]);

        $service = new PakasirService();
        $service->createQris('order-1', 100000);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'https://app.pakasir.com/api/transactioncreate/qris');
        });
    }

    public function test_create_qris_posts_json_to_transactioncreate(): void
    {
        config(['services.pakasir.project' => 'p', 'services.pakasir.api_key' => 'k']);
        Http::fake([
            '*/api/transactioncreate/qris' => Http::response(['qr' => 'data:image/png;base64,...'], 200),
        ]);

        $service = new PakasirService();
        $result = $service->createQris('ORDER-123', 250000);

        $this->assertArrayHasKey('qr', $result);
        Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);
            return $body['project'] === 'p'
                && $body['order_id'] === 'ORDER-123'
                && $body['amount'] === 250000
                && $body['api_key'] === 'k';
        });
    }

    public function test_create_qris_on_500_throws(): void
    {
        config(['services.pakasir.project' => 'p', 'services.pakasir.api_key' => 'k']);
        Http::fake(['*/api/transactioncreate/qris' => Http::response([], 500)]);

        $service = new PakasirService();

        $this->expectException(\Illuminate\Http\Client\RequestException::class);
        $service->createQris('ORDER-123', 250000);
    }

    public function test_transaction_detail_gets_the_endpoint(): void
    {
        config([
            'services.pakasir.base_url' => 'https://app.pakasir.com',
            'services.pakasir.project' => 'p',
            'services.pakasir.api_key' => 'k',
        ]);
        Http::fake([
            'app.pakasir.com/api/transactiondetail*' => Http::response(['status' => 'completed'], 200),
        ]);

        $service = new PakasirService();
        $result = $service->transactionDetail('ORDER-123', 250000);

        $this->assertSame('completed', $result['status']);
        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/api/transactiondetail')
                && str_contains($request->url(), 'project=p')
                && str_contains($request->url(), 'order_id=ORDER-123')
                && str_contains($request->url(), 'amount=250000')
                && str_contains($request->url(), 'api_key=k');
        });
    }

    public function test_simulate_payment_posts_json(): void
    {
        config(['services.pakasir.project' => 'p', 'services.pakasir.api_key' => 'k']);
        Http::fake([
            '*/api/paymentsimulation' => Http::response(['result' => 'success'], 200),
        ]);

        $service = new PakasirService();
        $result = $service->simulatePayment('ORDER-123', 250000);

        $this->assertSame('success', $result['result']);
        Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);
            return $body['project'] === 'p'
                && $body['order_id'] === 'ORDER-123'
                && $body['amount'] === 250000;
        });
    }
}

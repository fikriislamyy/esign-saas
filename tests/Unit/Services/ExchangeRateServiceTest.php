<?php

namespace Tests\Unit\Services;

use App\Services\ExchangeRateService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ExchangeRateServiceTest extends TestCase
{
    public function test_usd_to_idr_with_no_api_key_returns_null(): void
    {
        Http::fake();
        config(['services.currencyfreaks.key' => '']);

        $service = new ExchangeRateService();

        $this->assertNull($service->usdToIdr());
        Http::assertNothingSent();
    }

    public function test_usd_to_idr_returns_the_rate(): void
    {
        Http::fake([
            'api.currencyfreaks.com/*' => Http::response([
                'rates' => ['IDR' => '16000'],
            ], 200),
        ]);
        config(['services.currencyfreaks.key' => 'key']);

        $service = new ExchangeRateService();

        $this->assertSame(16000.0, $service->usdToIdr());
    }

    public function test_usd_to_idr_second_call_within_300_seconds_does_not_hit_api(): void
    {
        Http::fake([
            'api.currencyfreaks.com/*' => Http::response([
                'rates' => ['IDR' => '16000'],
            ], 200),
        ]);
        config(['services.currencyfreaks.key' => 'key']);
        $service = new ExchangeRateService();

        $service->usdToIdr();
        $service->usdToIdr();

        Http::assertSentCount(1);
    }

    public function test_usd_to_idr_on_http_500_returns_null(): void
    {
        Http::fake(['api.currencyfreaks.com/*' => Http::response([], 500)]);
        config(['services.currencyfreaks.key' => 'key']);

        $service = new ExchangeRateService();

        $this->assertNull($service->usdToIdr());
    }

    public function test_usd_to_idr_when_rates_idr_missing_returns_null(): void
    {
        Http::fake([
            'api.currencyfreaks.com/*' => Http::response(['rates' => []], 200),
        ]);
        config(['services.currencyfreaks.key' => 'key']);

        $service = new ExchangeRateService();

        $this->assertNull($service->usdToIdr());
    }

    public function test_usd_to_idr_when_rate_is_zero_returns_null(): void
    {
        Http::fake([
            'api.currencyfreaks.com/*' => Http::response(['rates' => ['IDR' => 0]], 200),
        ]);
        config(['services.currencyfreaks.key' => 'key']);

        $service = new ExchangeRateService();

        $this->assertNull($service->usdToIdr());
    }

    public function test_usd_to_idr_with_non_numeric_rate_returns_null(): void
    {
        Http::fake([
            'api.currencyfreaks.com/*' => Http::response(['rates' => ['IDR' => 'invalid']], 200),
        ]);
        config(['services.currencyfreaks.key' => 'key']);

        $service = new ExchangeRateService();

        $this->assertNull($service->usdToIdr());
    }

    public function test_convert_usd_to_idr_with_rate_16000(): void
    {
        Http::fake([
            'api.currencyfreaks.com/*' => Http::response(['rates' => ['IDR' => '16000']], 200),
        ]);
        config(['services.currencyfreaks.key' => 'key']);
        Cache::flush();

        $service = new ExchangeRateService();

        $this->assertSame(160000.0, $service->convertUsdToIdr(10));
    }

    public function test_convert_usd_to_idr_with_no_rate_returns_null(): void
    {
        Http::fake(['api.currencyfreaks.com/*' => Http::response([], 500)]);
        config(['services.currencyfreaks.key' => 'key']);

        $service = new ExchangeRateService();

        $this->assertNull($service->convertUsdToIdr(10));
    }
}

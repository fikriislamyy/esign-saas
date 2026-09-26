<?php

namespace Tests\Unit\Services;

use App\Services\StripeService;
use RuntimeException;
use Stripe\StripeClient;
use Tests\TestCase;

class StripeServiceTest extends TestCase
{
    public function test_a_missing_secret_key_throws_an_exception(): void
    {
        config(['services.stripe.secret' => null]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Stripe secret key is not configured.');

        new StripeService;
    }

    public function test_a_configured_secret_key_creates_a_stripe_client(): void
    {
        config(['services.stripe.secret' => 'sk_test_dummy']);

        $service = new StripeService;

        $this->assertInstanceOf(StripeClient::class, $service->client());
    }
}

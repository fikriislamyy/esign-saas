<?php

namespace App\Services;

use RuntimeException;
use Stripe\StripeClient;

class StripeService
{
    protected StripeClient $client;

    public function __construct()
    {
        $secret = config('services.stripe.secret');

        if (!$secret) {
            throw new RuntimeException(
                'Stripe secret key is not configured.'
            );
        }

        $this->client = new StripeClient($secret);
    }

    public function client(): StripeClient
    {
        return $this->client;
    }
}

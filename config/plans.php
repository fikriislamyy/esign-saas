<?php

/*
 | Plan catalogue. Single source of truth for pricing and limits.
 |
 | - Prices are USD cents (integers), matching the wallet convention.
 | - A `null` limit means unlimited.
 | - `documents.period` is 'week' or 'month' and differs per plan — see trap 3.
 | - `stripe_price_id` comes from the Stripe Dashboard (Products → Add product).
 |   Create the recurring monthly price there and paste the `price_…` id into .env.
 */

return [

    'free' => [
        'label' => 'Free',
        'description' => 'For trying things out.',
        'price_usd_cents' => 0,
        'stripe_price_id' => null,
        'self_serve' => true,
        'limits' => [
            'documents' => ['limit' => 3, 'period' => 'week'],
            'templates' => 5,
            'members' => 3,
            'storage_bytes' => 100 * 1024 * 1024,        // 100 MB
        ],
    ],

    'pro' => [
        'label' => 'Pro',
        'description' => 'For small teams sending contracts regularly.',
        'price_usd_cents' => 1000,                        // $10.00
        'stripe_price_id' => env('STRIPE_PRICE_PRO'),
        'self_serve' => true,
        'limits' => [
            'documents' => ['limit' => 100, 'period' => 'month'],
            'templates' => 5,
            'members' => 10,
            'storage_bytes' => 10 * 1024 * 1024 * 1024,   // 10 GB
        ],
    ],

    'enterprise' => [
        'label' => 'Enterprise',
        'description' => 'Custom limits and terms. Talk to us.',
        'price_usd_cents' => 5000,                        // "from $50" — display only
        'stripe_price_id' => null,
        'self_serve' => false,                            // decision 3.7
        'contact_email' => 'sales@bebem.my.id',
        'limits' => [
            'documents' => ['limit' => null, 'period' => 'month'],
            'templates' => 5,
            'members' => null,
            'storage_bytes' => null,
        ],
    ],

];

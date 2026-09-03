<?php

namespace App\Http\Controllers;

use App\Models\WalletTopup;
use App\Services\ExchangeRateService;
use App\Services\StripeService;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;

class BillingTopupController extends Controller
{
    public function __construct(
        protected WalletService $walletService,
        protected ExchangeRateService $exchangeRateService,
        protected StripeService $stripeService,
    ) {}

    public function store(Request $request)
    {
        Log::info('BillingTopupController@store START', [
            'user_id' => $request->user()?->id,
            'email' => $request->user()?->email,
            'ip' => $request->ip(),
        ]);

        $user = $request->user();

        abort_unless(
            $user->role === 'owner',
            403
        );

        $validated = $request->validate([
            'currency' => [
                'required',
                'string',
                'in:USD,IDR',
            ],

            'amount' => [
                'required',
                'numeric',
                'gt:0',
            ],
        ]);

        Log::info('BillingTopupController@store VALIDATED', [
            'currency' => $validated['currency'],
            'amount' => $validated['amount'],
        ]);

        $organization = $user->organization;

        abort_unless($organization, 403);

        $currency = strtoupper($validated['currency']);
        $sourceAmount = (float) $validated['amount'];

        /*
        |--------------------------------------------------------------------------
        | Calculate wallet credit
        |--------------------------------------------------------------------------
        */

        if ($currency === 'USD') {
            $exchangeRate = 1.0;

            $walletAmountUsdCents = (int) round(
                $sourceAmount * 100
            );

            $stripeAmount = $walletAmountUsdCents;
        } else {
            $exchangeRate = app(
                \App\Services\ExchangeRateService::class
            )->usdToIdr();

            if (!$exchangeRate || $exchangeRate <= 0) {
                Log::error(
                    'BillingTopupController@store FX RATE FAILED',
                    [
                        'currency' => $currency,
                        'source_amount' => $sourceAmount,
                        'exchange_rate' => $exchangeRate,
                    ]
                );

                return back()->withErrors([
                    'amount' =>
                        'The current USD/IDR exchange rate is unavailable.',
                ]);
            }

            $sourceAmount = round($sourceAmount);

            $walletAmountUsdCents = (int) round(
                ($sourceAmount / $exchangeRate) * 100
            );

            $stripeAmount = (int) $sourceAmount;
        }

        Log::info('BillingTopupController@store AMOUNTS CALCULATED', [
            'currency' => $currency,
            'source_amount' => $sourceAmount,
            'exchange_rate' => $exchangeRate,
            'wallet_amount_usd_cents' => $walletAmountUsdCents,
            'stripe_amount' => $stripeAmount,
        ]);

        if ($walletAmountUsdCents <= 0) {
            Log::warning(
                'BillingTopupController@store WALLET AMOUNT <= 0',
                [
                    'wallet_amount_usd_cents' => $walletAmountUsdCents,
                ]
            );

            return back()->withErrors([
                'amount' =>
                    'The top-up amount is too small to add funds to the wallet.',
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Get wallet
        |--------------------------------------------------------------------------
        */

        $walletService = app(
            \App\Services\WalletService::class
        );

        $wallet = $walletService->getOrCreateWallet(
            $organization
        );

        Log::info('BillingTopupController@store WALLET READY', [
            'wallet_id' => $wallet->id,
            'organization_id' => $organization->id,
            'balance_usd_cents' => $wallet->balance_usd_cents,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Create pending top-up
        |--------------------------------------------------------------------------
        */

        $topup = \DB::transaction(function () use (
            $wallet,
            $organization,
            $user,
            $currency,
            $sourceAmount,
            $exchangeRate,
            $walletAmountUsdCents
        ) {
            return \App\Models\WalletTopup::create([
                'wallet_id' => $wallet->id,
                'organization_id' => $organization->id,
                'currency' => $currency,
                'amount' => $sourceAmount,
                'exchange_rate' => $exchangeRate,
                'wallet_amount_usd_cents' => $walletAmountUsdCents,
                'status' => 'pending',
                'created_by' => $user->id,
                'metadata' => [
                    'wallet_credit_usd_cents' => $walletAmountUsdCents,
                ],
            ]);
        });

        Log::info('BillingTopupController@store TOPUP CREATED', [
            'topup_id' => $topup->id,
            'wallet_id' => $topup->wallet_id,
            'organization_id' => $topup->organization_id,
            'currency' => $topup->currency,
            'amount' => $topup->amount,
            'exchange_rate' => $topup->exchange_rate,
            'wallet_amount_usd_cents' => $topup->wallet_amount_usd_cents,
            'status' => $topup->status,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Create Stripe Checkout Session
        |--------------------------------------------------------------------------
        */

        try {
            $stripe = app(
                \App\Services\StripeService::class
            )->client();

            Log::info(
                'BillingTopupController@store CREATING STRIPE SESSION',
                [
                    'topup_id' => $topup->id,
                    'currency' => strtolower($currency),
                    'stripe_amount' => $stripeAmount,
                    'customer_email' => $user->email,
                ]
            );

            $session = $stripe->checkout->sessions->create([
                'mode' => 'payment',

                'success_url' => route('billing.index', [
                    'topup' => 'success',
                ]),

                'cancel_url' => route('billing.index', [
                    'topup' => 'cancelled',
                ]),

                'customer_email' => $user->email,

                'line_items' => [
                    [
                        'price_data' => [
                            'currency' => strtolower($currency),

                            'product_data' => [
                                'name' =>
                                    'ESignSaaS Wallet Top Up',
                            ],

                            'unit_amount' => $stripeAmount,
                        ],

                        'quantity' => 1,
                    ],
                ],

                'metadata' => [
                    'wallet_topup_id' => (string) $topup->id,
                    'organization_id' => (string) $organization->id,
                ],
            ]);

            Log::info(
                'BillingTopupController@store STRIPE SESSION CREATED',
                [
                    'topup_id' => $topup->id,
                    'session_id' => $session->id,
                    'session_status' => $session->status,
                    'payment_status' => $session->payment_status,
                    'payment_intent' => $session->payment_intent,
                    'session_url_exists' => !empty($session->url),
                    'metadata' => $session->metadata?->toArray(),
                ]
            );

            $topup->update([
                'stripe_checkout_session_id' => $session->id,
            ]);

            Log::info(
                'BillingTopupController@store TOPUP UPDATED WITH SESSION',
                [
                    'topup_id' => $topup->id,
                    'stripe_checkout_session_id' =>
                        $topup->stripe_checkout_session_id,
                ]
            );

            Log::info(
                'BillingTopupController@store REDIRECTING TO STRIPE',
                [
                    'topup_id' => $topup->id,
                    'session_id' => $session->id,
                    'url' => $session->url,
                ]
            );

            return \Inertia\Inertia::location(
                $session->url
            );
        } catch (\Throwable $e) {
            Log::error(
                'BillingTopupController@store STRIPE ERROR',
                [
                    'topup_id' => $topup->id ?? null,
                    'message' => $e->getMessage(),
                    'exception' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]
            );

            throw $e;
        }
    }
}

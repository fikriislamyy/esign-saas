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
                'min:0.5',
            ],

            'method' => [
                'nullable',
                'string',
                'in:card,qris',
            ],
        ]);

        Log::info('BillingTopupController@store VALIDATED', [
            'currency' => $validated['currency'],
            'amount' => $validated['amount'],
        ]);

        $organization = $user->organization;

        abort_unless($organization, 403);

        $isQris = ($validated['method'] ?? 'card') === 'qris';

        // Checked before the pending top-up row is created, so a misconfigured
        // gateway does not leave orphaned rows behind.
        if ($isQris && ! app(\App\Services\PakasirService::class)->isConfigured()) {
            Log::error('QRIS blocked: Pakasir is not configured', [
                'env_keys' => ['PAKASIR_PROJECT', 'PAKASIR_API_KEY'],
            ]);

            return response()->json([
                'message' => 'QRIS payments are not available yet. Please pay by card or contact support.',
            ], 422);
        }

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

        if ($isQris) {
            $rate = $this->exchangeRateService->usdToIdr();

            if (! $rate || $rate <= 0) {
                return response()->json([
                    'message' => 'The USD/IDR exchange rate is unavailable. Please try again shortly.',
                ], 422);
            }

            $amountIdr = (int) round(($walletAmountUsdCents / 100) * $rate);
            $orderId = 'TOP-'.$organization->id.'-'.now()->timestamp;

            $topup->update([
                'provider' => 'pakasir',
                'order_id' => $orderId,
                // The webhook and transactionDetail() both need the exact rupiah figure.
                // wallet_topups.amount holds USD, so it cannot answer that.
                'metadata' => array_merge($topup->metadata ?? [], [
                    'amount_idr' => $amountIdr,
                ]),
            ]);

            $result = app(\App\Services\PakasirService::class)->createQris($orderId, $amountIdr);

            if (config('services.pakasir.auto_simulate')) {
                app(\App\Services\PakasirService::class)->simulatePayment($orderId, $amountIdr);
                app(\App\Services\PakasirFulfillmentService::class)->fulfill($topup, sandboxOnly: true);
            }

            $payload = $result['payment'] ?? [];

            return response()->json([
                'orderId' => $orderId,
                'amountIdr' => $amountIdr,
                'qrString' => $payload['payment_number'] ?? null,
                'expiredAt' => $payload['expired_at'] ?? null,
            ]);
        }

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

            $intent = $stripe->paymentIntents->create([
                'amount' => $stripeAmount,
                'currency' => strtolower($currency),

                // Card only for now. QRIS will add its own method later.
                'payment_method_types' => ['card'],

                // The webhook finds the top-up by this. Without it the payment
                // succeeds and the wallet never moves (trap 1).
                'metadata' => [
                    'wallet_topup_id' => (string) $topup->id,
                    'organization_id' => (string) $organization->id,
                ],
            ]);

            $topup->update([
                'stripe_payment_intent_id' => $intent->id,
            ]);

            Log::info('BillingTopupController@store PAYMENT INTENT CREATED', [
                'topup_id' => $topup->id,
                'payment_intent' => $intent->id,
                'amount' => $stripeAmount,
                'currency' => $currency,
            ]);

            // JSON, not a redirect — the browser stays on our page.
            return response()->json([
                'clientSecret' => $intent->client_secret,
                'topupId' => $topup->id,
            ]);
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

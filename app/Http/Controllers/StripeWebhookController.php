<?php

namespace App\Http\Controllers;

use App\Models\WalletTopup;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use Illuminate\Support\Facades\Log;

class StripeWebhookController extends Controller
{
    public function handle(Request $request)
    {
        Log::info('StripeWebhookController@handle RECEIVED', [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'ip' => $request->ip(),
            'has_signature' => $request->hasHeader('Stripe-Signature'),
            'content_length' => strlen($request->getContent()),
        ]);

        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');
        $secret = config('services.stripe.webhook_secret');

        Log::info('StripeWebhookController@handle CONFIG', [
            'has_webhook_secret' => !empty($secret),
            'secret_prefix' => $secret
                ? substr($secret, 0, 8)
                : null,
            'signature_prefix' => $signature
                ? substr($signature, 0, 20)
                : null,
        ]);

        if (!$secret) {
            Log::error(
                'StripeWebhookController@handle NO WEBHOOK SECRET'
            );

            return response()->json([
                'error' =>
                    'Stripe webhook secret is not configured.',
            ], 500);
        }

        try {
            $event = Webhook::constructEvent(
                $payload,
                $signature,
                $secret
            );

            Log::info(
                'StripeWebhookController@handle SIGNATURE VERIFIED',
                [
                    'event_id' => $event->id,
                    'event_type' => $event->type,
                ]
            );
        } catch (\UnexpectedValueException $e) {
            Log::error(
                'StripeWebhookController@handle INVALID PAYLOAD',
                [
                    'message' => $e->getMessage(),
                ]
            );

            return response()->json([
                'error' => 'Invalid payload.',
            ], 400);
        } catch (
            \Stripe\Exception\SignatureVerificationException $e
        ) {
            Log::error(
                'StripeWebhookController@handle INVALID SIGNATURE',
                [
                    'message' => $e->getMessage(),
                ]
            );

            return response()->json([
                'error' => 'Invalid signature.',
            ], 400);
        }

        Log::info(
            'StripeWebhookController@handle EVENT',
            [
                'event_id' => $event->id,
                'event_type' => $event->type,
            ]
        );

        if ($event->type !== 'checkout.session.completed') {
            Log::info(
                'StripeWebhookController@handle EVENT IGNORED',
                [
                    'event_type' => $event->type,
                ]
            );

            return response()->json([
                'received' => true,
            ]);
        }

        $session = $event->data->object;

        Log::info(
            'StripeWebhookController@handle CHECKOUT SESSION',
            [
                'session_id' => $session->id,
                'payment_status' => $session->payment_status ?? null,
                'status' => $session->status ?? null,
                'payment_intent' => $session->payment_intent ?? null,
                'metadata' => $session->metadata?->toArray(),
            ]
        );

        $topupId = $session->metadata->wallet_topup_id ?? null;

        if (!$topupId) {
            Log::warning(
                'StripeWebhookController@handle TOPUP ID MISSING',
                [
                    'session_id' => $session->id,
                    'metadata' => $session->metadata?->toArray(),
                ]
            );

            return response()->json([
                'error' =>
                    'Wallet top-up ID missing from Stripe metadata.',
            ], 400);
        }

        Log::info(
            'StripeWebhookController@handle TOPUP FOUND IN METADATA',
            [
                'topup_id' => $topupId,
                'session_id' => $session->id,
            ]
        );

        DB::transaction(function () use (
            $topupId,
            $session
        ) {
            $topup = WalletTopup::query()
                ->lockForUpdate()
                ->find($topupId);

            Log::info(
                'StripeWebhookController@handle TOPUP LOOKUP',
                [
                    'topup_id' => $topupId,
                    'found' => $topup !== null,
                    'status' => $topup?->status,
                ]
            );

            if (!$topup) {
                throw new \RuntimeException(
                    "Wallet top-up {$topupId} not found."
                );
            }

            if ($topup->status === 'paid') {
                Log::info(
                    'StripeWebhookController@handle ALREADY PAID',
                    [
                        'topup_id' => $topup->id,
                    ]
                );

                return;
            }

            if (
                $topup->stripe_checkout_session_id &&
                $topup->stripe_checkout_session_id !== $session->id
            ) {
                Log::error(
                    'StripeWebhookController@handle SESSION MISMATCH',
                    [
                        'topup_id' => $topup->id,
                        'database_session_id' =>
                            $topup->stripe_checkout_session_id,
                        'stripe_session_id' => $session->id,
                    ]
                );

                throw new \RuntimeException(
                    'Stripe Checkout Session does not match wallet top-up.'
                );
            }

            if (($session->payment_status ?? null) !== 'paid') {
                Log::warning(
                    'StripeWebhookController@handle PAYMENT NOT PAID',
                    [
                        'topup_id' => $topup->id,
                        'payment_status' =>
                            $session->payment_status ?? null,
                    ]
                );

                return;
            }

            Log::info(
                'StripeWebhookController@handle CREDITING WALLET',
                [
                    'topup_id' => $topup->id,
                    'organization_id' => $topup->organization_id,
                    'wallet_amount_usd_cents' =>
                        $topup->wallet_amount_usd_cents,
                ]
            );

            $walletService = app(
                WalletService::class
            );

            $transaction = $walletService->credit(
                organization: $topup->organization,
                sourceCurrency: $topup->currency,
                sourceAmount: (float) $topup->amount,
                exchangeRate: (float) $topup->exchange_rate,
                amountUsdCents: (int) $topup->wallet_amount_usd_cents,
                type: 'topup',
                description: 'Wallet top-up via Stripe',
                reference: $topup,
                createdBy: $topup->created_by,
                metadata: [
                    'stripe_checkout_session_id' =>
                        $session->id,
                    'stripe_payment_intent_id' =>
                        $session->payment_intent ?? null,
                ],
            );

            Log::info(
                'StripeWebhookController@handle WALLET CREDITED',
                [
                    'topup_id' => $topup->id,
                    'wallet_transaction_id' => $transaction->id,
                    'amount_usd_cents' =>
                        $transaction->amount_usd_cents,
                    'balance_after_usd_cents' =>
                        $transaction->balance_after_usd_cents,
                ]
            );

            $topup->update([
                'status' => 'paid',
                'stripe_payment_intent_id' =>
                    $session->payment_intent ?? null,
                'paid_at' => now(),
            ]);

            Log::info(
                'StripeWebhookController@handle TOPUP MARKED PAID',
                [
                    'topup_id' => $topup->id,
                    'payment_intent' =>
                        $session->payment_intent ?? null,
                ]
            );
        });

        Log::info(
            'StripeWebhookController@handle COMPLETE',
            [
                'event_id' => $event->id,
                'session_id' => $session->id,
                'topup_id' => $topupId,
            ]
        );

        return response()->json([
            'received' => true,
        ]);
    }
}
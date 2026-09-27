<?php

namespace App\Http\Controllers;

use App\Models\WalletTopup;
use App\Observability\Telemetry;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Stripe\Webhook;

class StripeWebhookController extends Controller
{
    public function handle(Request $request)
    {
        app(Telemetry::class)->event('payment.webhook.received', [
            'app.outcome' => 'success',
            'payment.provider' => 'stripe',
        ]);

        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');
        $secret = config('services.stripe.webhook_secret');

        if (! $secret) {
            app(Telemetry::class)->event('payment.webhook.rejected', [
                'app.outcome' => 'rejected',
                'app.reason' => 'missing_configuration',
                'payment.provider' => 'stripe',
            ]);

            return response()->json([
                'error' => 'Stripe webhook secret is not configured.',
            ], 500);
        }

        try {
            $event = Webhook::constructEvent(
                $payload,
                $signature,
                $secret
            );

        } catch (\UnexpectedValueException $e) {
            app(Telemetry::class)->event('payment.webhook.rejected', [
                'app.outcome' => 'rejected',
                'app.reason' => 'invalid_payload',
                'payment.provider' => 'stripe',
                'error.type' => $e::class,
            ]);

            return response()->json([
                'error' => 'Invalid payload.',
            ], 400);
        } catch (
            \Stripe\Exception\SignatureVerificationException $e
        ) {
            app(Telemetry::class)->event('payment.webhook.rejected', [
                'app.outcome' => 'rejected',
                'app.reason' => 'invalid_signature',
                'payment.provider' => 'stripe',
                'error.type' => $e::class,
            ]);

            return response()->json([
                'error' => 'Invalid signature.',
            ], 400);
        }

        switch ($event->type) {
            case 'checkout.session.completed':
                return $this->handleCheckoutCompleted($event->data->object);

            case 'payment_intent.succeeded':
                return $this->handlePaymentIntentSucceeded($event->data->object);

            case 'invoice.paid':
                return $this->handleInvoicePaid($event->data->object);

            case 'invoice.payment_failed':
                return $this->handleInvoiceFailed($event->data->object);

            case 'customer.subscription.deleted':
                return $this->handleSubscriptionDeleted($event->data->object);

            default:
                app(Telemetry::class)->event('payment.webhook.skipped', [
                    'app.outcome' => 'skipped',
                    'app.reason' => 'ignored_type',
                    'payment.provider' => 'stripe',
                ]);

                return response()->json(['received' => true]);
        }
    }

    protected function handleCheckoutCompleted($session)
    {
        $topupId = $session->metadata->wallet_topup_id ?? null;

        if (! $topupId) {
            app(Telemetry::class)->event('payment.webhook.rejected', [
                'app.outcome' => 'rejected',
                'app.reason' => 'invalid_payload',
                'payment.provider' => 'stripe',
            ]);

            return response()->json([
                'error' => 'Wallet top-up ID missing from Stripe metadata.',
            ], 400);
        }

        DB::transaction(function () use (
            $topupId,
            $session
        ) {
            $topup = WalletTopup::query()
                ->lockForUpdate()
                ->find($topupId);

            if (! $topup) {
                app(Telemetry::class)->event('payment.webhook.rejected', [
                    'app.outcome' => 'rejected',
                    'app.reason' => 'unknown_record',
                    'payment.provider' => 'stripe',
                ]);
                throw new \RuntimeException(
                    "Wallet top-up {$topupId} not found."
                );
            }

            if ($topup->status === 'paid') {
                app(Telemetry::class)->eventAfterCommit('payment.webhook.skipped', [
                    'app.outcome' => 'skipped',
                    'app.reason' => 'duplicate',
                    'payment.provider' => 'stripe',
                ]);

                return;
            }

            if (
                $topup->stripe_checkout_session_id &&
                $topup->stripe_checkout_session_id !== $session->id
            ) {
                app(Telemetry::class)->event('payment.webhook.rejected', [
                    'app.outcome' => 'rejected',
                    'app.reason' => 'invalid_payload',
                    'payment.provider' => 'stripe',
                ]);

                throw new \RuntimeException(
                    'Stripe Checkout Session does not match wallet top-up.'
                );
            }

            if (($session->payment_status ?? null) !== 'paid') {
                app(Telemetry::class)->eventAfterCommit('payment.webhook.skipped', [
                    'app.outcome' => 'pending',
                    'app.reason' => 'pending',
                    'payment.provider' => 'stripe',
                ]);

                return;
            }

            $walletService = app(
                WalletService::class
            );

            $walletService->credit(
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
                    'stripe_checkout_session_id' => $session->id,
                    'stripe_payment_intent_id' => $session->payment_intent ?? null,
                ],
            );

            $topup->update([
                'status' => 'paid',
                'stripe_payment_intent_id' => $session->payment_intent ?? null,
                'paid_at' => now(),
            ]);

            app(Telemetry::class)->eventAfterCommit('payment.fulfillment.completed', [
                'app.outcome' => 'success',
                'payment.provider' => 'stripe',
            ]);
        });

        return response()->json([
            'received' => true,
        ]);
    }

    protected function handlePaymentIntentSucceeded($intent)
    {
        $topupId = $intent->metadata->wallet_topup_id ?? null;

        // Subscription invoices also produce PaymentIntents. Those carry no
        // wallet_topup_id and are handled by invoice.paid instead.
        if (! $topupId) {
            app(Telemetry::class)->event('payment.webhook.skipped', [
                'app.outcome' => 'skipped',
                'app.reason' => 'ignored_type',
                'payment.provider' => 'stripe',
            ]);

            return response()->json(['received' => true]);
        }

        DB::transaction(function () use ($topupId, $intent) {
            $topup = WalletTopup::query()
                ->lockForUpdate()
                ->find($topupId);

            if (! $topup) {
                app(Telemetry::class)->event('payment.webhook.rejected', [
                    'app.outcome' => 'rejected',
                    'app.reason' => 'unknown_record',
                    'payment.provider' => 'stripe',
                ]);

                return;
            }

            // Idempotency: Stripe retries, and the client may also have
            // triggered a reload. Credit once (trap 2).
            if ($topup->status === 'paid') {
                app(Telemetry::class)->eventAfterCommit('payment.webhook.skipped', [
                    'app.outcome' => 'skipped',
                    'app.reason' => 'duplicate',
                    'payment.provider' => 'stripe',
                ]);

                return;
            }

            app(WalletService::class)->credit(
                organization: $topup->organization,
                sourceCurrency: $topup->currency,
                sourceAmount: (float) $topup->amount,
                exchangeRate: (float) $topup->exchange_rate,
                amountUsdCents: (int) $topup->wallet_amount_usd_cents,
                type: 'topup',
                description: 'Wallet top-up via card',
                reference: $topup,
                createdBy: $topup->created_by,
                metadata: ['stripe_payment_intent_id' => $intent->id],
            );

            $topup->update([
                'status' => 'paid',
                'paid_at' => now(),
            ]);

            app(Telemetry::class)->eventAfterCommit('payment.fulfillment.completed', [
                'app.outcome' => 'success',
                'payment.provider' => 'stripe',
            ]);
        });

        return response()->json(['received' => true]);
    }

    /**
     * Newer Stripe API versions nest the subscription under the invoice's
     * parent instead of exposing invoice.subscription. Webhook payloads are
     * serialised with whatever version the endpoint is pinned to, so accept
     * either shape.
     */
    protected function subscriptionIdFromInvoice($invoice): ?string
    {
        return $invoice->parent->subscription_details->subscription
            ?? $invoice->subscription
            ?? null;
    }

    protected function handleInvoicePaid($invoice)
    {
        $stripeSubscriptionId = $this->subscriptionIdFromInvoice($invoice);

        if (! $stripeSubscriptionId) {
            app(Telemetry::class)->event('payment.webhook.skipped', [
                'app.outcome' => 'skipped',
                'app.reason' => 'ignored_type',
                'payment.provider' => 'stripe',
            ]);

            return response()->json(['received' => true]);
        }

        DB::transaction(function () use ($invoice, $stripeSubscriptionId) {
            $subscription = \App\Models\Subscription::query()
                ->lockForUpdate()
                ->where('stripe_subscription_id', $stripeSubscriptionId)
                ->first();

            if (! $subscription) {
                app(Telemetry::class)->event('payment.webhook.rejected', [
                    'app.outcome' => 'rejected',
                    'app.reason' => 'unknown_record',
                    'payment.provider' => 'stripe',
                ]);

                return;
            }

            // invoice.period_end is the moment the invoice was cut, not the
            // end of the period it paid for. Taking it would expire the
            // subscription the instant it renewed.
            $periodEnd = $invoice->lines->data[0]->period->end ?? null;

            $subscription->update([
                'status' => 'active',
                'expired_at' => $periodEnd
                    ? \Carbon\Carbon::createFromTimestamp($periodEnd)
                    : $subscription->expired_at,
            ]);

            \App\Models\SubscriptionPayment::where('stripe_invoice_id', $invoice->id)
                ->update(['status' => 'paid', 'paid_at' => now()]);

            app(Telemetry::class)->eventAfterCommit('billing.subscription.renewed', [
                'app.outcome' => 'success',
                'payment.provider' => 'stripe',
            ]);
        });

        return response()->json(['received' => true]);
    }

    protected function handleInvoiceFailed($invoice)
    {
        $stripeSubscriptionId = $this->subscriptionIdFromInvoice($invoice);

        if (! $stripeSubscriptionId) {
            app(Telemetry::class)->event('payment.webhook.skipped', [
                'app.outcome' => 'skipped',
                'app.reason' => 'ignored_type',
                'payment.provider' => 'stripe',
            ]);

            return response()->json(['received' => true]);
        }

        $subscription = \App\Models\Subscription::query()
            ->where('stripe_subscription_id', $stripeSubscriptionId)
            ->first();

        if ($subscription) {
            $subscription->update(['status' => 'past_due']);
            app(Telemetry::class)->eventAfterCommit('billing.subscription.past_due', [
                'app.outcome' => 'failure',
                'payment.provider' => 'stripe',
            ]);
        } else {
            app(Telemetry::class)->event('payment.webhook.rejected', [
                'app.outcome' => 'rejected',
                'app.reason' => 'unknown_record',
                'payment.provider' => 'stripe',
            ]);
        }

        return response()->json(['received' => true]);
    }

    protected function handleSubscriptionDeleted($subscription)
    {
        $stripeSubscriptionId = $subscription->id ?? null;

        if (! $stripeSubscriptionId) {
            app(Telemetry::class)->event('payment.webhook.rejected', [
                'app.outcome' => 'rejected',
                'app.reason' => 'invalid_payload',
                'payment.provider' => 'stripe',
            ]);

            return response()->json(['received' => true]);
        }

        $sub = \App\Models\Subscription::query()
            ->where('stripe_subscription_id', $stripeSubscriptionId)
            ->first();

        if ($sub) {
            $sub->update([
                'plan' => 'free',
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ]);
            app(Telemetry::class)->eventAfterCommit('billing.subscription.cancelled', [
                'app.outcome' => 'success',
                'payment.provider' => 'stripe',
            ]);
        } else {
            app(Telemetry::class)->event('payment.webhook.rejected', [
                'app.outcome' => 'rejected',
                'app.reason' => 'unknown_record',
                'payment.provider' => 'stripe',
            ]);
        }

        return response()->json(['received' => true]);
    }
}

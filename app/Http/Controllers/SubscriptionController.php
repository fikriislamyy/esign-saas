<?php

namespace App\Http\Controllers;

use App\Models\CardInfo;
use App\Models\SubscriptionPayment;
use App\Services\ExchangeRateService;
use App\Services\PakasirService;
use App\Services\PlanService;
use App\Services\StripeService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class SubscriptionController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless($user?->isOwner(), 403);

        $organization = $user->organization;
        $planService = app(PlanService::class);
        $subscription = $planService->subscriptionFor($organization);

        return Inertia::render('Plan/Index', [
            'subscription' => [
                'plan' => $subscription->effectivePlan(),
                'status' => $subscription->status,
                'provider' => $subscription->provider,
                'subscribedAt' => $subscription->subscribed_at,
                'expiredAt' => $subscription->expired_at,
                'autoRenews' => $subscription->provider === 'stripe',
            ],
            'usage' => $planService->usage($organization),
            'plans' => config('plans'),
            'card' => $organization->cards()->where('is_default', true)->first(),
            'payments' => $organization->subscriptionPayments()
                ->latest()->limit(10)->get(),
        ]);
    }

    public function checkout(Request $request, StripeService $stripe)
    {
        $user = $request->user();
        abort_unless($user?->isOwner(), 403);

        $organization = $user->organization;
        $subscription = app(PlanService::class)->subscriptionFor($organization);

        $customerId = $subscription->stripe_customer_id;

        if (! $customerId) {
            $customer = $stripe->client()->customers->create([
                'email' => $user->email,
                'name' => $organization->name,
                'metadata' => ['organization_id' => $organization->id],
            ]);

            $customerId = $customer->id;
            $subscription->update(['stripe_customer_id' => $customerId]);
        }

        $setupIntent = $stripe->client()->setupIntents->create([
            'customer' => $customerId,
            'payment_method_types' => ['card'],
        ]);

        return response()->json(['clientSecret' => $setupIntent->client_secret]);
    }

    public function subscribe(Request $request, StripeService $stripe)
    {
        $user = $request->user();
        abort_unless($user?->isOwner(), 403);

        $validated = $request->validate([
            'plan' => ['required', Rule::in(['pro'])],
            'payment_method' => ['required', 'string'],
        ]);

        $organization = $user->organization;
        $subscription = app(PlanService::class)->subscriptionFor($organization);
        $planConfig = config('plans.'.$validated['plan']);

        abort_unless($planConfig['stripe_price_id'], 500, 'Stripe price is not configured.');

        $client = $stripe->client();

        $client->paymentMethods->attach($validated['payment_method'], [
            'customer' => $subscription->stripe_customer_id,
        ]);

        $client->customers->update($subscription->stripe_customer_id, [
            'invoice_settings' => [
                'default_payment_method' => $validated['payment_method'],
            ],
        ]);

        $stripeSubscription = $client->subscriptions->create([
            'customer' => $subscription->stripe_customer_id,
            'items' => [['price' => $planConfig['stripe_price_id']]],
            'metadata' => [
                'organization_id' => $organization->id,
                'plan' => $validated['plan'],
            ],
        ]);

        $pm = $client->paymentMethods->retrieve($validated['payment_method']);

        CardInfo::updateOrCreate(
            ['stripe_payment_method_id' => $pm->id],
            [
                'organization_id' => $organization->id,
                'card_brand' => $pm->card->brand,
                'card_last_four' => $pm->card->last4,
                'card_exp_month' => $pm->card->exp_month,
                'card_exp_year' => $pm->card->exp_year,
                'is_default' => true,
            ],
        );

        $subscription->update([
            'plan' => $validated['plan'],
            'status' => 'active',
            'provider' => 'stripe',
            'stripe_subscription_id' => $stripeSubscription->id,
            'subscribed_at' => now(),
            'expired_at' => Carbon::createFromTimestamp(
                $stripeSubscription->current_period_end
            ),
        ]);

        SubscriptionPayment::create([
            'organization_id' => $organization->id,
            'subscription_id' => $subscription->id,
            'plan' => $validated['plan'],
            'provider' => 'stripe',
            'currency' => 'USD',
            'amount' => $planConfig['price_usd_cents'],
            'amount_usd_cents' => $planConfig['price_usd_cents'],
            'exchange_rate' => 1.0,
            'status' => 'pending',
            'stripe_invoice_id' => $stripeSubscription->latest_invoice,
        ]);

        return redirect()->route('plan.index');
    }

    public function downgrade(Request $request, StripeService $stripe)
    {
        $user = $request->user();
        abort_unless($user?->isOwner(), 403);

        $organization = $user->organization;
        $subscription = app(PlanService::class)->subscriptionFor($organization);

        abort_unless($subscription->provider === 'stripe', 400, 'Only Stripe subscriptions can be downgraded.');

        if ($subscription->stripe_subscription_id) {
            $stripe->client()->subscriptions->cancel($subscription->stripe_subscription_id);
        }

        $subscription->update([
            'plan' => 'free',
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);

        return redirect()->route('plan.index')->with('message', 'Downgraded to Free plan.');
    }

    public function payWithQr(Request $request, PakasirService $pakasir)
    {
        $user = $request->user();
        abort_unless($user?->isOwner(), 403);

        $validated = $request->validate([
            'plan' => ['required', Rule::in(['pro'])],
        ]);

        $organization = $user->organization;
        $subscription = app(PlanService::class)->subscriptionFor($organization);
        $planConfig = config('plans.'.$validated['plan']);

        $rate = app(ExchangeRateService::class)->usdToIdr();

        if (! $rate || $rate <= 0) {
            return back()->withErrors([
                'plan' => 'The USD/IDR exchange rate is unavailable. Please try again shortly.',
            ]);
        }

        $amountIdr = (int) round(($planConfig['price_usd_cents'] / 100) * $rate);

        $orderId = 'SUB-'.$organization->id.'-'.now()->timestamp;

        $payment = SubscriptionPayment::create([
            'organization_id' => $organization->id,
            'subscription_id' => $subscription->id,
            'plan' => $validated['plan'],
            'provider' => 'pakasir',
            'order_id' => $orderId,
            'currency' => 'IDR',
            'amount' => $amountIdr,
            'amount_usd_cents' => $planConfig['price_usd_cents'],
            'exchange_rate' => $rate,
            'status' => 'pending',
        ]);

        $result = $pakasir->createQris($orderId, $amountIdr);

        return Inertia::render('Plan/Qr', [
            'orderId' => $orderId,
            'amountIdr' => $amountIdr,
            'qrString' => $result['payment_number'] ?? null,
            'expiredAt' => $result['expired_at'] ?? null,
        ]);
    }
}

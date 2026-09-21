<?php

namespace Tests\Unit\Models;

use App\Models\Subscription;
use Tests\TestCase;

class SubscriptionTest extends TestCase
{
    public function test_a_free_subscription_is_free(): void
    {
        $subscription = new Subscription(['plan' => 'free', 'status' => 'active']);

        $this->assertSame('free', $subscription->effectivePlan());
    }

    public function test_an_active_pro_subscription_with_no_expiry_is_pro(): void
    {
        $subscription = new Subscription(['plan' => 'pro', 'status' => 'active', 'expired_at' => null]);

        $this->assertSame('pro', $subscription->effectivePlan());
    }

    public function test_a_cancelled_pro_subscription_is_effectively_free(): void
    {
        $subscription = new Subscription(['plan' => 'pro', 'status' => 'cancelled']);

        $this->assertSame('free', $subscription->effectivePlan());
    }

    public function test_an_expired_pro_subscription_is_effectively_free(): void
    {
        $subscription = new Subscription(['plan' => 'pro', 'status' => 'active', 'expired_at' => now()->subMinute()]);

        $this->assertSame('free', $subscription->effectivePlan());
    }

    public function test_a_pro_subscription_expiring_in_the_future_is_still_pro(): void
    {
        $subscription = new Subscription(['plan' => 'pro', 'status' => 'active', 'expired_at' => now()->addDay()]);

        $this->assertSame('pro', $subscription->effectivePlan());
    }

    public function test_an_active_enterprise_subscription_is_enterprise(): void
    {
        $subscription = new Subscription(['plan' => 'enterprise', 'status' => 'active', 'expired_at' => null]);

        $this->assertSame('enterprise', $subscription->effectivePlan());
    }

    public function test_config_returns_the_effective_plans_entry(): void
    {
        $subscription = new Subscription(['plan' => 'pro', 'status' => 'cancelled']);

        $this->assertSame(config('plans.free'), $subscription->config());
    }
}

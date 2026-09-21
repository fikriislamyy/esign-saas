<?php

namespace Tests\Unit\Console;

use App\Models\Organization;
use App\Models\Subscription;
use App\Models\User;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpireSubscriptionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Configure with dummy Stripe key so StripeService doesn't throw
        config(['services.stripe.secret' => 'sk_test_dummy']);
    }

    public function test_pakasir_pro_expired_yesterday_is_downgraded(): void
    {
        $org = Organization::create(['name' => 'Acme']);
        $subscription = $org->subscription()->create([
            'plan' => 'pro',
            'status' => 'active',
            'provider' => 'pakasir',
            'expired_at' => now()->subDay(),
        ]);

        $this->artisan('subscriptions:expire')->assertSuccessful();

        $subscription->refresh();
        $this->assertSame('free', $subscription->plan);
        $this->assertSame('cancelled', $subscription->status);
        $this->assertNotNull($subscription->cancelled_at);
    }

    public function test_pro_expiring_tomorrow_is_untouched(): void
    {
        $org = Organization::create(['name' => 'Acme']);
        $subscription = $org->subscription()->create([
            'plan' => 'pro',
            'status' => 'active',
            'provider' => 'pakasir',
            'expired_at' => now()->addDay(),
        ]);

        $this->artisan('subscriptions:expire')->assertSuccessful();

        $subscription->refresh();
        $this->assertSame('pro', $subscription->plan);
        $this->assertSame('active', $subscription->status);
    }

    public function test_free_with_past_expired_at_is_untouched(): void
    {
        $org = Organization::create(['name' => 'Acme']);
        $subscription = $org->subscription()->create([
            'plan' => 'free',
            'status' => 'active',
            'expired_at' => now()->subDay(),
        ]);

        $this->artisan('subscriptions:expire')->assertSuccessful();

        $subscription->refresh();
        $this->assertSame('free', $subscription->plan);
        $this->assertSame('active', $subscription->status);
    }

    public function test_subscription_with_null_expired_at_is_untouched(): void
    {
        $org = Organization::create(['name' => 'Acme']);
        $subscription = $org->subscription()->create([
            'plan' => 'pro',
            'status' => 'active',
            'provider' => 'pakasir',
            'expired_at' => null,
        ]);

        $this->artisan('subscriptions:expire')->assertSuccessful();

        $subscription->refresh();
        $this->assertSame('pro', $subscription->plan);
    }

    public function test_enterprise_expired_is_downgraded(): void
    {
        $org = Organization::create(['name' => 'Acme']);
        $subscription = $org->subscription()->create([
            'plan' => 'enterprise',
            'status' => 'active',
            'provider' => 'pakasir',
            'expired_at' => now()->subDay(),
        ]);

        $this->artisan('subscriptions:expire')->assertSuccessful();

        $subscription->refresh();
        $this->assertSame('free', $subscription->plan);
        $this->assertSame('cancelled', $subscription->status);
    }

    public function test_dry_run_changes_nothing(): void
    {
        $org = Organization::create(['name' => 'Acme']);
        $subscription = $org->subscription()->create([
            'plan' => 'pro',
            'status' => 'active',
            'provider' => 'pakasir',
            'expired_at' => now()->subDay(),
        ]);

        $this->artisan('subscriptions:expire', ['--dry-run' => true])->assertSuccessful();

        $subscription->refresh();
        $this->assertSame('pro', $subscription->plan);
        $this->assertSame('active', $subscription->status);
    }

    public function test_output_shows_downgraded_count(): void
    {
        $org1 = Organization::create(['name' => 'Org1']);
        $org1->subscription()->create([
            'plan' => 'pro',
            'status' => 'active',
            'provider' => 'pakasir',
            'expired_at' => now()->subDay(),
        ]);

        $org2 = Organization::create(['name' => 'Org2']);
        $org2->subscription()->create([
            'plan' => 'enterprise',
            'status' => 'active',
            'provider' => 'pakasir',
            'expired_at' => now()->subDay(),
        ]);

        $this->artisan('subscriptions:expire')
            ->expectsOutput('Subscriptions downgraded: 2');
    }
}

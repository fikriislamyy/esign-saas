<?php

namespace Tests\Unit\Services;

use App\Models\Organization;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Models\WalletTopup;
use App\Services\PakasirFulfillmentService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PakasirFulfillmentServiceTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.pakasir.project' => 'project', 'services.pakasir.api_key' => 'key']);
        $this->organization = Organization::create(['name' => 'Acme']);
        $this->owner = User::factory()->create(['organization_id' => $this->organization->id, 'role' => 'owner']);
        $this->organization->subscription()->create(['plan' => 'free', 'status' => 'active']);
    }

    private function payment(): SubscriptionPayment
    {
        return SubscriptionPayment::create([
            'organization_id' => $this->organization->id,
            'subscription_id' => $this->organization->subscription->id,
            'plan' => 'pro', 'provider' => 'pakasir', 'order_id' => 'subscription-order',
            'currency' => 'IDR', 'amount' => 160000, 'amount_usd_cents' => 1000,
        ]);
    }

    private function service(): PakasirFulfillmentService
    {
        return app(PakasirFulfillmentService::class);
    }

    public function test_amount_idr_uses_the_subscription_payment_amount(): void
    {
        $payment = $this->payment();

        $this->assertSame(160000, $this->service()->amountIdr($payment));
    }

    public function test_amount_idr_uses_topup_metadata_or_zero(): void
    {
        $wallet = app(WalletService::class)->getOrCreateWallet($this->organization);
        $topup = WalletTopup::create(['wallet_id' => $wallet->id, 'organization_id' => $this->organization->id, 'currency' => 'IDR', 'amount' => 160000, 'exchange_rate' => 16000, 'wallet_amount_usd_cents' => 1000, 'provider' => 'pakasir', 'metadata' => ['amount_idr' => 160000]]);
        $withoutMetadata = WalletTopup::create(['wallet_id' => $wallet->id, 'organization_id' => $this->organization->id, 'currency' => 'IDR', 'amount' => 1, 'exchange_rate' => 1, 'wallet_amount_usd_cents' => 1, 'provider' => 'pakasir']);

        $this->assertSame(160000, $this->service()->amountIdr($topup));
        $this->assertSame(0, $this->service()->amountIdr($withoutMetadata));
    }

    public function test_an_incomplete_payment_is_left_untouched(): void
    {
        Http::fake(['*/api/transactiondetail*' => Http::response(['transaction' => ['status' => 'pending']])]);
        $payment = $this->payment();

        $this->assertFalse($this->service()->fulfill($payment));
        $this->assertSame('pending', $payment->fresh()->status);
    }

    public function test_a_completed_subscription_payment_activates_pro(): void
    {
        Http::fake(['*/api/transactiondetail*' => Http::response(['transaction' => ['status' => 'completed', 'is_sandbox' => true]])]);
        $payment = $this->payment();

        $this->assertTrue($this->service()->fulfill($payment, true));
        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertSame('pro', $this->organization->subscription->fresh()->plan);
        $this->assertSame('pakasir', $this->organization->subscription->fresh()->provider);
    }
}

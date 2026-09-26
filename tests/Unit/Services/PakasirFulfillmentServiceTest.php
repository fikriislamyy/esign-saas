<?php

namespace Tests\Unit\Services;

use App\Models\Organization;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Models\WalletTopup;
use App\Services\PakasirFulfillmentService;
use App\Services\WalletService;
use Carbon\Carbon;
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

    private function topup(?array $metadata = ['amount_idr' => 160000]): WalletTopup
    {
        $wallet = app(WalletService::class)->getOrCreateWallet($this->organization);

        return WalletTopup::create([
            'wallet_id' => $wallet->id,
            'organization_id' => $this->organization->id,
            'currency' => 'IDR',
            'amount' => 160000,
            'exchange_rate' => 16000,
            'wallet_amount_usd_cents' => 1000,
            'provider' => 'pakasir',
            'order_id' => 'topup-order',
            'created_by' => $this->owner->id,
            'metadata' => $metadata,
        ]);
    }

    public function test_amount_idr_uses_the_subscription_payment_amount(): void
    {
        $payment = $this->payment();

        $this->assertSame(160000, $this->service()->amountIdr($payment));
    }

    public function test_amount_idr_uses_topup_metadata(): void
    {
        $topup = $this->topup();

        $this->assertSame(160000, $this->service()->amountIdr($topup));
    }

    public function test_amount_idr_without_topup_metadata_is_zero(): void
    {
        $withoutMetadata = $this->topup(null);

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
        $this->travelTo(Carbon::parse('2026-03-11 10:00:00'));
        Http::fake(['*/api/transactiondetail*' => Http::response(['transaction' => ['status' => 'completed', 'is_sandbox' => true]])]);
        $payment = $this->payment();

        $this->assertTrue($this->service()->fulfill($payment, true));
        $payment->refresh();
        $subscription = $this->organization->subscription->fresh();

        $this->assertSame('paid', $payment->status);
        $this->assertSame('2026-03-11 10:00:00', $payment->paid_at?->toDateTimeString());
        $this->assertSame('pro', $subscription->plan);
        $this->assertSame('active', $subscription->status);
        $this->assertSame('pakasir', $subscription->provider);
        $this->assertSame('2026-04-10 10:00:00', $subscription->expired_at?->toDateTimeString());
    }

    public function test_a_completed_topup_credits_the_wallet(): void
    {
        $this->travelTo(Carbon::parse('2026-03-11 10:00:00'));
        Http::fake(['*/api/transactiondetail*' => Http::response(['transaction' => ['status' => 'completed', 'is_sandbox' => true]])]);
        $topup = $this->topup();

        $this->assertTrue($this->service()->fulfill($topup));
        $topup->refresh();
        $wallet = $topup->wallet->fresh();
        $transaction = $wallet->transactions()->first();

        $this->assertSame('paid', $topup->status);
        $this->assertSame('2026-03-11 10:00:00', $topup->paid_at?->toDateTimeString());
        $this->assertSame(1000, $wallet->balance_usd_cents);
        $this->assertSame(WalletTopup::class, $transaction->reference_type);
        $this->assertSame((string) $topup->id, (string) $transaction->reference_id);
    }

    public function test_fulfilling_the_same_topup_twice_credits_only_once(): void
    {
        Http::fake(['*/api/transactiondetail*' => Http::response(['transaction' => ['status' => 'completed', 'is_sandbox' => true]])]);
        $topup = $this->topup();

        $this->assertTrue($this->service()->fulfill($topup));
        $this->assertTrue($this->service()->fulfill($topup->fresh()));

        $wallet = $topup->wallet->fresh();
        $this->assertSame(1000, $wallet->balance_usd_cents);
        $this->assertSame(1, $wallet->transactions()->count());
    }

    public function test_sandbox_only_rejects_a_non_sandbox_payment(): void
    {
        Http::fake(['*/api/transactiondetail*' => Http::response(['transaction' => ['status' => 'completed', 'is_sandbox' => false]])]);
        $payment = $this->payment();

        $this->assertFalse($this->service()->fulfill($payment, true));
        $this->assertSame('pending', $payment->fresh()->status);
    }
}

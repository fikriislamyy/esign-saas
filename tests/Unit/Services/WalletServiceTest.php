<?php

namespace Tests\Unit\Services;

use App\Exceptions\InsufficientWalletBalanceException;
use App\Models\Organization;
use App\Models\User;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalletServiceTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;
    private WalletService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::create(['name' => 'Acme']);
        $this->service = app(WalletService::class);
    }

    public function test_get_or_create_wallet_creates_with_zero_balance(): void
    {
        $wallet = $this->service->getOrCreateWallet($this->organization);

        $this->assertSame(0, $wallet->balance_usd_cents);
        $this->assertDatabaseCount('wallets', 1);
    }

    public function test_get_or_create_wallet_second_call_returns_same_row(): void
    {
        $wallet1 = $this->service->getOrCreateWallet($this->organization);
        $wallet2 = $this->service->getOrCreateWallet($this->organization);

        $this->assertSame($wallet1->id, $wallet2->id);
        $this->assertDatabaseCount('wallets', 1);
    }

    public function test_get_balance_for_new_organization_is_zero(): void
    {
        $balance = $this->service->getBalance($this->organization);

        $this->assertSame(0, $balance);
    }

    public function test_credit_raises_balance_and_returns_transaction(): void
    {
        $transaction = $this->service->credit(
            $this->organization,
            'usd',
            10.0,
            1.0,
            1000
        );

        $this->assertSame(0, $transaction->balance_before_usd_cents);
        $this->assertSame(1000, $transaction->balance_after_usd_cents);
        $this->assertSame(1000, $this->service->getBalance($this->organization));
        $this->assertDatabaseHas('wallet_transactions', [
            'wallet_id' => $transaction->wallet_id,
            'type' => 'topup',
        ]);
    }

    public function test_credit_uppercases_source_currency(): void
    {
        $transaction = $this->service->credit(
            $this->organization,
            'usd',
            10.0,
            1.0,
            1000
        );

        $this->assertSame('USD', $transaction->source_currency);
    }

    public function test_credit_with_reference_stores_morphable(): void
    {
        $owner = User::factory()->create(['organization_id' => $this->organization->id]);

        $transaction = $this->service->credit(
            $this->organization,
            'usd',
            10.0,
            1.0,
            1000,
            reference: $owner
        );

        $this->assertSame('App\Models\User', $transaction->reference_type);
        $this->assertSame($owner->id, $transaction->reference_id);
    }

    public function test_debit_lowers_balance_and_returns_transaction(): void
    {
        $this->service->credit($this->organization, 'usd', 10.0, 1.0, 2000);

        $transaction = $this->service->debit(
            $this->organization,
            'usd',
            5.0,
            1.0,
            1000
        );

        $this->assertSame(2000, $transaction->balance_before_usd_cents);
        $this->assertSame(1000, $transaction->balance_after_usd_cents);
        $this->assertSame(-1000, $transaction->amount_usd_cents);
        $this->assertSame(1000, $this->service->getBalance($this->organization));
    }

    public function test_debit_more_than_balance_throws_insufficient_exception(): void
    {
        $this->service->credit($this->organization, 'usd', 10.0, 1.0, 500);

        $this->expectException(InsufficientWalletBalanceException::class);

        $this->service->debit($this->organization, 'usd', 10.0, 1.0, 1000);
    }

    public function test_debit_more_than_balance_leaves_balance_and_transactions_unchanged(): void
    {
        $this->service->credit($this->organization, 'usd', 10.0, 1.0, 500);

        try {
            $this->service->debit($this->organization, 'usd', 10.0, 1.0, 1000);
        } catch (InsufficientWalletBalanceException) {
            // Expected
        }

        $this->assertSame(500, $this->service->getBalance($this->organization));
        $this->assertDatabaseCount('wallet_transactions', 1);
    }

    public function test_debit_exactly_the_balance_succeeds(): void
    {
        $this->service->credit($this->organization, 'usd', 10.0, 1.0, 1000);

        $this->service->debit($this->organization, 'usd', 10.0, 1.0, 1000);

        $this->assertSame(0, $this->service->getBalance($this->organization));
    }

    public function test_credit_default_type_is_topup(): void
    {
        $transaction = $this->service->credit(
            $this->organization,
            'usd',
            10.0,
            1.0,
            1000
        );

        $this->assertSame('topup', $transaction->type);
    }

    public function test_debit_default_type_is_signature(): void
    {
        $this->service->credit($this->organization, 'usd', 10.0, 1.0, 1000);

        $transaction = $this->service->debit(
            $this->organization,
            'usd',
            5.0,
            1.0,
            500
        );

        $this->assertSame('signature', $transaction->type);
    }
}

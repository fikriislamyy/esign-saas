<?php

namespace Tests\Unit\Models;

use App\Models\Organization;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_organization_without_a_wallet_has_a_zero_balance(): void
    {
        $organization = Organization::create(['name' => 'Acme']);

        $this->assertSame(0, $organization->wallet_balance_usd_cents);
    }

    public function test_an_organization_exposes_its_wallet_balance(): void
    {
        $organization = Organization::create(['name' => 'Acme']);
        app(WalletService::class)->credit($organization, 'usd', 15, 1, 1500);

        $this->assertSame(1500, $organization->fresh()->wallet_balance_usd_cents);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SharedInertiaPropsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_receive_null_account_props_and_public_configuration(): void
    {
        config(['services.recaptcha.site_key' => 'site-key']);

        $this->get('/')->assertInertia(fn ($page) => $page
            ->where('auth.user', null)
            ->where('wallet', null)
            ->where('plan', null)
            ->where('recaptchaSiteKey', 'site-key')
            ->has('salesMailto')
        );
    }

    public function test_an_owner_receives_the_free_plan_and_zero_wallet_balance(): void
    {
        $organization = Organization::create(['name' => 'Acme']);
        $owner = User::factory()->create(['organization_id' => $organization->id, 'role' => 'owner']);

        $this->actingAs($owner)->get('/dashboard')->assertInertia(fn ($page) => $page
            ->where('plan.key', 'free')
            ->where('plan.label', 'Free')
            ->where('wallet', null)
        );
    }

    public function test_an_owner_on_pro_receives_the_pro_plan(): void
    {
        $organization = Organization::create(['name' => 'Acme']);
        $owner = User::factory()->create(['organization_id' => $organization->id, 'role' => 'owner']);
        $organization->subscription()->create(['plan' => 'pro', 'status' => 'active']);

        $this->actingAs($owner)->get('/dashboard')->assertInertia(fn ($page) => $page
            ->where('plan.key', 'pro')
        );
    }
}

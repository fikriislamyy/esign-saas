<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesContactTest extends TestCase
{
    use RefreshDatabase;

    private function expectedLink(): string
    {
        return 'mailto:'.config('plans.enterprise.contact_email').'?subject=Enterprise%20Plan%20Inquiry';
    }

    public function test_the_landing_page_receives_the_sales_link(): void
    {
        $this->get('/')->assertInertia(fn ($page) => $page
            ->where('salesMailto', $this->expectedLink())
        );
    }

    public function test_the_plan_page_receives_the_sales_link(): void
    {
        $organization = Organization::create(['name' => 'Acme']);
        $owner = User::factory()->create([
            'organization_id' => $organization->id,
            'role' => 'owner',
        ]);

        $this->actingAs($owner)->get('/plan')->assertInertia(fn ($page) => $page
            ->where('salesMailto', $this->expectedLink())
        );
    }
}

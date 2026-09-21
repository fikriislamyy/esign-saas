<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthRedirectsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_dashboard_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_guests_are_redirected_from_documents_to_login(): void
    {
        $this->get('/documents')->assertRedirect('/login');
    }

    public function test_authenticated_users_are_redirected_from_login_to_dashboard(): void
    {
        $this->actingAs(User::factory()->create())->get('/login')->assertRedirect('/dashboard');
    }

    public function test_authenticated_users_are_redirected_from_register_to_dashboard(): void
    {
        $this->actingAs(User::factory()->create())->get('/register')->assertRedirect('/dashboard');
    }
}

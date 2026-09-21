<?php

namespace Tests\Feature\Auth;

use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        Mail::fake();

        $response = $this->post('/register', [
            'organization_name' => 'Test Organization',
            'name' => 'Test User',
            'email' => 'test@example.com',
            'country_code' => 'ID',
            'phone_number' => '+62 812 3456 7890',
            'terms' => 'on',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $this->assertAuthenticated();

        $response->assertRedirect(route('verification.notice'));

        $this->assertDatabaseHas('organizations', [
            'name' => 'Test Organization',
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'name' => 'Test User',
            'role' => 'owner',
        ]);
    }
}

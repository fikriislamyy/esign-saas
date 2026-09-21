<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class LoginThrottleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_sixth_failed_login_attempt_is_throttled(): void
    {
        $user = User::factory()->create();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->from('/login')->post('/login', ['email' => $user->email, 'password' => 'incorrect']);
        }

        $this->from('/login')->post('/login', ['email' => $user->email, 'password' => 'incorrect'])
            ->assertSessionHasErrors('email');
        $this->assertStringContainsString('Too many', session('errors')->first('email'));
    }

    public function test_a_correct_password_clears_four_prior_failed_attempts(): void
    {
        Mail::fake();
        $user = User::factory()->create();

        for ($attempt = 0; $attempt < 4; $attempt++) {
            $this->from('/login')->post('/login', ['email' => $user->email, 'password' => 'incorrect']);
        }

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect('/login/otp');
    }
}

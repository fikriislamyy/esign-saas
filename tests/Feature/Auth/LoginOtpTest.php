<?php

namespace Tests\Feature\Auth;

use App\Mail\LoginOtpMail;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use App\Services\LoginOtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class LoginOtpTest extends TestCase
{
    use RefreshDatabase;

    private function pending(User $user, bool $remember = false): static
    {
        return $this->withSession([
            'login_otp' => ['user_id' => $user->id, 'remember' => $remember],
        ]);
    }

    public function test_the_otp_page_redirects_to_login_when_nothing_is_pending(): void
    {
        $this->get('/login/otp')->assertRedirect('/login');
        $this->post('/login/otp', ['otp' => '123456'])->assertRedirect('/login');
        $this->post('/login/otp/resend')->assertRedirect('/login');
    }

    public function test_the_otp_page_renders_for_a_pending_login(): void
    {
        $user = User::factory()->create();

        $this->pending($user)->get('/login/otp')->assertOk();
    }

    public function test_a_correct_code_logs_the_user_in(): void
    {
        $user = User::factory()->create();
        $code = app(LoginOtpService::class)->generate($user);

        $response = $this->pending($user)->post('/login/otp', ['otp' => $code]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(RouteServiceProvider::HOME);
        $response->assertSessionMissing('login_otp');
    }

    public function test_remember_me_survives_the_otp_step(): void
    {
        $user = User::factory()->create(['remember_token' => null]);
        $code = app(LoginOtpService::class)->generate($user);

        $this->pending($user, remember: true)->post('/login/otp', ['otp' => $code]);

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->remember_token);
    }

    public function test_a_wrong_code_is_rejected(): void
    {
        $user = User::factory()->create();
        app(LoginOtpService::class)->generate($user);

        $response = $this->pending($user)->post('/login/otp', ['otp' => '000000']);

        $this->assertGuest();
        $response->assertSessionHasErrors('otp');
        $response->assertSessionHas('login_otp.user_id', $user->id);
    }

    public function test_a_code_that_is_gone_from_redis_is_rejected(): void
    {
        $user = User::factory()->create();
        $code = app(LoginOtpService::class)->generate($user);
        Redis::del("login_otp:{$user->id}:hash");

        $response = $this->pending($user)->post('/login/otp', ['otp' => $code]);

        $this->assertGuest();
        $response->assertSessionHasErrors('otp');
    }

    public function test_five_wrong_guesses_burn_the_code(): void
    {
        $user = User::factory()->create();
        $code = app(LoginOtpService::class)->generate($user);

        for ($i = 0; $i < 5; $i++) {
            $this->pending($user)->post('/login/otp', ['otp' => '000000']);
        }

        $this->pending($user)->post('/login/otp', ['otp' => $code]);

        $this->assertGuest();
    }

    public function test_resend_is_blocked_inside_the_cooldown(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        app(LoginOtpService::class)->generate($user);

        $response = $this->pending($user)->post('/login/otp/resend');

        $response->assertSessionHasErrors('otp');
        Mail::assertNothingSent();
    }

    public function test_resend_sends_a_new_code_after_the_cooldown(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        app(LoginOtpService::class)->generate($user);
        Redis::del("login_otp:{$user->id}:cooldown");

        $response = $this->pending($user)->post('/login/otp/resend');

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('status', 'login-code-sent');
        Mail::assertSent(LoginOtpMail::class, 1);
    }

    public function test_resend_is_blocked_after_five_sends(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $service = app(LoginOtpService::class);

        for ($i = 0; $i < 5; $i++) {
            $service->generate($user);
        }
        Redis::del("login_otp:{$user->id}:cooldown");

        $response = $this->pending($user)->post('/login/otp/resend');

        $response->assertSessionHasErrors('otp');
        Mail::assertNothingSent();
    }

    public function test_a_correct_code_also_verifies_an_unverified_email(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);
        $code = app(LoginOtpService::class)->generate($user);

        $this->pending($user)->post('/login/otp', ['otp' => $code]);

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_a_logged_in_user_is_sent_away_from_the_otp_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/login/otp')->assertRedirect(RouteServiceProvider::HOME);
    }
}

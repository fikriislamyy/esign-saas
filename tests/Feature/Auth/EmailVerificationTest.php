<?php

namespace Tests\Feature\Auth;

use App\Mail\EmailVerificationOtpMail;
use App\Models\OtpVerification;
use App\Models\User;
use App\Services\EmailVerificationOtpService;
use App\Providers\RouteServiceProvider;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_verification_screen_can_be_rendered(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => null,
        ]);

        $response = $this->actingAs($user)->get('/verify-email');

        $response->assertStatus(200);
    }

    public function test_email_can_be_verified_with_valid_otp(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);
        Event::fake();

        $otp = app(EmailVerificationOtpService::class)->generate($user);

        $response = $this->actingAs($user)->post('/verify-email', ['otp' => $otp]);

        Event::assertDispatched(Verified::class);
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $response->assertRedirect(RouteServiceProvider::HOME.'?verified=1');
    }

    public function test_email_is_not_verified_with_wrong_otp(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);

        app(EmailVerificationOtpService::class)->generate($user);

        $response = $this->actingAs($user)->post('/verify-email', ['otp' => '000000']);

        $response->assertSessionHasErrors('otp');
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_email_is_not_verified_with_expired_otp(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);

        $otp = app(EmailVerificationOtpService::class)->generate($user);
        OtpVerification::where('user_id', $user->id)->update(['expired_at' => now()->subMinute()]);

        $response = $this->actingAs($user)->post('/verify-email', ['otp' => $otp]);

        $response->assertSessionHasErrors('otp');
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_otp_email_is_sent_on_registration(): void
    {
        Mail::fake();

        $this->post('/register', [
            'organization_name' => 'Test Organization',
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        Mail::assertSent(EmailVerificationOtpMail::class, fn ($mail) => $mail->hasTo('test@example.com'));
        $this->assertDatabaseCount('otp_verifications', 1);
    }
}

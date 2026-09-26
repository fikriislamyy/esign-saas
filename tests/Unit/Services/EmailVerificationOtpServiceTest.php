<?php

namespace Tests\Unit\Services;

use App\Models\OtpVerification;
use App\Models\User;
use App\Services\EmailVerificationOtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailVerificationOtpServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private EmailVerificationOtpService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->service = app(EmailVerificationOtpService::class);
    }

    public function test_generate_returns_six_digits(): void
    {
        $otp = $this->service->generate($this->user);

        $this->assertIsNumeric($otp);
        $this->assertSame(6, strlen($otp));
        $this->assertGreaterThanOrEqual(100000, (int) $otp);
        $this->assertLessThanOrEqual(999999, (int) $otp);
    }

    public function test_generate_stores_one_row(): void
    {
        $this->service->generate($this->user);

        $this->assertDatabaseCount('otp_verifications', 1);
    }

    public function test_generate_sets_expiry_10_minutes(): void
    {
        $before = now();
        $otp = $this->service->generate($this->user);

        $record = OtpVerification::where('user_id', $this->user->id)->first();

        $diffInMinutes = $record->expired_at->diffInMinutes($before);
        $this->assertGreaterThanOrEqual(9, $diffInMinutes);
        $this->assertLessThanOrEqual(10, $diffInMinutes);
    }

    public function test_generate_second_call_replaces_first(): void
    {
        $otp1 = $this->service->generate($this->user);
        $otp2 = $this->service->generate($this->user);

        $this->assertDatabaseCount('otp_verifications', 1);
        $this->assertDatabaseHas('otp_verifications', ['otp' => $otp2]);
    }

    public function test_verify_correct_otp_returns_true(): void
    {
        $otp = $this->service->generate($this->user);

        $this->assertTrue($this->service->verify($this->user, $otp));
    }

    public function test_verify_correct_otp_deletes_row(): void
    {
        $otp = $this->service->generate($this->user);
        $this->service->verify($this->user, $otp);

        $this->assertDatabaseCount('otp_verifications', 0);
    }

    public function test_verify_wrong_otp_returns_false(): void
    {
        $this->service->generate($this->user);

        $this->assertFalse($this->service->verify($this->user, '000000'));
    }

    public function test_verify_wrong_otp_keeps_row(): void
    {
        $otp = $this->service->generate($this->user);
        $this->service->verify($this->user, '000000');

        $this->assertDatabaseCount('otp_verifications', 1);
        $this->assertDatabaseHas('otp_verifications', ['otp' => $otp]);
    }

    public function test_verify_expired_otp_returns_false(): void
    {
        $otp = $this->service->generate($this->user);
        OtpVerification::where('user_id', $this->user->id)->update(
            ['expired_at' => now()->subMinute()]
        );

        $this->assertFalse($this->service->verify($this->user, $otp));
    }

    public function test_verify_twice_second_returns_false(): void
    {
        $otp = $this->service->generate($this->user);

        $this->assertTrue($this->service->verify($this->user, $otp));
        $this->assertFalse($this->service->verify($this->user, $otp));
    }
}

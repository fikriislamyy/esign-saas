<?php

namespace Tests\Unit\Services;

use App\Mail\LoginOtpMail;
use App\Models\User;
use App\Services\LoginOtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class LoginOtpServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private LoginOtpService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->service = app(LoginOtpService::class);
    }

    protected function tearDown(): void
    {
        Redis::flushDb();
        parent::tearDown();
    }

    public function test_generate_returns_six_digits(): void
    {
        $otp = $this->service->generate($this->user);

        $this->assertIsNumeric($otp);
        $this->assertSame(6, strlen($otp));
        $this->assertGreaterThanOrEqual(100000, (int) $otp);
        $this->assertLessThanOrEqual(999999, (int) $otp);
    }

    public function test_generate_stores_bcrypt_hash(): void
    {
        $otp = $this->service->generate($this->user);
        $hash = Redis::get("login_otp:{$this->user->id}:hash");

        $this->assertStringStartsWith('$2y$', $hash);
        $this->assertTrue(Hash::check($otp, $hash));
    }

    public function test_generate_sets_hash_ttl_600_seconds(): void
    {
        $this->service->generate($this->user);
        $ttl = Redis::ttl("login_otp:{$this->user->id}:hash");

        $this->assertGreaterThan(0, $ttl);
        $this->assertLessThanOrEqual(600, $ttl);
    }

    public function test_generate_sets_cooldown_ttl_under_60(): void
    {
        $this->service->generate($this->user);
        $ttl = Redis::ttl("login_otp:{$this->user->id}:cooldown");

        $this->assertGreaterThan(0, $ttl);
        $this->assertLessThanOrEqual(60, $ttl);
    }

    public function test_generate_deletes_previous_attempts(): void
    {
        $this->service->generate($this->user);
        Redis::set("login_otp:{$this->user->id}:attempts", 3);

        $this->service->generate($this->user);

        $this->assertNull(Redis::get("login_otp:{$this->user->id}:attempts"));
    }

    public function test_generate_increments_sends_counter(): void
    {
        $this->service->generate($this->user);
        $this->assertSame(1, (int) Redis::get("login_otp:{$this->user->id}:sends"));

        $this->service->generate($this->user);
        $this->assertSame(2, (int) Redis::get("login_otp:{$this->user->id}:sends"));
    }

    public function test_verify_correct_otp_returns_true(): void
    {
        $otp = $this->service->generate($this->user);

        $this->assertTrue($this->service->verify($this->user, $otp));
    }

    public function test_verify_correct_otp_deletes_hash(): void
    {
        $otp = $this->service->generate($this->user);
        $this->service->verify($this->user, $otp);

        $this->assertNull(Redis::get("login_otp:{$this->user->id}:hash"));
    }

    public function test_verify_wrong_otp_returns_false(): void
    {
        $this->service->generate($this->user);

        $this->assertFalse($this->service->verify($this->user, '000000'));
    }

    public function test_verify_wrong_otp_increments_attempts(): void
    {
        $this->service->generate($this->user);
        $this->service->verify($this->user, '000000');

        $this->assertSame(1, (int) Redis::get("login_otp:{$this->user->id}:attempts"));
    }

    public function test_verify_missing_hash_returns_false(): void
    {
        $this->assertFalse($this->service->verify($this->user, '123456'));
    }

    public function test_verify_six_wrong_attempts_then_correct_returns_false(): void
    {
        $otp = $this->service->generate($this->user);

        for ($i = 0; $i < 5; $i++) {
            $this->assertFalse($this->service->verify($this->user, '000000'));
        }

        $this->assertFalse($this->service->verify($this->user, $otp));
    }

    public function test_verify_six_wrong_attempts_then_correct_deletes_hash(): void
    {
        $otp = $this->service->generate($this->user);

        for ($i = 0; $i < 5; $i++) {
            $this->service->verify($this->user, '000000');
        }

        $this->service->verify($this->user, $otp);

        $this->assertNull(Redis::get("login_otp:{$this->user->id}:hash"));
    }

    public function test_retry_after_right_after_generate_is_positive(): void
    {
        $this->service->generate($this->user);

        $retryAfter = $this->service->retryAfter($this->user);

        $this->assertGreaterThan(0, $retryAfter);
        $this->assertLessThanOrEqual(60, $retryAfter);
    }

    public function test_retry_after_after_deleting_cooldown_is_zero(): void
    {
        $this->service->generate($this->user);
        Redis::del("login_otp:{$this->user->id}:cooldown");

        $retryAfter = $this->service->retryAfter($this->user);

        $this->assertSame(0, $retryAfter);
    }

    public function test_retry_after_after_5_generates_and_deleted_cooldown_is_positive(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->service->generate($this->user);
        }
        Redis::del("login_otp:{$this->user->id}:cooldown");

        $retryAfter = $this->service->retryAfter($this->user);

        $this->assertGreaterThan(0, $retryAfter);
        $this->assertLessThanOrEqual(600, $retryAfter);
    }

    public function test_send_mails_the_otp(): void
    {
        Mail::fake();

        $this->service->send($this->user);

        Mail::assertSent(LoginOtpMail::class, function ($mail) {
            return $mail->hasTo($this->user->email);
        });
    }
}

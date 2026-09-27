<?php

namespace App\Services;

use App\Mail\LoginOtpMail;
use App\Models\User;
use App\Observability\Telemetry;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redis;

class LoginOtpService
{
    public const EXPIRES_IN_SECONDS = 600;

    public const RESEND_COOLDOWN_SECONDS = 60;

    public const MAX_SENDS_PER_WINDOW = 5;

    public const MAX_VERIFY_ATTEMPTS = 5;

    public function send(User $user): void
    {
        $otp = $this->generate($user);

        app(Telemetry::class)->submitMail('login_otp', fn () => Mail::to($user->email)->send(new LoginOtpMail($user, $otp)));
        app(Telemetry::class)->event('auth.otp.sent', [
            'app.outcome' => 'success',
            'app.reason' => 'login',
        ]);
    }

    public function generate(User $user): string
    {
        $otp = (string) random_int(100000, 999999);

        Redis::setex($this->key($user, 'hash'), self::EXPIRES_IN_SECONDS, Hash::make($otp));
        Redis::del($this->key($user, 'attempts'));
        Redis::setex($this->key($user, 'cooldown'), self::RESEND_COOLDOWN_SECONDS, 1);

        if ((int) Redis::incr($this->key($user, 'sends')) === 1) {
            Redis::expire($this->key($user, 'sends'), self::EXPIRES_IN_SECONDS);
        }

        return $otp;
    }

    public function verify(User $user, string $otp): bool
    {
        $hash = Redis::get($this->key($user, 'hash'));

        if (! $hash) {
            app(Telemetry::class)->event('auth.otp.rejected', [
                'app.outcome' => 'rejected',
                'app.reason' => 'expired',
            ]);

            return false;
        }

        $attempts = (int) Redis::incr($this->key($user, 'attempts'));
        Redis::expire($this->key($user, 'attempts'), self::EXPIRES_IN_SECONDS);

        if ($attempts > self::MAX_VERIFY_ATTEMPTS) {
            $this->forget($user);
            app(Telemetry::class)->event('auth.otp.rejected', [
                'app.outcome' => 'rejected',
                'app.reason' => 'rate_limited',
            ]);

            return false;
        }

        if (! Hash::check($otp, $hash)) {
            app(Telemetry::class)->event('auth.otp.rejected', [
                'app.outcome' => 'rejected',
                'app.reason' => 'invalid_otp',
            ]);

            return false;
        }

        $this->forget($user);

        return true;
    }

    // 0 when a code may be sent now, otherwise seconds until it may.
    public function retryAfter(User $user): int
    {
        $cooldown = (int) Redis::ttl($this->key($user, 'cooldown'));

        if ($cooldown > 0) {
            return $cooldown;
        }

        if ((int) Redis::get($this->key($user, 'sends')) >= self::MAX_SENDS_PER_WINDOW) {
            return max(1, (int) Redis::ttl($this->key($user, 'sends')));
        }

        return 0;
    }

    private function forget(User $user): void
    {
        Redis::del($this->key($user, 'hash'), $this->key($user, 'attempts'));
    }

    private function key(User $user, string $suffix): string
    {
        return "login_otp:{$user->id}:{$suffix}";
    }
}

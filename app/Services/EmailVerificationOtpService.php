<?php

namespace App\Services;

use App\Models\OtpVerification;
use App\Models\User;
use App\Observability\Telemetry;

class EmailVerificationOtpService
{
    public const EXPIRES_IN_MINUTES = 10;

    public function generate(User $user): string
    {
        $otp = (string) random_int(100000, 999999);

        // Only one active code per user.
        OtpVerification::where('user_id', $user->id)->delete();

        OtpVerification::create([
            'user_id' => $user->id,
            'otp' => $otp,
            'expired_at' => now()->addMinutes(self::EXPIRES_IN_MINUTES),
        ]);

        return $otp;
    }

    public function verify(User $user, string $otp): bool
    {
        $record = OtpVerification::where('user_id', $user->id)
            ->where('otp', $otp)
            ->where('expired_at', '>', now())
            ->latest()
            ->first();

        if (! $record) {
            app(Telemetry::class)->event('auth.otp.rejected', [
                'app.outcome' => 'rejected',
                'app.reason' => 'invalid_otp',
            ]);

            return false;
        }

        $record->delete();

        return true;
    }
}

<?php

namespace App\Services;

use App\Models\DocumentSigner;
use Illuminate\Support\Facades\Hash;

class SigningOtpService
{
    public function generate(DocumentSigner $signer): string
    {
        $otp = (string) random_int(100000, 999999);

        $signer->update([
            'otp_hash' => Hash::make($otp),
            'otp_expires_at' => now()->addMinutes(5),
            'otp_attempts' => 0,
            'otp_verified_at' => null,
            'otp_last_sent_at' => now(),
        ]);

        return $otp;
    }

    public function verify(
        DocumentSigner $signer,
        string $otp
    ): bool {
        if (!$signer->otp_hash) {
            return false;
        }

        if (
            $signer->otp_expires_at &&
            now()->greaterThan($signer->otp_expires_at)
        ) {
            return false;
        }

        if ($signer->otp_attempts >= 5) {
            return false;
        }

        $signer->increment('otp_attempts');

        if (!Hash::check($otp, $signer->otp_hash)) {
            return false;
        }

        $signer->update([
            'otp_verified_at' => now(),
        ]);

        return true;
    }

    public function isVerified(DocumentSigner $signer): bool
    {
        return $signer->otp_verified_at !== null
            && $signer->otp_expires_at !== null
            && now()->lessThanOrEqualTo(
                $signer->otp_expires_at
            );
    }
}

<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RecaptchaService
{
    public function isConfigured(): bool
    {
        return filled(config('services.recaptcha.site_key'))
            && filled(config('services.recaptcha.secret_key'));
    }

    public function verify(?string $token, ?string $ip = null): bool
    {
        if (blank($token)) {
            return false;
        }

        $response = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
            'secret' => config('services.recaptcha.secret_key'),
            'response' => $token,
            'remoteip' => $ip,
        ]);

        if (! $response->successful()) {
            Log::warning('reCAPTCHA siteverify request failed', [
                'status' => $response->status(),
            ]);

            return false;
        }

        $body = $response->json();

        if (($body['success'] ?? false) !== true) {
            Log::info('reCAPTCHA rejected a token', [
                'error-codes' => $body['error-codes'] ?? [],
            ]);
        }

        return ($body['success'] ?? false) === true;
    }
}

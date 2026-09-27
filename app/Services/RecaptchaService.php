<?php

namespace App\Services;

use App\Observability\Telemetry;
use Illuminate\Support\Facades\Http;
use Throwable;

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

        $started = hrtime(true);
        try {
            $response = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
                'secret' => config('services.recaptcha.secret_key'),
                'response' => $token,
                'remoteip' => $ip,
            ]);
        } catch (Throwable $error) {
            app(Telemetry::class)->event('integration.request.failed', [
                'app.operation' => 'verify_recaptcha',
                'app.outcome' => 'failure',
                'app.reason' => 'provider_unavailable',
                'integration.provider' => 'recaptcha',
                'app.duration_ms' => (hrtime(true) - $started) / 1e6,
                'error.type' => $error::class,
            ]);
            throw $error;
        }

        if (! $response->successful()) {
            app(Telemetry::class)->event('integration.request.failed', [
                'app.operation' => 'verify_recaptcha',
                'app.outcome' => 'failure',
                'app.reason' => 'provider_unavailable',
                'integration.provider' => 'recaptcha',
                'app.duration_ms' => (hrtime(true) - $started) / 1e6,
                'error.type' => 'UnexpectedHttpStatus',
            ]);

            return false;
        }

        app(Telemetry::class)->event('integration.request.completed', [
            'app.operation' => 'verify_recaptcha',
            'app.outcome' => 'success',
            'integration.provider' => 'recaptcha',
            'app.duration_ms' => (hrtime(true) - $started) / 1e6,
        ]);

        $body = $response->json();

        return ($body['success'] ?? false) === true;
    }
}

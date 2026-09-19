<?php

namespace App\Rules;

use App\Services\RecaptchaService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class Recaptcha implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (app()->environment() === 'testing') {
            return;
        }

        $service = app(RecaptchaService::class);

        if (! $service->isConfigured()) {
            return;
        }

        if (! $service->verify($value, request()->ip())) {
            $fail('Please confirm you are not a robot.');
        }
    }
}

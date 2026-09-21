<?php

namespace Tests\Unit\Services;

use App\Services\RecaptchaService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RecaptchaServiceTest extends TestCase
{
    public function test_is_configured_true_with_both_keys(): void
    {
        config(['services.recaptcha.site_key' => 'site', 'services.recaptcha.secret_key' => 'secret']);

        $service = new RecaptchaService();

        $this->assertTrue($service->isConfigured());
    }

    public function test_is_configured_false_with_site_key_blank(): void
    {
        config(['services.recaptcha.site_key' => '', 'services.recaptcha.secret_key' => 'secret']);

        $service = new RecaptchaService();

        $this->assertFalse($service->isConfigured());
    }

    public function test_is_configured_false_with_secret_key_blank(): void
    {
        config(['services.recaptcha.site_key' => 'site', 'services.recaptcha.secret_key' => '']);

        $service = new RecaptchaService();

        $this->assertFalse($service->isConfigured());
    }

    public function test_verify_null_token_returns_false(): void
    {
        Http::fake();
        $service = new RecaptchaService();

        $this->assertFalse($service->verify(null));
        Http::assertNothingSent();
    }

    public function test_verify_empty_token_returns_false(): void
    {
        Http::fake();
        $service = new RecaptchaService();

        $this->assertFalse($service->verify(''));
        Http::assertNothingSent();
    }

    public function test_verify_sends_the_token_secret_and_remoteip(): void
    {
        Http::fake(['www.google.com/*' => Http::response(['success' => true], 200)]);
        config(['services.recaptcha.secret_key' => 'my-secret']);
        $service = new RecaptchaService();

        $service->verify('tok123', '192.168.1.1');

        Http::assertSent(function ($request) {
            return $request['secret'] === 'my-secret'
                && $request['response'] === 'tok123'
                && $request['remoteip'] === '192.168.1.1';
        });
    }

    public function test_verify_on_http_500_returns_false(): void
    {
        Http::fake(['www.google.com/*' => Http::response([], 500)]);
        config(['services.recaptcha.secret_key' => 'secret']);
        $service = new RecaptchaService();

        $this->assertFalse($service->verify('token'));
    }

    public function test_verify_with_success_false_returns_false(): void
    {
        Http::fake(['www.google.com/*' => Http::response(['success' => false], 200)]);
        config(['services.recaptcha.secret_key' => 'secret']);
        $service = new RecaptchaService();

        $this->assertFalse($service->verify('token'));
    }

    public function test_verify_with_success_true_returns_true(): void
    {
        Http::fake(['www.google.com/*' => Http::response(['success' => true], 200)]);
        config(['services.recaptcha.secret_key' => 'secret']);
        $service = new RecaptchaService();

        $this->assertTrue($service->verify('token'));
    }
}

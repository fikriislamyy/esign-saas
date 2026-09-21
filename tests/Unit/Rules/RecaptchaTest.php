<?php

namespace Tests\Unit\Rules;

use App\Rules\Recaptcha;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class RecaptchaTest extends TestCase
{
    public function test_in_testing_environment_passes_without_http(): void
    {
        Http::fake();

        $validator = Validator::make(
            ['recaptcha_token' => 'token'],
            ['recaptcha_token' => [new Recaptcha()]]
        );

        $this->assertTrue($validator->passes());
        Http::assertNothingSent();
    }

    public function test_when_not_configured_passes_without_http(): void
    {
        $this->app['env'] = 'production';
        Http::fake();
        config(['services.recaptcha.site_key' => '', 'services.recaptcha.secret_key' => '']);

        $validator = Validator::make(
            ['recaptcha_token' => 'token'],
            ['recaptcha_token' => [new Recaptcha()]]
        );

        $this->assertTrue($validator->passes());
        Http::assertNothingSent();
    }

    public function test_when_google_returns_false_fails(): void
    {
        $this->app['env'] = 'production';
        Http::fake(['www.google.com/*' => Http::response(['success' => false], 200)]);
        config(['services.recaptcha.site_key' => 'site', 'services.recaptcha.secret_key' => 'secret']);

        $validator = Validator::make(
            ['recaptcha_token' => 'token'],
            ['recaptcha_token' => [new Recaptcha()]]
        );

        $this->assertFalse($validator->passes());
        $this->assertStringContainsString('robot', $validator->errors()->get('recaptcha_token')[0]);
    }

    public function test_when_google_returns_true_passes(): void
    {
        $this->app['env'] = 'production';
        Http::fake(['www.google.com/*' => Http::response(['success' => true], 200)]);
        config(['services.recaptcha.site_key' => 'site', 'services.recaptcha.secret_key' => 'secret']);

        $validator = Validator::make(
            ['recaptcha_token' => 'token'],
            ['recaptcha_token' => [new Recaptcha()]]
        );

        $this->assertTrue($validator->passes());
    }
}

<?php

namespace Tests\Unit\Mail;

use App\Mail\LoginOtpMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginOtpMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_subject_is_correct(): void
    {
        $user = User::factory()->create();
        $mail = new LoginOtpMail($user, '123456');

        $mail->assertHasSubject('Your EZSign sign-in code');
    }

    public function test_html_contains_the_otp(): void
    {
        $user = User::factory()->create();
        $mail = new LoginOtpMail($user, '654321');

        $mail->assertSeeInHtml('654321');
    }

    public function test_html_contains_the_users_name(): void
    {
        $user = User::factory()->create(['name' => 'Sam Signer']);
        $mail = new LoginOtpMail($user, '123456');

        $mail->assertSeeInHtml('Sam Signer');
    }

    public function test_html_contains_change_your_password(): void
    {
        $user = User::factory()->create();
        $mail = new LoginOtpMail($user, '123456');

        $mail->assertSeeInHtml('change your password');
    }
}

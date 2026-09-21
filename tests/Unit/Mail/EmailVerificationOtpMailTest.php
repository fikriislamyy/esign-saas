<?php

namespace Tests\Unit\Mail;

use App\Mail\EmailVerificationOtpMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailVerificationOtpMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_subject_is_correct(): void
    {
        $user = User::factory()->create();
        $mail = new EmailVerificationOtpMail($user, '123456');

        $mail->assertHasSubject('Your EZSign verification code');
    }

    public function test_html_contains_the_otp(): void
    {
        $user = User::factory()->create();
        $mail = new EmailVerificationOtpMail($user, '654321');

        $mail->assertSeeInHtml('654321');
    }

    public function test_html_contains_the_users_name(): void
    {
        $user = User::factory()->create(['name' => 'Alex User']);
        $mail = new EmailVerificationOtpMail($user, '123456');

        $mail->assertSeeInHtml('Alex User');
    }

    public function test_html_contains_safe_to_ignore_message(): void
    {
        $user = User::factory()->create();
        $mail = new EmailVerificationOtpMail($user, '123456');

        $mail->assertSeeInHtml('safely ignore this email');
    }
}

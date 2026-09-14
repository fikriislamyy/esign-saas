<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class EmailVerificationOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $otp
    ) {}

    public function build()
    {
        return $this
            ->subject('Your EZSign verification code')
            ->view('emails.email-verification-otp')
            ->with([
                'user' => $this->user,
                'otp' => $this->otp,
            ]);
    }
}

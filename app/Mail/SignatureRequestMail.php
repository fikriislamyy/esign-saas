<?php

namespace App\Mail;

use App\Models\DocumentSigner;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SignatureRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public DocumentSigner $signer,
        public string $otp
    ) {}

    public function build()
    {
        return $this
            ->subject(
                'Signature Request: ' .
                $this->signer->document->name
            )
            ->view('emails.signature-request')
            ->with([
                'signer' => $this->signer,
                'otp' => $this->otp,
            ]);
    }
}

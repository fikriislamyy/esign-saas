<?php

namespace App\Mail;

use App\Models\Invitation;
use Illuminate\Mail\Mailable;

class OrganizationInvitationMail extends Mailable
{
    public function __construct(
        public Invitation $invitation
    ) {
    }

    public function build()
    {
        return $this
            ->subject(
                'Organization Invitation'
            )
            ->view(
                'emails.organization-invitation'
            );
    }
}

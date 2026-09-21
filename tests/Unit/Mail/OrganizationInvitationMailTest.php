<?php

namespace Tests\Unit\Mail;

use App\Mail\OrganizationInvitationMail;
use App\Models\Invitation;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationInvitationMailTest extends TestCase
{
    use RefreshDatabase;

    private function invitation(): Invitation
    {
        $organization = Organization::create(['name' => 'Acme']);

        return Invitation::create(['organization_id' => $organization->id, 'email' => 'member@example.test', 'role' => 'member', 'token' => 'invite-token']);
    }

    public function test_the_subject_is_an_organization_invitation(): void
    {
        $mail = new OrganizationInvitationMail($this->invitation());

        $mail->assertHasSubject('Organization Invitation');
    }

    public function test_the_html_contains_the_organization_and_acceptance_link(): void
    {
        $mail = new OrganizationInvitationMail($this->invitation());

        $mail->assertSeeInHtml('Acme');
        $mail->assertSeeInHtml('invite-token');
    }
}

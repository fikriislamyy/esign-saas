<?php

namespace Tests\Unit\Mail;

use App\Mail\SignatureRequestMail;
use App\Models\Document;
use App\Models\DocumentSigner;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SignatureRequestMailTest extends TestCase
{
    use RefreshDatabase;

    private function signer(): DocumentSigner
    {
        $organization = Organization::create(['name' => 'Acme']);
        $owner = User::factory()->create(['organization_id' => $organization->id]);
        $document = Document::create(['organization_id' => $organization->id, 'owner_id' => $owner->id, 'name' => 'Contract', 'file_path' => 'documents/contract.pdf', 'file_size' => 100, 'mime_type' => 'application/pdf']);

        return DocumentSigner::create(['document_id' => $document->id, 'name' => 'Sam', 'email' => 'sam@example.test', 'token' => (string) Str::uuid()]);
    }

    public function test_the_subject_contains_the_document_name(): void
    {
        $signer = $this->signer();
        $mail = new SignatureRequestMail($signer, '123456');

        $mail->assertHasSubject('Signature Request: Contract');
    }

    public function test_the_html_contains_the_otp_and_signing_link(): void
    {
        $signer = $this->signer();
        $mail = new SignatureRequestMail($signer, '123456');

        $mail->assertSeeInHtml('123456');
        $mail->assertSeeInHtml($signer->token);
    }
}

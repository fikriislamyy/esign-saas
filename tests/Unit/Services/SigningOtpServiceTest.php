<?php

namespace Tests\Unit\Services;

use App\Models\Document;
use App\Models\DocumentSigner;
use App\Models\Organization;
use App\Models\User;
use App\Services\SigningOtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class SigningOtpServiceTest extends TestCase
{
    use RefreshDatabase;

    private DocumentSigner $signer;
    private SigningOtpService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $organization = Organization::create(['name' => 'Acme']);
        $owner = User::factory()->create([
            'organization_id' => $organization->id,
            'role' => 'owner',
        ]);
        $document = Document::create([
            'organization_id' => $organization->id,
            'owner_id' => $owner->id,
            'name' => 'Contract',
            'file_path' => 'contract.pdf',
            'file_size' => 1024,
            'mime_type' => 'application/pdf',
        ]);
        $this->signer = DocumentSigner::create([
            'document_id' => $document->id,
            'name' => 'Sam Signer',
            'email' => 'sam@example.com',
            'token' => (string) Str::uuid(),
        ]);
        $this->service = app(SigningOtpService::class);
    }

    public function test_generate_returns_six_digits(): void
    {
        $otp = $this->service->generate($this->signer);

        $this->assertIsNumeric($otp);
        $this->assertSame(6, strlen($otp));
        $this->assertGreaterThanOrEqual(100000, (int) $otp);
        $this->assertLessThanOrEqual(999999, (int) $otp);
    }

    public function test_generate_stores_bcrypt_hash(): void
    {
        $otp = $this->service->generate($this->signer);
        $this->signer->refresh();

        $this->assertStringStartsWith('$2y$', $this->signer->otp_hash);
        $this->assertTrue(Hash::check($otp, $this->signer->otp_hash));
    }

    public function test_generate_sets_expires_at_5_minutes(): void
    {
        $before = now();
        $this->service->generate($this->signer);
        $this->signer->refresh();

        $diffInMinutes = $this->signer->otp_expires_at->diffInMinutes($before);
        $this->assertGreaterThanOrEqual(4, $diffInMinutes);
        $this->assertLessThanOrEqual(5, $diffInMinutes);
    }

    public function test_generate_sets_attempts_zero(): void
    {
        $this->service->generate($this->signer);
        $this->signer->refresh();

        $this->assertSame(0, $this->signer->otp_attempts);
    }

    public function test_generate_clears_verified_at(): void
    {
        $this->signer->update(['otp_verified_at' => now()]);
        $this->service->generate($this->signer);
        $this->signer->refresh();

        $this->assertNull($this->signer->otp_verified_at);
    }

    public function test_generate_sets_last_sent_at(): void
    {
        $before = now();
        $this->service->generate($this->signer);
        $this->signer->refresh();

        $this->assertTrue($this->signer->otp_last_sent_at->isAfter($before->subSecond()));
    }

    public function test_verify_no_hash_returns_false(): void
    {
        $this->assertFalse($this->service->verify($this->signer, '123456'));
    }

    public function test_verify_correct_otp_returns_true(): void
    {
        $otp = $this->service->generate($this->signer);

        $this->assertTrue($this->service->verify($this->signer, $otp));
    }

    public function test_verify_correct_otp_sets_verified_at(): void
    {
        $otp = $this->service->generate($this->signer);
        $before = now();
        $this->service->verify($this->signer, $otp);
        $this->signer->refresh();

        $this->assertTrue($this->signer->otp_verified_at->isAfter($before->subSecond()));
    }

    public function test_verify_wrong_otp_returns_false(): void
    {
        $this->service->generate($this->signer);

        $this->assertFalse($this->service->verify($this->signer, '000000'));
    }

    public function test_verify_wrong_otp_increments_attempts(): void
    {
        $this->service->generate($this->signer);
        $this->service->verify($this->signer, '000000');
        $this->signer->refresh();

        $this->assertSame(1, $this->signer->otp_attempts);
    }

    public function test_verify_expired_otp_returns_false(): void
    {
        $otp = $this->service->generate($this->signer);
        $this->signer->update(['otp_expires_at' => now()->subMinute()]);

        $this->assertFalse($this->service->verify($this->signer, $otp));
    }

    public function test_verify_five_wrong_then_correct_returns_false(): void
    {
        $otp = $this->service->generate($this->signer);

        for ($i = 0; $i < 5; $i++) {
            $this->service->verify($this->signer, '000000');
        }

        $this->assertFalse($this->service->verify($this->signer, $otp));
    }

    public function test_is_verified_false_before_verify(): void
    {
        $this->service->generate($this->signer);

        $this->assertFalse($this->service->isVerified($this->signer));
    }

    public function test_is_verified_true_after_verify(): void
    {
        $otp = $this->service->generate($this->signer);
        $this->service->verify($this->signer, $otp);
        $this->signer->refresh();

        $this->assertTrue($this->service->isVerified($this->signer));
    }

    public function test_is_verified_false_after_expiry(): void
    {
        $otp = $this->service->generate($this->signer);
        $this->service->verify($this->signer, $otp);
        $this->signer->refresh();
        $this->travel(6)->minutes();

        $this->assertFalse($this->service->isVerified($this->signer));
    }

    public function test_is_verified_false_without_verified_at(): void
    {
        $this->service->generate($this->signer);
        $this->signer->refresh();

        $this->assertFalse($this->service->isVerified($this->signer));
    }

    public function test_is_verified_false_without_expires_at(): void
    {
        $this->signer->update(['otp_verified_at' => now(), 'otp_expires_at' => null]);

        $this->assertFalse($this->service->isVerified($this->signer));
    }
}

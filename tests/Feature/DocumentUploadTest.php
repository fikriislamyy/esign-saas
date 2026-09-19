<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentUploadTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('documents');

        $organization = Organization::create(['name' => 'Acme']);

        $this->user = User::factory()->create([
            'organization_id' => $organization->id,
            'role' => 'owner',
        ]);
    }

    private function pdf(string $name, int $bytes): UploadedFile
    {
        $header = "%PDF-1.4\n";

        return UploadedFile::fake()->createWithContent(
            $name,
            $header.str_repeat('a', $bytes - strlen($header))
        );
    }

    public function test_a_pdf_of_exactly_5mb_is_accepted(): void
    {
        $response = $this->actingAs($this->user)->post('/documents', [
            'file' => $this->pdf('contract.pdf', 5 * 1024 * 1024),
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('documents', ['name' => 'contract']);
    }

    public function test_a_pdf_one_byte_over_5mb_is_rejected(): void
    {
        $response = $this->actingAs($this->user)->post('/documents', [
            'file' => $this->pdf('contract.pdf', 5 * 1024 * 1024 + 1),
        ]);

        $response->assertSessionHasErrors(['file' => 'The file must be 5 MB or smaller.']);
        $this->assertDatabaseCount('documents', 0);
    }

    public function test_a_docx_is_rejected(): void
    {
        $response = $this->actingAs($this->user)->post('/documents', [
            'file' => UploadedFile::fake()->create('contract.docx', 100),
        ]);

        $response->assertSessionHasErrors(['file' => 'Only PDF files can be uploaded.']);
        $this->assertDatabaseCount('documents', 0);
    }

    public function test_a_non_pdf_renamed_to_pdf_is_rejected(): void
    {
        $file = UploadedFile::fake()->createWithContent('contract.pdf', str_repeat("\0", 1024));

        $response = $this->actingAs($this->user)->post('/documents', ['file' => $file]);

        $response->assertSessionHasErrors(['file' => 'Only PDF files can be uploaded.']);
        $this->assertDatabaseCount('documents', 0);
    }

    public function test_a_real_pdf_with_the_wrong_extension_is_rejected(): void
    {
        $response = $this->actingAs($this->user)->post('/documents', [
            'file' => $this->pdf('contract.txt', 1024),
        ]);

        $response->assertSessionHasErrors(['file' => 'Only PDF files can be uploaded.']);
        $this->assertDatabaseCount('documents', 0);
    }
}

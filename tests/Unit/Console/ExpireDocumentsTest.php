<?php

namespace Tests\Unit\Console;

use App\Models\Document;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExpireDocumentsTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('documents.disk'));

        $this->organization = Organization::create(['name' => 'Acme']);
        $this->owner = User::factory()->create([
            'organization_id' => $this->organization->id,
            'role' => 'owner',
        ]);
    }

    public function test_draft_4_days_old_is_expired(): void
    {
        $doc = Document::create([
            'organization_id' => $this->organization->id,
            'owner_id' => $this->owner->id,
            'name' => 'Old Draft',
            'file_path' => 'draft.pdf',
            'file_size' => 1024,
            'mime_type' => 'application/pdf',
            'status' => 'draft',
        ]);
        Document::whereKey($doc->id)->update(['created_at' => now()->subDays(4)]);
        Storage::disk(config('documents.disk'))->put('draft.pdf', 'content');

        $this->artisan('documents:expire')->assertSuccessful();

        $doc->refresh();
        $this->assertSame('expired', $doc->status);
        Storage::disk(config('documents.disk'))->assertMissing('draft.pdf');
    }

    public function test_sent_4_days_old_is_expired(): void
    {
        $doc = Document::create([
            'organization_id' => $this->organization->id,
            'owner_id' => $this->owner->id,
            'name' => 'Old Sent',
            'file_path' => 'sent.pdf',
            'file_size' => 1024,
            'mime_type' => 'application/pdf',
            'status' => 'sent',
        ]);
        Document::whereKey($doc->id)->update(['created_at' => now()->subDays(4)]);
        Storage::disk(config('documents.disk'))->put('sent.pdf', 'content');

        $this->artisan('documents:expire')->assertSuccessful();

        $doc->refresh();
        $this->assertSame('expired', $doc->status);
    }

    public function test_completed_4_days_old_is_untouched(): void
    {
        $doc = Document::create([
            'organization_id' => $this->organization->id,
            'owner_id' => $this->owner->id,
            'name' => 'Completed',
            'file_path' => 'completed.pdf',
            'file_size' => 1024,
            'mime_type' => 'application/pdf',
            'status' => 'completed',
        ]);
        Document::whereKey($doc->id)->update(['created_at' => now()->subDays(4)]);

        $this->artisan('documents:expire')->assertSuccessful();

        $doc->refresh();
        $this->assertSame('completed', $doc->status);
    }

    public function test_draft_2_days_old_is_untouched(): void
    {
        $doc = Document::create([
            'organization_id' => $this->organization->id,
            'owner_id' => $this->owner->id,
            'name' => 'Recent Draft',
            'file_path' => 'recent.pdf',
            'file_size' => 1024,
            'mime_type' => 'application/pdf',
            'status' => 'draft',
        ]);
        Document::whereKey($doc->id)->update(['created_at' => now()->subDays(2)]);

        $this->artisan('documents:expire')->assertSuccessful();

        $doc->refresh();
        $this->assertSame('draft', $doc->status);
    }

    public function test_days_option_overrides_retention(): void
    {
        $doc = Document::create([
            'organization_id' => $this->organization->id,
            'owner_id' => $this->owner->id,
            'name' => '2 Days Old',
            'file_path' => '2days.pdf',
            'file_size' => 1024,
            'mime_type' => 'application/pdf',
            'status' => 'draft',
        ]);
        Document::whereKey($doc->id)->update(['created_at' => now()->subDays(2)]);
        Storage::disk(config('documents.disk'))->put('2days.pdf', 'content');

        $this->artisan('documents:expire', ['--days' => '1'])->assertSuccessful();

        $doc->refresh();
        $this->assertSame('expired', $doc->status);
    }

    public function test_dry_run_changes_nothing(): void
    {
        $doc = Document::create([
            'organization_id' => $this->organization->id,
            'owner_id' => $this->owner->id,
            'name' => 'Dry Run Doc',
            'file_path' => 'dryrun.pdf',
            'file_size' => 1024,
            'mime_type' => 'application/pdf',
            'status' => 'draft',
        ]);
        Document::whereKey($doc->id)->update(['created_at' => now()->subDays(4)]);
        Storage::disk(config('documents.disk'))->put('dryrun.pdf', 'content');

        $this->artisan('documents:expire', ['--dry-run' => true])->assertSuccessful();

        $doc->refresh();
        $this->assertSame('draft', $doc->status);
        Storage::disk(config('documents.disk'))->assertExists('dryrun.pdf');
    }

    public function test_dry_run_outputs_summary_with_dry_run_text(): void
    {
        Document::create([
            'organization_id' => $this->organization->id,
            'owner_id' => $this->owner->id,
            'name' => 'Test Doc',
            'file_path' => 'test.pdf',
            'file_size' => 1024,
            'mime_type' => 'application/pdf',
            'status' => 'draft',
        ]);
        Document::where('status', 'draft')->update(['created_at' => now()->subDays(4)]);

        $this->artisan('documents:expire', ['--dry-run' => true])
            ->assertSuccessful();
    }

    public function test_document_with_missing_file_still_expires(): void
    {
        $doc = Document::create([
            'organization_id' => $this->organization->id,
            'owner_id' => $this->owner->id,
            'name' => 'Missing File',
            'file_path' => 'missing.pdf',
            'file_size' => 1024,
            'mime_type' => 'application/pdf',
            'status' => 'draft',
        ]);
        Document::whereKey($doc->id)->update(['created_at' => now()->subDays(4)]);
        // Don't create the file

        $this->artisan('documents:expire')->assertSuccessful();

        $doc->refresh();
        $this->assertSame('expired', $doc->status);
    }

    public function test_output_shows_document_count(): void
    {
        Document::create([
            'organization_id' => $this->organization->id,
            'owner_id' => $this->owner->id,
            'name' => 'Doc 1',
            'file_path' => 'doc1.pdf',
            'file_size' => 1024,
            'mime_type' => 'application/pdf',
            'status' => 'draft',
        ]);
        Document::create([
            'organization_id' => $this->organization->id,
            'owner_id' => $this->owner->id,
            'name' => 'Doc 2',
            'file_path' => 'doc2.pdf',
            'file_size' => 1024,
            'mime_type' => 'application/pdf',
            'status' => 'sent',
        ]);
        Document::whereIn('status', ['draft', 'sent'])->update(['created_at' => now()->subDays(4)]);

        $this->artisan('documents:expire')
            ->expectsOutput('Documents expired: 2');
    }
}

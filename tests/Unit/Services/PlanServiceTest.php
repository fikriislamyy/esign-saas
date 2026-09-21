<?php

namespace Tests\Unit\Services;

use App\Models\Document;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\Template;
use App\Models\User;
use App\Services\PlanService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanServiceTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;
    private User $owner;
    private PlanService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::create(['name' => 'Acme']);
        $this->owner = User::factory()->create([
            'organization_id' => $this->organization->id,
            'role' => 'owner',
        ]);
        $this->service = app(PlanService::class);
    }

    public function test_subscription_for_creates_free_subscription(): void
    {
        $subscription = $this->service->subscriptionFor($this->organization);

        $this->assertSame('free', $subscription->plan);
        $this->assertSame('active', $subscription->status);
        $this->assertNull($subscription->expired_at);
        $this->assertDatabaseCount('subscriptions', 1);
    }

    public function test_subscription_for_returns_existing_subscription(): void
    {
        $sub1 = $this->service->subscriptionFor($this->organization);
        $sub2 = $this->service->subscriptionFor($this->organization);

        $this->assertSame($sub1->id, $sub2->id);
        $this->assertDatabaseCount('subscriptions', 1);
    }

    public function test_limits_free_returns_free_plan_limits(): void
    {
        $limits = $this->service->limits($this->organization);

        $this->assertSame(3, $limits['documents']['limit']);
        $this->assertSame('week', $limits['documents']['period']);
    }

    public function test_limits_pro_returns_pro_plan_limits(): void
    {
        $this->organization->subscription()->create([
            'plan' => 'pro',
            'status' => 'active',
            'expired_at' => now()->addDays(30),
        ]);

        $limits = $this->service->limits($this->organization);

        $this->assertSame(100, $limits['documents']['limit']);
        $this->assertSame('month', $limits['documents']['period']);
    }

    public function test_document_period_start_free_is_start_of_week(): void
    {
        $this->travelTo(Carbon::parse('2026-03-11 10:00'));

        $periodStart = $this->service->documentPeriodStart($this->organization);

        $this->assertTrue($periodStart->isMonday());
        $this->assertTrue($periodStart->is('2026-03-09'));
    }

    public function test_document_period_start_pro_is_start_of_month(): void
    {
        $this->organization->subscription()->create([
            'plan' => 'pro',
            'status' => 'active',
            'expired_at' => now()->addDays(30),
        ]);
        $this->travelTo(Carbon::parse('2026-03-11 10:00'));

        $periodStart = $this->service->documentPeriodStart($this->organization);

        $this->assertTrue($periodStart->day === 1);
        $this->assertTrue($periodStart->is('2026-03-01'));
    }

    public function test_documents_used_counts_docs_in_period(): void
    {
        $this->travelTo(Carbon::parse('2026-03-11 10:00'));
        $doc1 = Document::create([
            'organization_id' => $this->organization->id,
            'owner_id' => $this->owner->id,
            'name' => 'Monday doc',
            'file_path' => 'doc1.pdf',
            'file_size' => 1024,
            'mime_type' => 'application/pdf',
        ]);
        Document::where('name', 'Monday doc')->update(
            ['created_at' => Carbon::parse('2026-03-09 10:00')]
        );

        $doc2 = Document::create([
            'organization_id' => $this->organization->id,
            'owner_id' => $this->owner->id,
            'name' => 'Sunday doc (old)',
            'file_path' => 'doc2.pdf',
            'file_size' => 1024,
            'mime_type' => 'application/pdf',
        ]);
        Document::where('name', 'Sunday doc (old)')->update(
            ['created_at' => Carbon::parse('2026-03-08 10:00')]
        );

        $used = $this->service->documentsUsed($this->organization);

        $this->assertSame(1, $used);
    }

    public function test_storage_used_bytes_sums_file_sizes(): void
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
            'file_size' => 2048,
            'mime_type' => 'application/pdf',
            'status' => 'sent',
        ]);

        $bytes = $this->service->storageUsedBytes($this->organization);

        $this->assertSame(3072, $bytes);
    }

    public function test_storage_used_bytes_ignores_expired_docs(): void
    {
        Document::create([
            'organization_id' => $this->organization->id,
            'owner_id' => $this->owner->id,
            'name' => 'Expired Doc',
            'file_path' => 'expired.pdf',
            'file_size' => 5000,
            'mime_type' => 'application/pdf',
            'status' => 'expired',
        ]);
        Document::create([
            'organization_id' => $this->organization->id,
            'owner_id' => $this->owner->id,
            'name' => 'Active Doc',
            'file_path' => 'active.pdf',
            'file_size' => 2000,
            'mime_type' => 'application/pdf',
            'status' => 'completed',
        ]);

        $bytes = $this->service->storageUsedBytes($this->organization);

        $this->assertSame(2000, $bytes);
    }

    public function test_members_used_counts_accepted_users(): void
    {
        User::factory()->create([
            'organization_id' => $this->organization->id,
            'role' => 'member',
        ]);

        $used = $this->service->membersUsed($this->organization);

        $this->assertSame(2, $used); // owner + new member
    }

    public function test_members_used_includes_pending_invitations(): void
    {
        Invitation::create([
            'organization_id' => $this->organization->id,
            'email' => 'pending@example.com',
            'role' => 'member',
            'token' => (string) \Illuminate\Support\Str::uuid(),
            'accepted_at' => null,
        ]);

        $used = $this->service->membersUsed($this->organization);

        $this->assertSame(2, $used); // owner + pending invitation
    }

    public function test_members_used_ignores_accepted_invitations(): void
    {
        Invitation::create([
            'organization_id' => $this->organization->id,
            'email' => 'accepted@example.com',
            'role' => 'member',
            'token' => (string) \Illuminate\Support\Str::uuid(),
            'accepted_at' => now(),
        ]);

        $used = $this->service->membersUsed($this->organization);

        $this->assertSame(1, $used); // owner only
    }

    public function test_can_upload_document_free_plan_with_two_docs_true(): void
    {
        $this->travelTo(Carbon::parse('2026-03-11 10:00'));
        Document::create([
            'organization_id' => $this->organization->id,
            'owner_id' => $this->owner->id,
            'name' => 'Doc 1',
            'file_path' => 'doc1.pdf',
            'file_size' => 1024,
            'mime_type' => 'application/pdf',
            'created_at' => now(),
        ]);
        Document::create([
            'organization_id' => $this->organization->id,
            'owner_id' => $this->owner->id,
            'name' => 'Doc 2',
            'file_path' => 'doc2.pdf',
            'file_size' => 1024,
            'mime_type' => 'application/pdf',
            'created_at' => now(),
        ]);

        $this->assertTrue($this->service->canUploadDocument($this->organization));
    }

    public function test_can_upload_document_free_plan_with_three_docs_false(): void
    {
        $this->travelTo(Carbon::parse('2026-03-11 10:00'));
        for ($i = 0; $i < 3; $i++) {
            Document::create([
                'organization_id' => $this->organization->id,
                'owner_id' => $this->owner->id,
                'name' => "Doc $i",
                'file_path' => "doc$i.pdf",
                'file_size' => 1024,
                'mime_type' => 'application/pdf',
                'created_at' => now(),
            ]);
        }

        $this->assertFalse($this->service->canUploadDocument($this->organization));
    }

    public function test_can_upload_document_enterprise_with_50_docs_true(): void
    {
        $this->organization->subscription()->create([
            'plan' => 'enterprise',
            'status' => 'active',
        ]);
        $this->travelTo(Carbon::parse('2026-03-11 10:00'));
        for ($i = 0; $i < 50; $i++) {
            Document::create([
                'organization_id' => $this->organization->id,
                'owner_id' => $this->owner->id,
                'name' => "Doc $i",
                'file_path' => "doc$i.pdf",
                'file_size' => 1024,
                'mime_type' => 'application/pdf',
                'created_at' => now(),
            ]);
        }

        $this->assertTrue($this->service->canUploadDocument($this->organization));
    }

    public function test_can_add_member_at_limit_false(): void
    {
        User::factory()->create(['organization_id' => $this->organization->id]);
        User::factory()->create(['organization_id' => $this->organization->id]);

        $this->assertFalse($this->service->canAddMember($this->organization));
    }

    public function test_can_add_template_at_limit_false(): void
    {
        Template::create([
            'organization_id' => $this->organization->id,
            'name' => 'Template 1',
            'file_path' => 'template1.pdf',
            'file_size' => 1024,
            'mime_type' => 'application/pdf',
        ]);
        Template::create([
            'organization_id' => $this->organization->id,
            'name' => 'Template 2',
            'file_path' => 'template2.pdf',
            'file_size' => 1024,
            'mime_type' => 'application/pdf',
        ]);
        Template::create([
            'organization_id' => $this->organization->id,
            'name' => 'Template 3',
            'file_path' => 'template3.pdf',
            'file_size' => 1024,
            'mime_type' => 'application/pdf',
        ]);
        Template::create([
            'organization_id' => $this->organization->id,
            'name' => 'Template 4',
            'file_path' => 'template4.pdf',
            'file_size' => 1024,
            'mime_type' => 'application/pdf',
        ]);
        Template::create([
            'organization_id' => $this->organization->id,
            'name' => 'Template 5',
            'file_path' => 'template5.pdf',
            'file_size' => 1024,
            'mime_type' => 'application/pdf',
        ]);

        $this->assertFalse($this->service->canAddTemplate($this->organization));
    }

    public function test_can_store_exactly_to_the_limit_true(): void
    {
        $limit = config('plans.free.limits.storage_bytes');
        Document::create([
            'organization_id' => $this->organization->id,
            'owner_id' => $this->owner->id,
            'name' => 'Doc',
            'file_path' => 'doc.pdf',
            'file_size' => $limit,
            'mime_type' => 'application/pdf',
            'status' => 'draft',
        ]);

        $this->assertTrue($this->service->canStore($this->organization, 0));
    }

    public function test_can_store_one_byte_over_false(): void
    {
        $limit = config('plans.free.limits.storage_bytes');
        Document::create([
            'organization_id' => $this->organization->id,
            'owner_id' => $this->owner->id,
            'name' => 'Doc',
            'file_path' => 'doc.pdf',
            'file_size' => $limit - 1,
            'mime_type' => 'application/pdf',
            'status' => 'draft',
        ]);

        $this->assertFalse($this->service->canStore($this->organization, 2));
    }

    public function test_usage_returns_all_quotas(): void
    {
        $this->travelTo(Carbon::parse('2026-03-11 10:00'));
        Document::create([
            'organization_id' => $this->organization->id,
            'owner_id' => $this->owner->id,
            'name' => 'Doc',
            'file_path' => 'doc.pdf',
            'file_size' => 1000,
            'mime_type' => 'application/pdf',
            'created_at' => now(),
        ]);
        Template::create([
            'organization_id' => $this->organization->id,
            'name' => 'Template',
            'file_path' => 'template.pdf',
            'file_size' => 500,
            'mime_type' => 'application/pdf',
        ]);

        $usage = $this->service->usage($this->organization);

        $this->assertSame(1, $usage['documents']['used']);
        $this->assertSame(3, $usage['documents']['limit']);
        $this->assertSame('week', $usage['documents']['period']);
        $this->assertSame(1, $usage['templates']['used']);
        $this->assertSame(5, $usage['templates']['limit']);
        $this->assertSame(1, $usage['members']['used']); // owner
        $this->assertSame(3, $usage['members']['limit']);
        $this->assertSame(1000, $usage['storage']['used']); // only document storage counts
        $this->assertSame(100 * 1024 * 1024, $usage['storage']['limit']);
    }
}

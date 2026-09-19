<?php

namespace App\Services;

use App\Models\Document;
use App\Models\Organization;
use App\Models\Subscription;
use App\Models\Template;
use Carbon\Carbon;

class PlanService
{
    public function subscriptionFor(Organization $organization): Subscription
    {
        return $organization->subscription()->firstOrCreate(
            ['organization_id' => $organization->id],
            ['plan' => 'free', 'status' => 'active', 'expired_at' => null],
        );
    }

    public function limits(Organization $organization): array
    {
        return $this->subscriptionFor($organization)->config()['limits'];
    }

    /**
     * Start of the current quota window. The period comes from the plan,
     * never from a hardcoded month — see trap 3.
     */
    public function documentPeriodStart(Organization $organization): Carbon
    {
        $period = $this->limits($organization)['documents']['period'];

        return $period === 'week'
            ? Carbon::now()->startOfWeek()
            : Carbon::now()->startOfMonth();
    }

    public function documentsUsed(Organization $organization): int
    {
        return Document::query()
            ->where('organization_id', $organization->id)
            ->where('created_at', '>=', $this->documentPeriodStart($organization))
            ->count();
    }

    public function storageUsedBytes(Organization $organization): int
    {
        return (int) Document::query()
            ->where('organization_id', $organization->id)
            // Expired documents have no files on S3, so they occupy no quota.
            ->where('status', '!=', 'expired')
            ->sum('file_size');
    }

    /**
     * Accepted members plus invitations still outstanding — see trap 4.
     */
    public function membersUsed(Organization $organization): int
    {
        return $organization->users()->count()
            + $organization->invitations()->whereNull('accepted_at')->count();
    }

    public function canUploadDocument(Organization $organization): bool
    {
        $limit = $this->limits($organization)['documents']['limit'];

        // null means unlimited (enterprise) — not zero. Decision 3.7.
        return $limit === null
            || $this->documentsUsed($organization) < $limit;
    }

    public function canAddMember(Organization $organization): bool
    {
        $limit = $this->limits($organization)['members'];

        return $limit === null
            || $this->membersUsed($organization) < $limit;
    }

    public function templatesUsed(Organization $organization): int
    {
        return Template::query()
            ->where('organization_id', $organization->id)
            ->count();
    }

    public function canAddTemplate(Organization $organization): bool
    {
        $limit = $this->limits($organization)['templates'];

        return $limit === null
            || $this->templatesUsed($organization) < $limit;
    }

    public function canStore(Organization $organization, int $bytes): bool
    {
        $limit = $this->limits($organization)['storage_bytes'];

        return $limit === null
            || ($this->storageUsedBytes($organization) + $bytes) <= $limit;
    }

    /** Shape consumed by the Plan page and the shared Inertia props. */
    public function usage(Organization $organization): array
    {
        $limits = $this->limits($organization);

        return [
            'documents' => [
                'used' => $this->documentsUsed($organization),
                'limit' => $limits['documents']['limit'],
                'period' => $limits['documents']['period'],
            ],
            'templates' => [
                'used' => $this->templatesUsed($organization),
                'limit' => $limits['templates'],
            ],
            'members' => [
                'used' => $this->membersUsed($organization),
                'limit' => $limits['members'],
            ],
            'storage' => [
                'used' => $this->storageUsedBytes($organization),
                'limit' => $limits['storage_bytes'],
            ],
        ];
    }
}

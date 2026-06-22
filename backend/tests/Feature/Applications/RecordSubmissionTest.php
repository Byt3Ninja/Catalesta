<?php

declare(strict_types=1);

namespace Tests\Feature\Applications;

use App\Modules\Applications\Application\Exceptions\SubmissionTooLargeException;
use App\Modules\Applications\Application\Exceptions\UnknownBlobReferenceException;
use App\Modules\Applications\Application\RecordSubmission;
use App\Modules\Applications\Domain\Models\ApplicationSubmission;
use App\Modules\Cohorts\Domain\Models\Cohort;
use App\Modules\Cohorts\Domain\Models\CohortStatus;
use App\Shared\Storage\ContentAddressedStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

final class RecordSubmissionTest extends TestCase
{
    use RefreshDatabase;

    private function service(): RecordSubmission
    {
        return $this->app->make(RecordSubmission::class);
    }

    private function makeCohort(): Cohort
    {
        // program_id has no FK constraint — any ulid is fine for binding.
        return Cohort::create([
            'program_id' => (string) Str::ulid(),
            'name' => 'Cohort One',
            'status' => CohortStatus::Open,
        ]);
    }

    /** Boot a tenant context + an open cohort, return the cohort. */
    private function bootTenantWithCohort(): Cohort
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);

        return $this->makeCohort();
    }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('s3');
        config(['blob.disk' => 's3', 'blob.max_bytes' => 1024, 'blob.path_prefix' => 'blobs']);
    }

    public function test_records_a_submission_with_an_immutable_snapshot(): void // AC-1/2
    {
        $cohort = $this->bootTenantWithCohort();
        $blob = $this->app->make(ContentAddressedStore::class)->store('cv.pdf bytes');

        $submission = $this->service()->handle(
            $cohort,
            ['name' => 'Omar', 'idea' => 'Solar Nile'],
            [$blob->digest],
            ['form' => 'form-v1', 'program' => 'prog-v1', 'rubric' => 'rubric-v1'],
        );

        $this->assertDatabaseCount('application_submissions', 1);
        $this->assertSame($cohort->id, $submission->cohort_id);
        $this->assertNotEmpty($submission->organization_id, 'organization_id is server-set');

        $snap = $submission->fresh()->submission_snapshot;
        // assertEquals (order-insensitive): content-addressed snapshot sorts keys
        // alphabetically for stable hashing — see PublicSubmitTest for the same fix.
        $this->assertEquals(['name' => 'Omar', 'idea' => 'Solar Nile'], $snap['answers']);
        $this->assertSame([$blob->digest], $snap['blob_refs']);
        $this->assertSame('form-v1', $snap['form_version_id']);
        $this->assertSame('prog-v1', $snap['program_version_id']);
        $this->assertSame('rubric-v1', $snap['rubric_version_id']);
    }

    public function test_submission_is_write_once(): void // AC-3
    {
        $cohort = $this->bootTenantWithCohort();
        $submission = $this->service()->handle($cohort, ['a' => 1], [], ['form' => 'f']);

        $this->expectException(RuntimeException::class);
        try {
            $submission->update(['submission_snapshot' => ['answers' => ['a' => 999]]]);
        } finally {
            $this->assertSame(1, $submission->fresh()->submission_snapshot['answers']['a'], 'snapshot unchanged');
        }
    }

    public function test_referenced_blob_survives_garbage_collection(): void // ★ AC-5
    {
        $cohort = $this->bootTenantWithCohort();
        $store = $this->app->make(ContentAddressedStore::class);
        $blob = $store->store('referenced bytes');

        $this->service()->handle($cohort, ['a' => 1], [$blob->digest], ['form' => 'f']);

        $this->artisan('blobs:gc --apply')->assertExitCode(0);

        $this->assertTrue($store->exists($blob->digest), 'a snapshot-referenced blob is pinned and never collected');
    }

    public function test_unknown_blob_digest_is_rejected_and_nothing_is_persisted(): void // ★ AC-6
    {
        $cohort = $this->bootTenantWithCohort();
        $unknown = hash('sha256', 'never stored');

        $this->expectException(UnknownBlobReferenceException::class);
        try {
            $this->service()->handle($cohort, ['a' => 1], [$unknown], ['form' => 'f']);
        } finally {
            $this->assertDatabaseCount('application_submissions', 0); // rolled back
        }
    }

    public function test_submission_is_tenant_isolated(): void // AC-4
    {
        $cohort = $this->bootTenantWithCohort();
        $submission = $this->service()->handle($cohort, ['a' => 1], [], ['form' => 'f']);
        $id = $submission->id;

        // Switch to a different tenant — the submission must be invisible.
        [$otherUser, $otherOrg] = $this->bootUserWithOrg('Other Org');
        $this->actingAsTenant($otherUser, $otherOrg);

        $this->assertSame(0, ApplicationSubmission::count(), 'cross-tenant query returns no rows');
        $this->assertNull(ApplicationSubmission::find($id), 'cross-tenant find returns null (→404)');
    }

    public function test_empty_answers_produce_a_valid_snapshot(): void // AC-8
    {
        $cohort = $this->bootTenantWithCohort();

        $submission = $this->service()->handle($cohort, [], [], ['form' => 'f']);

        $this->assertSame([], $submission->fresh()->submission_snapshot['answers']);
        $this->assertSame([], $submission->fresh()->submission_snapshot['blob_refs']);
    }

    public function test_oversize_payload_is_rejected_fail_closed(): void // AC-8
    {
        $cohort = $this->bootTenantWithCohort();
        $huge = ['blob' => str_repeat('x', 1_100_000)]; // > 1 MiB

        $this->expectException(SubmissionTooLargeException::class);
        try {
            $this->service()->handle($cohort, $huge, [], ['form' => 'f']);
        } finally {
            $this->assertDatabaseCount('application_submissions', 0);
        }
    }
}

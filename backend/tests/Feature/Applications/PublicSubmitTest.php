<?php

declare(strict_types=1);

namespace Tests\Feature\Applications;

use App\Modules\Applications\Application\SubmitApplication;
use App\Modules\Applications\Domain\Models\ApplicationSubmission;
use App\Modules\Cohorts\Application\CloseCohort;
use App\Modules\Cohorts\Domain\Models\Cohort;
use App\Modules\Cohorts\Domain\Models\CohortStatus;
use App\Modules\Identity\Domain\Models\Account;
use App\Shared\Idempotency\Exceptions\IdempotencyConflictException;
use App\Shared\Outbox\OutboxEvent;
use App\Shared\Storage\ContentAddressedStore;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Testing\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Story 2.7 — the public idempotent submit engine. The applicant authenticates
 * via `sub` but is NOT a tenant member; the submission inherits the cohort's org.
 */
final class PublicSubmitTest extends TestCase
{
    use RefreshDatabase;

    private Cohort $cohort;

    private string $cohortOrgId;

    private string $formVersionId;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('s3');
        config(['blob.disk' => 's3', 'blob.max_bytes' => 1_048_576, 'blob.path_prefix' => 'blobs']);

        // An operator's tenant owns an open cohort with a published form version.
        // createBareOrg() does NOT authenticate the test user, so each test below
        // controls auth itself (the applicant is never the cohort's operator).
        $org = $this->createBareOrg();
        $this->cohortOrgId = $org->id;
        // cohorts.form_version_id is CHAR(26) (ULID FK to form_versions.id). The old
        // fixture used 'form-v1' (7 chars), which the column space-padded to 26 and
        // the snapshot copied verbatim into JSON — false-positive padding "bug" that
        // only existed in tests. Production always stores real ULIDs.
        $this->formVersionId = (string) Str::ulid();
        $this->cohort = $this->withoutTenantContext(function () use ($org): Cohort {
            $cohort = new Cohort([
                'program_id' => (string) Str::ulid(),
                'form_version_id' => $this->formVersionId,
                'name' => 'Cohort One',
                'status' => CohortStatus::Open,
            ]);
            $cohort->setAttribute('organization_id', $org->id);
            $cohort->save();

            return $cohort;
        });
    }

    /** Submit as an applicant with NO tenant context (mirrors the public request). */
    private function submit(string $key, array $body, ?Account $applicant = null, array $files = []): TestResponse
    {
        $applicant ??= $this->makeAccount();

        return $this->withoutTenantContext(fn () => $this->actingAs($applicant, 'web')
            ->withHeader('Idempotency-Key', $key)
            ->postJson("/api/v1/apply/{$this->cohort->id}/submit", $body + ($files ? ['files' => $files] : [])));
    }

    private function systemFind(string $id): ?ApplicationSubmission
    {
        return app(TenantContext::class)->runAsSystem(fn () => ApplicationSubmission::find($id));
    }

    public function test_submit_records_one_snapshot_under_the_cohort_org_and_emits_outbox_and_audit(): void // AC-1/3/5
    {
        $resp = $this->submit('key-1', ['answers' => ['name' => 'Omar', 'idea' => 'Solar Nile']]);

        $resp->assertCreated()
            ->assertJsonPath('status', 'received')
            ->assertJsonPath('cohort_id', $this->cohort->id);
        $reference = $resp->json('reference_number');

        $this->assertDatabaseCount('application_submissions', 1);
        $submission = $this->systemFind($reference);
        $this->assertNotNull($submission);
        $this->assertSame($this->cohortOrgId, $submission->organization_id, 'submission is owned by the cohort org');
        // assertEquals (order-insensitive): the snapshot is content-addressed and
        // canonicalizes keys via alphabetical sort for stable hashing — production
        // stores ['idea','name'], test sent ['name','idea']. Equivalent contents.
        $this->assertEquals(['name' => 'Omar', 'idea' => 'Solar Nile'], $submission->submission_snapshot['answers']);
        $this->assertSame($this->formVersionId, $submission->submission_snapshot['form_version_id']);

        $this->assertDatabaseHas('outbox_events', ['event_type' => 'ApplicationSubmitted']);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'application.submitted',
            'organization_id' => $this->cohortOrgId,
            'target_id' => $reference,
        ]);
    }

    public function test_duplicate_idempotency_key_returns_the_original_receipt_not_a_second_record(): void // AC-1 (FR-032)
    {
        $body = ['answers' => ['name' => 'Omar']];
        $first = $this->submit('key-dup', $body)->assertCreated();
        $second = $this->submit('key-dup', $body)->assertCreated();

        $this->assertSame($first->json('reference_number'), $second->json('reference_number'));
        $this->assertDatabaseCount('application_submissions', 1);
        $this->assertSame(1, OutboxEvent::where('event_type', 'ApplicationSubmitted')->count());
    }

    public function test_submission_to_a_closed_cohort_is_rejected_422(): void // AC-2 (FR-033)
    {
        app(CloseCohort::class)->handle($this->cohort);

        $this->submit('key-closed', ['answers' => ['a' => 1]])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'COHORT_CLOSED');
        $this->assertDatabaseCount('application_submissions', 0);
    }

    public function test_idempotency_replay_after_a_mid_attempt_close_replays_the_original_receipt(): void // ★ AC-2
    {
        $body = ['answers' => ['name' => 'Omar']];
        $first = $this->submit('key-race', $body)->assertCreated();

        // The cohort closes between the two taps of the same submit.
        app(CloseCohort::class)->handle($this->cohort);

        // The replay returns the original receipt — NOT a 422 re-evaluation.
        $replay = $this->submit('key-race', $body)->assertCreated();
        $this->assertSame($first->json('reference_number'), $replay->json('reference_number'));
        $this->assertDatabaseCount('application_submissions', 1);
    }

    public function test_same_key_with_a_different_payload_is_a_conflict_422(): void // AC-2 (2.2 AC-7)
    {
        $this->submit('key-fp', ['answers' => ['name' => 'Omar']])->assertCreated();

        $this->submit('key-fp', ['answers' => ['name' => 'Someone Else']])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'IDEMPOTENCY_CONFLICT');
        $this->assertDatabaseCount('application_submissions', 1);
    }

    public function test_a_different_applicant_cannot_replay_anothers_key(): void // ★ cross-actor fingerprint (2.2 AC-7)
    {
        // Asserted at the service layer: the fingerprint binds the actor (`sub`),
        // so the same key+payload from a different applicant is a conflict, never a
        // replay of another's receipt. (HTTP auth's web-session fallback can't model
        // two distinct anonymous applicants in one request cycle.)
        $service = app(SubmitApplication::class);
        $answers = ['name' => 'Omar'];

        $service->handle($this->cohort->id, 'key-shared', 'sub-A', $answers);

        $this->expectException(IdempotencyConflictException::class);
        try {
            $service->handle($this->cohort->id, 'key-shared', 'sub-B', $answers);
        } finally {
            $this->assertDatabaseCount('application_submissions', 1);
        }
    }

    public function test_inline_file_upload_is_stored_content_addressed_and_referenced_in_the_snapshot(): void // AC-6
    {
        $file = File::fake()->create('cv.pdf', 4, 'application/pdf');
        $resp = $this->submit('key-file', ['answers' => ['a' => 1]], files: [$file])->assertCreated();

        $submission = $this->systemFind($resp->json('reference_number'));
        $this->assertNotNull($submission);
        $refs = $submission->submission_snapshot['blob_refs'];
        $this->assertCount(1, $refs);
        $this->assertTrue(app(ContentAddressedStore::class)->exists($refs[0]), 'uploaded blob is stored');
    }

    public function test_too_many_files_is_rejected_422(): void // memory-exhaustion guard
    {
        $files = array_map(static fn (int $i) => File::fake()->create("f{$i}.pdf", 1, 'application/pdf'), range(1, 21));

        $this->submit('key-many', ['answers' => ['a' => 1]], files: $files)
            ->assertStatus(422)
            ->assertJsonPath('error.details.files.0', 'The files field must not have more than 20 items.');
        $this->assertDatabaseCount('application_submissions', 0);
    }

    public function test_unknown_cohort_is_404(): void
    {
        $this->withoutTenantContext(fn () => $this->actingAs($this->makeAccount(), 'web')
            ->withHeader('Idempotency-Key', 'k')
            ->postJson('/api/v1/apply/'.Str::ulid().'/submit', ['answers' => ['a' => 1]]))
            ->assertNotFound();
    }

    public function test_missing_idempotency_key_is_rejected_422(): void
    {
        $this->withoutTenantContext(fn () => $this->actingAs($this->makeAccount(), 'web')
            ->postJson("/api/v1/apply/{$this->cohort->id}/submit", ['answers' => ['a' => 1]]))
            ->assertStatus(422)
            ->assertJsonPath('error.details.idempotency_key.0', 'The idempotency key field is required.');
    }

    public function test_submit_requires_authentication(): void
    {
        $this->withoutTenantContext(fn () => $this->withHeader('Idempotency-Key', 'k')
            ->postJson("/api/v1/apply/{$this->cohort->id}/submit", ['answers' => ['a' => 1]]))
            ->assertUnauthorized();
    }
}

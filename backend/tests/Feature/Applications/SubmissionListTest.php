<?php

declare(strict_types=1);

namespace Tests\Feature\Applications;

use App\Modules\Applications\Domain\Models\ApplicationSubmission;
use App\Modules\Cohorts\Domain\Models\Cohort;
use App\Modules\Cohorts\Domain\Models\CohortStatus;
use App\Modules\Identity\Domain\Models\ExternalUser;
use App\Modules\Organizations\Domain\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Story 2.8 (FR-034) — the operator's tenant-scoped submission read API: list +
 * detail. The funnel (viewed/started) and operator UI are follow-ups (Learning
 * Telemetry FR-080 / frontend foundation 1.0 are not built); `submitted` is the
 * list's pagination total.
 */
final class SubmissionListTest extends TestCase
{
    use RefreshDatabase;

    private function seedCohort(Organization $org): Cohort
    {
        return $this->withoutTenantContext(function () use ($org): Cohort {
            $cohort = new Cohort([
                'program_id' => (string) Str::ulid(),
                'form_version_id' => 'form-v1',
                'name' => 'Cohort One',
                'status' => CohortStatus::Open,
            ]);
            $cohort->setAttribute('organization_id', $org->id);
            $cohort->save();

            return $cohort;
        });
    }

    /** @param array<string, mixed> $answers */
    private function seedSubmission(Cohort $cohort, Organization $org, array $answers): ApplicationSubmission
    {
        return $this->withoutTenantContext(function () use ($cohort, $org, $answers): ApplicationSubmission {
            $s = new ApplicationSubmission([
                'cohort_id' => $cohort->id,
                'submission_snapshot' => [
                    'answers' => $answers,
                    'blob_refs' => [],
                    'form_version_id' => 'form-v1',
                    'program_version_id' => null,
                    'rubric_version_id' => null,
                ],
            ]);
            $s->setAttribute('organization_id', $org->id);
            $s->save();

            return $s;
        });
    }

    private function getAs(ExternalUser $user, Organization $org, string $path): TestResponse
    {
        return $this->actingAs($user, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->getJson($path);
    }

    public function test_operator_lists_their_cohorts_submissions_newest_first(): void // FR-034
    {
        [$operator, $org] = $this->bootUserWithOrg('Org A');
        $cohort = $this->seedCohort($org);
        $first = $this->seedSubmission($cohort, $org, ['name' => 'Omar']);
        $second = $this->seedSubmission($cohort, $org, ['name' => 'Sara']);

        $resp = $this->getAs($operator, $org, "/api/v1/cohorts/{$cohort->id}/submissions");

        $resp->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 2) // == the funnel's "submitted" count
            ->assertJsonPath('data.0.reference_number', $second->id) // newest first
            ->assertJsonPath('data.1.reference_number', $first->id)
            ->assertJsonStructure(['data' => [['reference_number', 'cohort_id', 'submitted_at']]]);

        // The list is lightweight — no answer snapshot leaks into the row.
        $resp->assertJsonMissingPath('data.0.snapshot');
    }

    public function test_empty_cohort_returns_an_empty_list(): void // empty state
    {
        [$operator, $org] = $this->bootUserWithOrg('Org A');
        $cohort = $this->seedCohort($org);

        $this->getAs($operator, $org, "/api/v1/cohorts/{$cohort->id}/submissions")
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0);
    }

    public function test_detail_returns_the_full_immutable_snapshot(): void
    {
        [$operator, $org] = $this->bootUserWithOrg('Org A');
        $cohort = $this->seedCohort($org);
        $submission = $this->seedSubmission($cohort, $org, ['name' => 'Omar', 'idea' => 'Solar Nile']);

        $this->getAs($operator, $org, "/api/v1/cohorts/{$cohort->id}/submissions/{$submission->id}")
            ->assertOk()
            ->assertJsonPath('data.reference_number', $submission->id)
            ->assertJsonPath('data.snapshot.answers.idea', 'Solar Nile')
            ->assertJsonPath('data.snapshot.form_version_id', 'form-v1');
    }

    public function test_list_only_returns_the_requested_cohorts_submissions(): void
    {
        [$operator, $org] = $this->bootUserWithOrg('Org A');
        $cohortA = $this->seedCohort($org);
        $cohortB = $this->seedCohort($org);
        $this->seedSubmission($cohortA, $org, ['name' => 'A1']);
        $this->seedSubmission($cohortB, $org, ['name' => 'B1']);

        $this->getAs($operator, $org, "/api/v1/cohorts/{$cohortA->id}/submissions")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.cohort_id', $cohortA->id);
    }

    public function test_cross_tenant_operator_cannot_list_another_orgs_cohort(): void // AR-6
    {
        [, $orgA] = $this->bootUserWithOrg('Org A');
        $cohortA = $this->seedCohort($orgA);
        $this->seedSubmission($cohortA, $orgA, ['name' => 'secret']);

        // Operator B asks for Org A's cohort with their OWN org header → 404
        // (the cohort is resolved tenant-scoped, so it's invisible to Org B).
        [$operatorB, $orgB] = $this->bootUserWithOrg('Org B');

        $this->getAs($operatorB, $orgB, "/api/v1/cohorts/{$cohortA->id}/submissions")
            ->assertNotFound();
    }

    public function test_cross_tenant_operator_cannot_read_another_orgs_submission_detail(): void // AR-6
    {
        [, $orgA] = $this->bootUserWithOrg('Org A');
        $cohortA = $this->seedCohort($orgA);
        $submission = $this->seedSubmission($cohortA, $orgA, ['name' => 'secret']);

        [$operatorB, $orgB] = $this->bootUserWithOrg('Org B');

        $this->getAs($operatorB, $orgB, "/api/v1/cohorts/{$cohortA->id}/submissions/{$submission->id}")
            ->assertNotFound();
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        // createBareOrg() does not log anyone in (bootUserWithOrg would, and the
        // sanctum guard falls back to the web session), so this request is truly
        // anonymous.
        $org = $this->createBareOrg('Org A');
        $cohort = $this->seedCohort($org);

        $this->withHeader('X-Organization-Id', $org->id)
            ->getJson("/api/v1/cohorts/{$cohort->id}/submissions")
            ->assertUnauthorized();
    }
}

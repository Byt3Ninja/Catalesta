<?php

declare(strict_types=1);

namespace Tests\Feature\Cohorts;

use App\Modules\Applications\Domain\Models\ApplicationSubmission;
use App\Modules\Cohorts\Domain\Models\Cohort;
use App\Modules\Cohorts\Domain\Models\CohortStatus;
use App\Modules\Identity\Domain\Models\Account;
use App\Modules\Organizations\Domain\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Story 1.5 (AC-4, FR-009 read) — the operator Home needs a tenant-scoped cohort
 * list carrying a `submissions_count` so Home derives its single next action in
 * one call. Tenant isolation is the BelongsToTenant global scope's job (AR-6),
 * not a manual filter; `viewAny` allows any tenant member.
 */
final class CohortIndexTest extends TestCase
{
    use RefreshDatabase;

    private function seedCohort(Organization $org, string $name = 'Cohort One'): Cohort
    {
        return $this->withoutTenantContext(function () use ($org, $name): Cohort {
            $cohort = new Cohort([
                'program_id' => (string) Str::ulid(),
                'name' => $name,
                'status' => CohortStatus::Open,
            ]);
            $cohort->setAttribute('organization_id', $org->id);
            $cohort->save();

            return $cohort;
        });
    }

    private function seedSubmission(Cohort $cohort, Organization $org): void
    {
        $this->withoutTenantContext(function () use ($cohort, $org): void {
            $s = new ApplicationSubmission([
                'cohort_id' => $cohort->id,
                'submission_snapshot' => [
                    'answers' => ['name' => 'Omar'],
                    'blob_refs' => [],
                    'form_version_id' => 'form-v1',
                    'program_version_id' => null,
                    'rubric_version_id' => null,
                ],
            ]);
            $s->setAttribute('organization_id', $org->id);
            $s->save();
        });
    }

    private function getAs(Account $user, Organization $org, string $path): TestResponse
    {
        return $this->actingAs($user, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->getJson($path);
    }

    public function test_lists_the_tenants_cohorts_with_submissions_count(): void
    {
        [$operator, $org] = $this->bootUserWithOrg('Org A');
        $cohort = $this->seedCohort($org);
        $this->seedSubmission($cohort, $org);
        $this->seedSubmission($cohort, $org);

        $this->getAs($operator, $org, '/api/v1/cohorts')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $cohort->id)
            ->assertJsonPath('data.0.submissions_count', 2)
            ->assertJsonStructure(['data' => [['id', 'name', 'status', 'submissions_count']]]);
    }

    public function test_empty_when_the_tenant_has_no_cohorts(): void
    {
        [$operator, $org] = $this->bootUserWithOrg('Org A');

        $this->getAs($operator, $org, '/api/v1/cohorts')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_cross_tenant_cohorts_are_not_listed(): void // AR-6
    {
        [, $orgA] = $this->bootUserWithOrg('Org A');
        $this->seedCohort($orgA, 'A-only');

        [$operatorB, $orgB] = $this->bootUserWithOrg('Org B');
        $bCohort = $this->seedCohort($orgB, 'B-only');

        $this->getAs($operatorB, $orgB, '/api/v1/cohorts')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $bCohort->id);
    }

    public function test_a_plain_tenant_member_can_list_without_cohorts_manage(): void
    {
        // bootUserWithOrg grants the owner role; viewAny must NOT require the
        // cohorts.manage permission — any authenticated member may read.
        [$operator, $org] = $this->bootUserWithOrg('Org A');
        $this->seedCohort($org);

        $this->getAs($operator, $org, '/api/v1/cohorts')->assertOk();
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $org = $this->createBareOrg('Org A');
        $this->seedCohort($org);

        $this->withHeader('X-Organization-Id', $org->id)
            ->getJson('/api/v1/cohorts')
            ->assertUnauthorized();
    }
}

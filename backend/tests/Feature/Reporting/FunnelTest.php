<?php

declare(strict_types=1);

namespace Tests\Feature\Reporting;

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
 * Story 2.8 (FR-080) — the operator funnel. `submitted` is the durable submissions
 * count; `viewed`/`started` are telemetry; `viewed` is clamped >= `started`. This
 * also closes the FR-080 DoD predicate: a full viewed→started→submitted flow emits
 * and the funnel returns all three (events emit + are queryable).
 */
final class FunnelTest extends TestCase
{
    use RefreshDatabase;

    private function seedCohort(Organization $org): Cohort
    {
        return $this->withoutTenantContext(function () use ($org): Cohort {
            $cohort = new Cohort([
                'program_id' => (string) Str::ulid(),
                'name' => 'Cohort One',
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
                'submission_snapshot' => ['answers' => [], 'blob_refs' => [], 'form_version_id' => 'fv'],
            ]);
            $s->setAttribute('organization_id', $org->id);
            $s->save();
        });
    }

    private function funnelAs(ExternalUser $user, Organization $org, string $cohortId): TestResponse
    {
        return $this->actingAs($user, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->getJson("/api/v1/cohorts/{$cohortId}/funnel");
    }

    public function test_full_flow_view_start_submit_yields_all_three_counts(): void
    {
        [$operator, $org] = $this->bootUserWithOrg('Org A');
        $cohort = $this->seedCohort($org);

        // viewed — the public apply page (no auth/tenant)
        $this->getJson("/api/v1/apply/{$cohort->id}")->assertOk();
        // started — the public beacon
        $this->postJson("/api/v1/apply/{$cohort->id}/events", ['event' => 'started'])->assertNoContent();
        // submitted — a durable submission
        $this->seedSubmission($cohort, $org);

        $this->funnelAs($operator, $org, $cohort->id)
            ->assertOk()
            ->assertJsonPath('data.viewed', 1)
            ->assertJsonPath('data.started', 1)
            ->assertJsonPath('data.submitted', 1);
    }

    public function test_viewed_is_clamped_to_at_least_started(): void
    {
        [$operator, $org] = $this->bootUserWithOrg('Org A');
        $cohort = $this->seedCohort($org);

        // Two starts, zero views (beacon-loss undercount) → viewed clamps to 2.
        $this->postJson("/api/v1/apply/{$cohort->id}/events", ['event' => 'started'])->assertNoContent();
        $this->postJson("/api/v1/apply/{$cohort->id}/events", ['event' => 'started'])->assertNoContent();

        $this->funnelAs($operator, $org, $cohort->id)
            ->assertOk()
            ->assertJsonPath('data.started', 2)
            ->assertJsonPath('data.viewed', 2)
            ->assertJsonPath('data.submitted', 0);
    }

    public function test_empty_cohort_funnel_is_all_zero(): void
    {
        [$operator, $org] = $this->bootUserWithOrg('Org A');
        $cohort = $this->seedCohort($org);

        $this->funnelAs($operator, $org, $cohort->id)
            ->assertOk()
            ->assertExactJson(['data' => ['viewed' => 0, 'started' => 0, 'submitted' => 0]]);
    }

    public function test_cross_tenant_operator_gets_404(): void // AR-6
    {
        [, $orgA] = $this->bootUserWithOrg('Org A');
        $cohortA = $this->seedCohort($orgA);
        $this->seedSubmission($cohortA, $orgA);

        [$operatorB, $orgB] = $this->bootUserWithOrg('Org B');

        $this->funnelAs($operatorB, $orgB, $cohortA->id)->assertNotFound();
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $org = $this->createBareOrg('Org A');
        $cohort = $this->seedCohort($org);

        $this->withHeader('X-Organization-Id', $org->id)
            ->getJson("/api/v1/cohorts/{$cohort->id}/funnel")
            ->assertUnauthorized();
    }

    public function test_beacon_rejects_an_unknown_event(): void
    {
        $org = $this->createBareOrg('Org A');
        $cohort = $this->seedCohort($org);

        $this->postJson("/api/v1/apply/{$cohort->id}/events", ['event' => 'hacked'])
            ->assertStatus(422);
    }
}

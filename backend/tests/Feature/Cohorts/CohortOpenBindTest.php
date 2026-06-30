<?php

declare(strict_types=1);

namespace Tests\Feature\Cohorts;

use App\Modules\Cohorts\Application\OpenCohort;
use App\Modules\Cohorts\Domain\Models\Cohort;
use App\Modules\Cohorts\Domain\Models\CohortStatus;
use App\Modules\Forms\Domain\Models\Form;
use App\Modules\Forms\Domain\Models\FormVersion;
use App\Modules\Organizations\Domain\Models\OrganizationMembership;
use App\Shared\Entitlement\EntitlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class CohortOpenBindTest extends TestCase
{
    use RefreshDatabase;

    /** A published form version in the current tenant. */
    private function publishedVersion(?string $hash = null): FormVersion
    {
        $form = Form::create(['name' => 'Intake']);

        return FormVersion::create([
            'form_id' => $form->id,
            'status' => 'published',
            'version_number' => 1,
            'content_hash' => $hash ?? str_repeat('a', 64),
            'definition' => [['type' => 'short_text', 'label' => 'Name', 'id' => 'a']],
            'published_at' => now(),
        ]);
    }

    private function draftCohort(): Cohort
    {
        return Cohort::create(['program_id' => (string) Str::ulid(), 'name' => 'Spring', 'status' => 'draft']);
    }

    public function test_bind_form_sets_the_version_and_returns_200(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $cohort = $this->draftCohort();
        $version = $this->publishedVersion();

        $res = $this->actingAsTenantRequest($user, $org)
            ->postJson("/api/v1/cohorts/{$cohort->id}/bind-form", ['form_version_id' => $version->id]);

        $res->assertStatus(200)->assertJsonPath('data.bound_form_version_id', $version->id);
    }

    public function test_bind_same_version_is_idempotent(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $cohort = $this->draftCohort();
        $version = $this->publishedVersion();
        $cohort->update(['form_version_id' => $version->id]);

        $this->actingAsTenantRequest($user, $org)
            ->postJson("/api/v1/cohorts/{$cohort->id}/bind-form", ['form_version_id' => $version->id])
            ->assertStatus(200);
    }

    public function test_bind_different_version_conflicts_409(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $cohort = $this->draftCohort();
        $v1 = $this->publishedVersion(str_repeat('a', 64));
        $cohort->update(['form_version_id' => $v1->id]);
        $v2 = $this->publishedVersion(str_repeat('b', 64));

        $this->actingAsTenantRequest($user, $org)
            ->postJson("/api/v1/cohorts/{$cohort->id}/bind-form", ['form_version_id' => $v2->id])
            ->assertStatus(409);
    }

    public function test_bind_on_non_draft_conflicts_409(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $cohort = $this->draftCohort();
        $version = $this->publishedVersion();
        $cohort->update(['form_version_id' => $version->id, 'status' => 'open']);

        $this->actingAsTenantRequest($user, $org)
            ->postJson("/api/v1/cohorts/{$cohort->id}/bind-form", ['form_version_id' => $version->id])
            ->assertStatus(409);
    }

    public function test_bind_unknown_or_draft_version_is_404(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $cohort = $this->draftCohort();
        // a draft (unpublished) version is not bindable
        $form = Form::create(['name' => 'Intake']);
        $draftVersion = FormVersion::create(['form_id' => $form->id, 'definition' => []]);

        $this->actingAsTenantRequest($user, $org)
            ->postJson("/api/v1/cohorts/{$cohort->id}/bind-form", ['form_version_id' => $draftVersion->id])
            ->assertStatus(404);
    }

    public function test_bind_returns_404_across_tenants(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $cohort = $this->draftCohort();
        $version = $this->publishedVersion();

        [$other, $otherOrg] = $this->bootUserWithOrg('Other Org');

        $this->actingAsTenantRequest($other, $otherOrg)
            ->postJson("/api/v1/cohorts/{$cohort->id}/bind-form", ['form_version_id' => $version->id])
            ->assertStatus(404);
    }

    public function test_bind_requires_cohorts_manage_403(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $cohort = $this->draftCohort();
        $version = $this->publishedVersion();

        // Create a member of $org with NO permission keys (no cohorts.manage).
        // Mirror FormAuthoringTest::test_member_without_forms_manage_cannot_create_form.
        $member = $this->makeAccount();
        $memberMembership = new OrganizationMembership(['account_id' => $member->id, 'status' => 'active']);
        $memberMembership->organization_id = $org->id;
        $memberMembership->save();

        // resetTenantContext() clears the stale singleton so ResolveTenant
        // middleware re-resolves it from the X-Organization-Id header.
        $this->resetTenantContext();

        $this->actingAs($member, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->postJson("/api/v1/cohorts/{$cohort->id}/bind-form", ['form_version_id' => $version->id])
            ->assertStatus(403);
    }

    public function test_open_transitions_draft_to_open_with_a_bound_form(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $cohort = $this->draftCohort();
        $version = $this->publishedVersion();
        $cohort->update(['form_version_id' => $version->id]);

        $this->actingAsTenantRequest($user, $org)
            ->postJson("/api/v1/cohorts/{$cohort->id}/open")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'open');
    }

    public function test_open_without_a_bound_form_conflicts_409(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $cohort = $this->draftCohort(); // no form bound

        $this->actingAsTenantRequest($user, $org)
            ->postJson("/api/v1/cohorts/{$cohort->id}/open")
            ->assertStatus(409);
    }

    public function test_open_already_open_conflicts_409(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $cohort = $this->draftCohort();
        $version = $this->publishedVersion();
        $cohort->update(['form_version_id' => $version->id, 'status' => 'open']);

        $this->actingAsTenantRequest($user, $org)
            ->postJson("/api/v1/cohorts/{$cohort->id}/open")
            ->assertStatus(409);
    }

    public function test_open_returns_404_across_tenants(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $cohort = $this->draftCohort();
        $version = $this->publishedVersion();
        $cohort->update(['form_version_id' => $version->id]);

        [$other, $otherOrg] = $this->bootUserWithOrg('Other Org');

        $this->actingAsTenantRequest($other, $otherOrg)
            ->postJson("/api/v1/cohorts/{$cohort->id}/open")
            ->assertStatus(404);
    }

    public function test_bind_form_rejects_foreign_tenant_version_with_404(): void
    {
        // Create a draft cohort in org A.
        [$userA, $orgA] = $this->bootUserWithOrg();
        $this->actingAsTenant($userA, $orgA);
        $cohort = $this->draftCohort();

        // Switch to org B and create a published FormVersion there.
        // BelongsToTenant scopes FormVersion to the creating org's context.
        [$userB, $orgB] = $this->bootUserWithOrg('Org B');
        $this->resetTenantContext();
        $this->actingAsTenant($userB, $orgB);
        $orgBVersion = $this->publishedVersion(str_repeat('b', 64));

        // Acting as org A, the org-B version is invisible via BelongsToTenant →
        // findOrFail throws → 404.
        $this->resetTenantContext();
        $this->actingAsTenantRequest($userA, $orgA)
            ->postJson("/api/v1/cohorts/{$cohort->id}/bind-form", ['form_version_id' => $orgBVersion->id])
            ->assertStatus(404);
    }

    public function test_bind_form_returns_422_when_form_version_id_is_absent(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $cohort = $this->draftCohort();

        // Empty body — BindCohortFormRequest requires form_version_id.
        $this->actingAsTenantRequest($user, $org)
            ->postJson("/api/v1/cohorts/{$cohort->id}/bind-form", [])
            ->assertStatus(422);
    }

    public function test_open_entitlement_gate_propagates_exception_when_denied(): void
    {
        // Bind a throwing entitlement double — mirrors PublishProgramTest pattern.
        // OpenCohort calls $entitlement->check('cohort.open') before the transaction,
        // so a denial must leave the cohort as draft with no audit entry.
        $this->app->bind(EntitlementService::class, fn () => new class implements EntitlementService
        {
            public function check(string $action): void
            {
                throw new \RuntimeException('blocked: '.$action);
            }
        });

        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $cohort = $this->draftCohort();
        $version = $this->publishedVersion();
        $cohort->update(['form_version_id' => $version->id]);

        try {
            $this->app->make(OpenCohort::class)->handle($cohort);
            $this->fail('Expected the entitlement gate to block cohort.open.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('cohort.open', $e->getMessage());
        }

        // Cohort must remain draft — nothing written before the gate fires.
        $this->assertSame(CohortStatus::Draft, $cohort->refresh()->status);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'cohort.opened', 'target_id' => $cohort->id]);
    }
}

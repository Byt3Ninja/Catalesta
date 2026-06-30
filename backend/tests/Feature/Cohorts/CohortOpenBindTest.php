<?php

declare(strict_types=1);

namespace Tests\Feature\Cohorts;

use App\Modules\Cohorts\Domain\Models\Cohort;
use App\Modules\Forms\Domain\Models\Form;
use App\Modules\Forms\Domain\Models\FormVersion;
use App\Modules\Organizations\Domain\Models\OrganizationMembership;
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
}

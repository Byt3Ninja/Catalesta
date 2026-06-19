<?php

declare(strict_types=1);

namespace Tests\Feature\Programs;

use App\Modules\Organizations\Domain\Models\OrganizationMembership;
use App\Modules\Programs\Domain\Models\Program;
use App\Modules\Programs\Domain\Models\ProgramPolicyRecord;
use App\Modules\Programs\Domain\Models\ProgramRoleRequirement;
use App\Modules\Programs\Domain\Models\ProgramStatus;
use App\Modules\Programs\Domain\Models\ProgramTemplate;
use App\Modules\Stages\Domain\Models\ProgramStage;
use App\Modules\Stages\Domain\Models\StageRule;
use App\Modules\Stages\Domain\Models\StageRuleType;
use App\Modules\Stages\Domain\Models\StageTransition;
use App\Modules\Stages\Domain\Models\StageVersion;
use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for Task 4.2 — Program Templates.
 *
 * Covers:
 *   - POST /api/v1/program-templates              (save-as-template)
 *   - POST /api/v1/program-templates/{id}/instantiate (create-from-template)
 *   - Authorization: 403 for members without programs.manage
 *   - Cross-tenant isolation: 404 when accessing another org's template
 */
final class ProgramTemplateTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Happy-path: owner saves a program as a template
    // -------------------------------------------------------------------------

    public function test_owner_can_save_program_as_template_returns_201(): void
    {
        [$user, $org] = $this->bootUserWithOrg();

        // Build a source program with stages
        $source = new Program(['name' => 'Source Program', 'status' => ProgramStatus::Draft]);
        $source->organization_id = $org->id;
        $source->save();

        // Stage 1 (published)
        $s1resp = $this->actingAs($user, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->postJson("/api/v1/programs/{$source->id}/stages", [
                'key' => 'stage-one',
                'name' => 'Stage One',
                'type' => 'application',
            ])
            ->assertStatus(201);

        $stage1Id = $s1resp->json('data.id');

        // Publish stage 1
        $this->actingAs($user, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->postJson("/api/v1/stages/{$stage1Id}/publish")
            ->assertStatus(200);

        // Stage 2 (draft)
        $s2resp = $this->actingAs($user, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->postJson("/api/v1/programs/{$source->id}/stages", [
                'key' => 'stage-two',
                'name' => 'Stage Two',
                'type' => 'screening',
            ])
            ->assertStatus(201);

        $stage2Id = $s2resp->json('data.id');

        // Add a rule to stage 2's draft version
        $stage2 = ProgramStage::find($stage2Id);
        $draftVersion2 = $stage2->versions()->where('status', 'draft')->firstOrFail();
        $rule = new StageRule([
            'stage_version_id' => $draftVersion2->id,
            'type' => StageRuleType::Entry,
            'expression' => [],
        ]);
        $rule->organization_id = $org->id;
        $rule->save();

        // Add a policy
        $policy = new ProgramPolicyRecord([
            'program_id' => $source->id,
            'key' => 'allow_late_applications',
            'value' => true,
        ]);
        $policy->organization_id = $org->id;
        $policy->save();

        // Add a role requirement
        $roleReq = new ProgramRoleRequirement([
            'program_id' => $source->id,
            'role_key' => 'mentor',
            'min_count' => 1,
            'max_count' => 5,
            'is_required' => true,
        ]);
        $roleReq->organization_id = $org->id;
        $roleReq->save();

        // Add a transition from stage1 → stage2
        $transition = new StageTransition([
            'program_id' => $source->id,
            'from_program_stage_id' => $stage1Id,
            'to_program_stage_id' => $stage2Id,
            'condition' => null,
            'order_index' => 0,
        ]);
        $transition->organization_id = $org->id;
        $transition->save();

        // POST /api/v1/program-templates
        $response = $this->actingAs($user, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->postJson('/api/v1/program-templates', [
                'program_id' => $source->id,
                'name' => 'My Template',
            ]);

        $response->assertStatus(201);

        $templateId = $response->json('data.id');
        $this->assertNotNull($templateId);

        $response->assertJsonPath('data.name', 'My Template');

        // Blueprint must be non-empty and contain stages, policies, role-reqs, transitions
        $blueprint = $response->json('data.blueprint');
        $this->assertNotNull($blueprint);
        $this->assertArrayHasKey('stages', $blueprint);
        $this->assertCount(2, $blueprint['stages']);
        $this->assertArrayHasKey('policies', $blueprint);
        $this->assertCount(1, $blueprint['policies']);
        $this->assertArrayHasKey('role_requirements', $blueprint);
        $this->assertCount(1, $blueprint['role_requirements']);
        $this->assertArrayHasKey('transitions', $blueprint);
        $this->assertCount(1, $blueprint['transitions']);

        // Transitions should use stage KEYS, not ids
        $this->assertEquals('stage-one', $blueprint['transitions'][0]['from_stage_key']);
        $this->assertEquals('stage-two', $blueprint['transitions'][0]['to_stage_key']);

        // Template must be persisted
        $this->assertDatabaseHas('program_templates', [
            'id' => $templateId,
            'name' => 'My Template',
            'organization_id' => $org->id,
        ]);
    }

    // -------------------------------------------------------------------------
    // Happy-path: owner instantiates a template into a new draft program
    // -------------------------------------------------------------------------

    public function test_owner_can_instantiate_template_returns_201_draft_program(): void
    {
        [$user, $org] = $this->bootUserWithOrg();

        // Build and save a template (reuse the save flow)
        $source = new Program(['name' => 'Template Source', 'status' => ProgramStatus::Draft]);
        $source->organization_id = $org->id;
        $source->save();

        $s1resp = $this->actingAs($user, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->postJson("/api/v1/programs/{$source->id}/stages", [
                'key' => 'stage-alpha',
                'name' => 'Stage Alpha',
                'type' => 'application',
            ])
            ->assertStatus(201);
        $stage1Id = $s1resp->json('data.id');

        $this->actingAs($user, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->postJson("/api/v1/stages/{$stage1Id}/publish")
            ->assertStatus(200);

        $s2resp = $this->actingAs($user, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->postJson("/api/v1/programs/{$source->id}/stages", [
                'key' => 'stage-beta',
                'name' => 'Stage Beta',
                'type' => 'screening',
            ])
            ->assertStatus(201);
        $stage2Id = $s2resp->json('data.id');

        // Add rule to stage 2
        $stage2 = ProgramStage::find($stage2Id);
        $draftVersion2 = $stage2->versions()->where('status', 'draft')->firstOrFail();
        $rule = new StageRule([
            'stage_version_id' => $draftVersion2->id,
            'type' => StageRuleType::Entry,
            'expression' => [],
        ]);
        $rule->organization_id = $org->id;
        $rule->save();

        // Policy
        $policy = new ProgramPolicyRecord([
            'program_id' => $source->id,
            'key' => 'allow_late_applications',
            'value' => true,
        ]);
        $policy->organization_id = $org->id;
        $policy->save();

        // Role req
        $roleReq = new ProgramRoleRequirement([
            'program_id' => $source->id,
            'role_key' => 'mentor',
            'min_count' => 1,
            'max_count' => 3,
            'is_required' => false,
        ]);
        $roleReq->organization_id = $org->id;
        $roleReq->save();

        // Transition
        $transition = new StageTransition([
            'program_id' => $source->id,
            'from_program_stage_id' => $stage1Id,
            'to_program_stage_id' => $stage2Id,
            'condition' => null,
            'order_index' => 0,
        ]);
        $transition->organization_id = $org->id;
        $transition->save();

        // Save as template
        $saveResp = $this->actingAs($user, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->postJson('/api/v1/program-templates', [
                'program_id' => $source->id,
                'name' => 'Beta Template',
            ])
            ->assertStatus(201);

        $templateId = $saveResp->json('data.id');

        // Instantiate
        $instantiateResp = $this->actingAs($user, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->postJson("/api/v1/program-templates/{$templateId}/instantiate", [
                'name' => 'New Program From Template',
            ]);

        $instantiateResp->assertStatus(201);

        $newProgramId = $instantiateResp->json('data.id');
        $this->assertNotNull($newProgramId);

        // Must be draft
        $instantiateResp->assertJsonPath('data.status', 'draft');

        // Slug must be distinct from source
        $this->assertNotEquals($source->slug, $instantiateResp->json('data.slug'));

        // Must have 2 stages each with a single DRAFT version
        $newStages = ProgramStage::where('program_id', $newProgramId)->orderBy('order_index')->get();
        $this->assertCount(2, $newStages, 'Instantiated program must have 2 stages');

        foreach ($newStages as $newStage) {
            $versions = StageVersion::where('program_stage_id', $newStage->id)->get();
            $this->assertCount(1, $versions, "Stage {$newStage->key} must have exactly 1 version");
            $this->assertEquals('draft', $versions->first()->status->value, 'Version must be draft');
            $this->assertNull($newStage->current_published_version_id, 'No published version on instantiated stage');
        }

        // Stage rule must be copied
        $newStageBeta = $newStages->firstWhere('key', 'stage-beta');
        $this->assertNotNull($newStageBeta);
        $newDraftVersionBeta = StageVersion::where('program_stage_id', $newStageBeta->id)->first();
        $betaRules = StageRule::where('stage_version_id', $newDraftVersionBeta->id)->get();
        $this->assertCount(1, $betaRules, 'Stage rule must be copied from blueprint');
        $this->assertEquals(StageRuleType::Entry->value, $betaRules->first()->type->value);

        // Policy copied
        $newPolicies = ProgramPolicyRecord::where('program_id', $newProgramId)->get();
        $this->assertCount(1, $newPolicies);
        $this->assertEquals('allow_late_applications', $newPolicies->first()->key);

        // Role requirement copied
        $newRoleReqs = ProgramRoleRequirement::where('program_id', $newProgramId)->get();
        $this->assertCount(1, $newRoleReqs);
        $this->assertEquals('mentor', $newRoleReqs->first()->role_key);

        // Transition remapped to new stage ids (not source ids)
        $newTransitions = StageTransition::where('program_id', $newProgramId)->get();
        $this->assertCount(1, $newTransitions);

        $newTransition = $newTransitions->first();
        $this->assertNotEquals($stage1Id, $newTransition->from_program_stage_id);
        $this->assertNotEquals($stage2Id, $newTransition->to_program_stage_id);

        $newStageAlpha = $newStages->firstWhere('key', 'stage-alpha');
        $this->assertEquals($newStageAlpha->id, $newTransition->from_program_stage_id);
        $this->assertEquals($newStageBeta->id, $newTransition->to_program_stage_id);

        // NO cohorts
        $this->assertDatabaseMissing('cohorts', ['program_id' => $newProgramId]);
    }

    // -------------------------------------------------------------------------
    // Authorization: member without programs.manage cannot save a template
    // -------------------------------------------------------------------------

    public function test_member_without_manage_cannot_save_template(): void
    {
        $this->seed(PermissionCatalogSeeder::class);

        $org = $this->createBareOrg('Template Perm Org');

        $source = new Program(['name' => 'Source', 'status' => ProgramStatus::Draft]);
        $source->organization_id = $org->id;
        $source->save();

        $member = $this->makeExternalUser();
        $membership = new OrganizationMembership(['external_user_id' => $member->id, 'status' => 'active']);
        $membership->organization_id = $org->id;
        $membership->save();

        $response = $this->actingAs($member, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->postJson('/api/v1/program-templates', [
                'program_id' => $source->id,
                'name' => 'Hijacked Template',
            ]);

        $response->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // Authorization: member without programs.manage cannot instantiate a template
    // -------------------------------------------------------------------------

    public function test_member_without_manage_cannot_instantiate_template(): void
    {
        $this->seed(PermissionCatalogSeeder::class);

        $org = $this->createBareOrg('Template Instantiate Perm Org');

        // Insert a template directly (no HTTP) so we don't need an owner actingAs
        $template = new ProgramTemplate([
            'name' => 'Owner Template',
            'slug' => 'owner-template',
            'blueprint' => ['program' => [], 'stages' => [], 'policies' => [], 'role_requirements' => [], 'transitions' => []],
        ]);
        $template->organization_id = $org->id;
        $template->save();

        // Create a bare member (no roles)
        $member = $this->makeExternalUser();
        $membership = new OrganizationMembership(['external_user_id' => $member->id, 'status' => 'active']);
        $membership->organization_id = $org->id;
        $membership->save();

        $response = $this->actingAs($member, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->postJson("/api/v1/program-templates/{$template->id}/instantiate", [
                'name' => 'Hijacked Program',
            ]);

        $response->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // Cross-tenant: template belonging to org B → 404 when accessed from org A
    // -------------------------------------------------------------------------

    public function test_cross_tenant_template_instantiate_returns_404(): void
    {
        $this->seed(PermissionCatalogSeeder::class);

        // Create org B with a template inserted directly (no HTTP for orgB)
        // to avoid the singleton TenantContext carrying orgB context into
        // the orgA request via withoutTenantContext restoration.
        $orgB = $this->createBareOrg('Org B Templates');

        $templateB = new ProgramTemplate([
            'name' => 'Org B Template',
            'slug' => 'org-b-template',
            'blueprint' => ['program' => [], 'stages' => [], 'policies' => [], 'role_requirements' => [], 'transitions' => []],
        ]);
        $templateB->organization_id = $orgB->id;
        $templateB->save();

        // Org A tries to instantiate Org B's template
        [$userA, $orgA] = $this->bootUserWithOrg('Org A Templates');

        $response = $this->actingAs($userA, 'web')
            ->withHeader('X-Organization-Id', $orgA->id)
            ->postJson("/api/v1/program-templates/{$templateB->id}/instantiate", [
                'name' => 'Cross-tenant Hijack',
            ]);

        $response->assertStatus(404);
    }
}

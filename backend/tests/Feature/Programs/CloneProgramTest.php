<?php

declare(strict_types=1);

namespace Tests\Feature\Programs;

use App\Modules\Organizations\Domain\Models\OrganizationMembership;
use App\Modules\Programs\Domain\Models\Program;
use App\Modules\Programs\Domain\Models\ProgramPolicyRecord;
use App\Modules\Programs\Domain\Models\ProgramRoleRequirement;
use App\Modules\Programs\Domain\Models\ProgramStatus;
use App\Modules\Stages\Domain\Models\ProgramStage;
use App\Modules\Stages\Domain\Models\StageRule;
use App\Modules\Stages\Domain\Models\StageRuleType;
use App\Modules\Stages\Domain\Models\StageTransition;
use App\Modules\Stages\Domain\Models\StageVersion;
use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for POST /api/v1/programs/{program}/clone (Task 4.1).
 *
 * Coverage:
 *   - Owner clones a program → 201, new draft with all sub-resources copied
 *   - Clone slug is distinct from source slug
 *   - Clone stages each have a DRAFT stage_version (none published)
 *   - Stage rules are copied to clone's draft version
 *   - Policies + role requirements are copied
 *   - Transition remapped to cloned stage ids
 *   - NO cohorts copied
 *   - Member WITHOUT programs.manage → 403
 *   - Cross-tenant clone id → 404
 */
final class CloneProgramTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Happy-path: full deep clone
    // -------------------------------------------------------------------------

    public function test_owner_can_clone_program_returns_201_draft(): void
    {
        [$user, $org] = $this->bootUserWithOrg();

        // --- Build source program ---
        $source = new Program(['name' => 'Source Program', 'status' => ProgramStatus::Draft]);
        $source->organization_id = $org->id;
        $source->save();

        // Stage 1 (published): create + publish via API
        $s1resp = $this->actingAs($user, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->postJson("/api/v1/programs/{$source->id}/stages", [
                'key' => 'stage-one',
                'name' => 'Stage One',
                'type' => 'application',
            ])
            ->assertStatus(201);

        $stage1Id = $s1resp->json('data.id');

        // Publish stage 1 so that cloning tests draft-only copies even from published source
        $this->actingAs($user, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->postJson("/api/v1/stages/{$stage1Id}/publish")
            ->assertStatus(200);

        // Stage 2 (draft): not published
        $s2resp = $this->actingAs($user, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->postJson("/api/v1/programs/{$source->id}/stages", [
                'key' => 'stage-two',
                'name' => 'Stage Two',
                'type' => 'screening',
            ])
            ->assertStatus(201);

        $stage2Id = $s2resp->json('data.id');

        // Add a stage rule to stage 1's published version
        $stage1 = ProgramStage::find($stage1Id);
        $publishedVersion = StageVersion::find($stage1->current_published_version_id);

        // We can't add a rule via API to a published version (immutable), so skip rule testing
        // on the published version. Instead add rule to stage 2's draft version.
        $stage2 = ProgramStage::find($stage2Id);
        $draftVersion2 = $stage2->versions()->where('status', 'draft')->firstOrFail();

        $rule = new StageRule([
            'stage_version_id' => $draftVersion2->id,
            'type' => StageRuleType::Entry,
            'expression' => [],
        ]);
        $rule->organization_id = $org->id;
        $rule->save();

        // Add a policy to the source program
        $policy = new ProgramPolicyRecord([
            'program_id' => $source->id,
            'key' => 'allow_late_applications',
            'value' => true,
        ]);
        $policy->organization_id = $org->id;
        $policy->save();

        // Add a role requirement to the source program
        $roleReq = new ProgramRoleRequirement([
            'program_id' => $source->id,
            'role_key' => 'mentor',
            'min_count' => 1,
            'max_count' => 5,
            'is_required' => true,
        ]);
        $roleReq->organization_id = $org->id;
        $roleReq->save();

        // Add a stage transition from stage1 → stage2
        $transition = new StageTransition([
            'program_id' => $source->id,
            'from_program_stage_id' => $stage1Id,
            'to_program_stage_id' => $stage2Id,
            'condition' => null,
            'order_index' => 0,
        ]);
        $transition->organization_id = $org->id;
        $transition->save();

        // Add a cohort to the source to confirm it is NOT copied
        $this->actingAs($user, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->postJson("/api/v1/programs/{$source->id}/cohorts", [
                'name' => 'Source Cohort',
                'starts_at' => '2026-09-01',
                'ends_at' => '2027-06-30',
            ])
            ->assertStatus(201);

        // --- Clone ---
        $response = $this->actingAs($user, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->postJson("/api/v1/programs/{$source->id}/clone", [
                'name' => 'Copy',
            ]);

        $response->assertStatus(201);

        $cloneId = $response->json('data.id');
        $this->assertNotNull($cloneId);

        // --- Assert clone is a distinct draft ---
        $response->assertJsonPath('data.name', 'Copy');
        $response->assertJsonPath('data.status', 'draft');

        $cloneSlug = $response->json('data.slug');
        $this->assertNotEquals($source->slug, $cloneSlug);

        // --- Stages ---
        $cloneStages = ProgramStage::where('program_id', $cloneId)->orderBy('order_index')->get();
        $this->assertCount(2, $cloneStages, 'Clone should have 2 stages');

        foreach ($cloneStages as $cloneStage) {
            // Each cloned stage must have exactly one version and it must be draft
            $versions = StageVersion::where('program_stage_id', $cloneStage->id)->get();
            $this->assertCount(1, $versions, "Stage {$cloneStage->key} should have exactly 1 version");
            $this->assertEquals('draft', $versions->first()->status->value, 'Cloned stage version must be draft');

            // No published version on clone stages
            $this->assertNull($cloneStage->current_published_version_id, 'Clone stage must not have a published version');
        }

        // --- Stage rules copied to clone ---
        $cloneStage2 = $cloneStages->firstWhere('key', 'stage-two');
        $this->assertNotNull($cloneStage2);
        $cloneDraftVersion2 = StageVersion::where('program_stage_id', $cloneStage2->id)->first();
        $cloneRules = StageRule::where('stage_version_id', $cloneDraftVersion2->id)->get();
        $this->assertCount(1, $cloneRules, 'Stage rule must be copied to cloned stage version');
        $this->assertEquals(StageRuleType::Entry->value, $cloneRules->first()->type->value);

        // --- Policy copied ---
        $clonePolicies = ProgramPolicyRecord::where('program_id', $cloneId)->get();
        $this->assertCount(1, $clonePolicies);
        $this->assertEquals('allow_late_applications', $clonePolicies->first()->key);

        // --- Role requirement copied ---
        $cloneRoleReqs = ProgramRoleRequirement::where('program_id', $cloneId)->get();
        $this->assertCount(1, $cloneRoleReqs);
        $this->assertEquals('mentor', $cloneRoleReqs->first()->role_key);

        // --- Transition remapped to cloned stage ids ---
        $cloneTransitions = StageTransition::where('program_id', $cloneId)->get();
        $this->assertCount(1, $cloneTransitions);

        $cloneTransition = $cloneTransitions->first();
        // Must reference the CLONED stages, not the source stages
        $this->assertNotEquals($stage1Id, $cloneTransition->from_program_stage_id);
        $this->assertNotEquals($stage2Id, $cloneTransition->to_program_stage_id);

        $cloneStage1 = $cloneStages->firstWhere('key', 'stage-one');
        $this->assertEquals($cloneStage1->id, $cloneTransition->from_program_stage_id);
        $this->assertEquals($cloneStage2->id, $cloneTransition->to_program_stage_id);

        // --- NO cohorts copied ---
        $cloneProgram = Program::find($cloneId);
        $this->assertCount(0, $cloneProgram->cohorts ?? collect(), 'Clone must have zero cohorts');
        $this->assertDatabaseMissing('cohorts', ['program_id' => $cloneId]);
    }

    // -------------------------------------------------------------------------
    // Authorization: member WITHOUT programs.manage → 403
    // -------------------------------------------------------------------------

    public function test_member_without_manage_cannot_clone_program(): void
    {
        $this->seed(PermissionCatalogSeeder::class);

        $org = $this->createBareOrg('Clone Perm Org');

        $source = new Program(['name' => 'Source', 'status' => ProgramStatus::Draft]);
        $source->organization_id = $org->id;
        $source->save();

        $member = $this->makeAccount();
        $memberMembership = new OrganizationMembership(['account_id' => $member->id, 'status' => 'active']);
        $memberMembership->organization_id = $org->id;
        $memberMembership->save();

        $response = $this->actingAs($member, 'web')
            ->withHeader('X-Organization-Id', $org->id)
            ->postJson("/api/v1/programs/{$source->id}/clone", ['name' => 'Hijacked Clone']);

        $response->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // Cross-tenant: program belonging to another org → 404
    // -------------------------------------------------------------------------

    public function test_cross_tenant_clone_returns_404(): void
    {
        // Create Org B with a program
        [$ownerB, $orgB] = $this->bootUserWithOrg('Org B Clone');

        $sourceB = new Program(['name' => 'Org B Source', 'status' => ProgramStatus::Draft]);
        $sourceB->organization_id = $orgB->id;
        $sourceB->save();

        // Create Org A user — send OrgA header but target OrgB program
        [$userA, $orgA] = $this->bootUserWithOrg('Org A Clone');

        $response = $this->actingAs($userA, 'web')
            ->withHeader('X-Organization-Id', $orgA->id)
            ->postJson("/api/v1/programs/{$sourceB->id}/clone", ['name' => 'Cross Clone']);

        $response->assertStatus(404);
    }
}

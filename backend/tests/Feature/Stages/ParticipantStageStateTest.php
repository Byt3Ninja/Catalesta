<?php

declare(strict_types=1);

namespace Tests\Feature\Stages;

use App\Modules\Cohorts\Domain\Models\Cohort;
use App\Modules\Identity\Domain\Models\ExternalUser;
use App\Modules\Organizations\Domain\Models\OrganizationMembership;
use App\Modules\Programs\Domain\Models\Program;
use App\Modules\Stages\Application\AdvanceParticipantStage;
use App\Modules\Stages\Domain\Exceptions\StageNotPublishedException;
use App\Modules\Stages\Domain\Models\ParticipantStageState;
use App\Modules\Stages\Domain\Models\ProgramStage;
use App\Modules\Stages\Domain\Models\StageInstance;
use App\Modules\Stages\Domain\Models\StageRule;
use App\Modules\Stages\Domain\Models\StageRuleType;
use App\Modules\Stages\Domain\Models\StageType;
use App\Modules\Stages\Domain\Models\StageVersion;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for participant stage state transitions and stage instance creation.
 */
final class ParticipantStageStateTest extends TestCase
{
    use RefreshDatabase;

    private string $orgId;

    private ExternalUser $participant;

    private Cohort $cohort;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenantContext();
    }

    private function setUpTenantContext(): void
    {
        [$user, $org] = $this->bootUserWithOrg();

        $membership = OrganizationMembership::withoutGlobalScope('tenant')
            ->where('organization_id', $org->id)
            ->where('external_user_id', $user->id)
            ->firstOrFail();

        $this->app->make(TenantContext::class)
            ->setOrganization($org->id, $membership, $membership->effectivePermissionKeys());

        $this->orgId = $org->id;

        $this->participant = $this->makeExternalUser();

        $program = Program::create(['name' => 'Test Program']);

        $this->cohort = Cohort::create([
            'program_id' => $program->id,
            'name' => 'Cohort 2026',
        ]);
    }

    private function makePublishedStage(?array $entryExpression = null, ?array $exitExpression = null): ProgramStage
    {
        $program = Program::create(['name' => 'Stage Program']);

        $stage = ProgramStage::create([
            'program_id' => $program->id,
            'key' => 'screening-'.uniqid(),
            'name' => 'Screening',
            'type' => StageType::Screening,
            'order_index' => 1,
        ]);

        $version = StageVersion::create([
            'program_stage_id' => $stage->id,
            'config' => [],
        ]);

        if ($entryExpression !== null) {
            StageRule::create([
                'stage_version_id' => $version->id,
                'type' => StageRuleType::Entry,
                'expression' => $entryExpression,
            ]);
        }

        if ($exitExpression !== null) {
            StageRule::create([
                'stage_version_id' => $version->id,
                'type' => StageRuleType::Exit,
                'expression' => $exitExpression,
            ]);
        }

        // Publish: set status=published, version_number, and update current_published_version_id
        $version->update(['status' => 'published', 'version_number' => 1]);
        $stage->update(['current_published_version_id' => $version->id]);

        return $stage->fresh();
    }

    // -------------------------------------------------------------------------
    // Test 1: Enter with no entry rule → InProgress + StageInstance created
    // -------------------------------------------------------------------------

    public function test_entering_published_stage_without_entry_rule_sets_in_progress_and_creates_instance(): void
    {
        $stage = $this->makePublishedStage(); // no entry rule

        /** @var AdvanceParticipantStage $service */
        $service = $this->app->make(AdvanceParticipantStage::class);

        $status = $service->enter($this->cohort, $this->participant, $stage);

        $this->assertSame(ParticipantStageState::InProgress, $status->status);
        $this->assertNotNull($status->entered_at);
        $this->assertNull($status->completed_at);

        // Exactly one StageInstance must have been created
        $instances = StageInstance::query()
            ->where('participant_stage_status_id', $status->id)
            ->get();

        $this->assertCount(1, $instances);

        // INVARIANT: instance is bound to the published version active at entry
        $this->assertSame($stage->current_published_version_id, $instances->first()->stage_version_id);
    }

    // -------------------------------------------------------------------------
    // Test 2: Entry rule evaluates FALSE → status Blocked, NO instance created
    // -------------------------------------------------------------------------

    public function test_entering_stage_whose_entry_rule_fails_returns_blocked_without_creating_instance(): void
    {
        // Entry rule: cohort.is_open must be true — we will pass false in context
        $stage = $this->makePublishedStage(
            entryExpression: [
                'field' => 'cohort.is_open',
                'operator' => 'equals',
                'value' => true,
            ]
        );

        /** @var AdvanceParticipantStage $service */
        $service = $this->app->make(AdvanceParticipantStage::class);

        // Pass context where cohort.is_open = false → rule fails
        $status = $service->enter($this->cohort, $this->participant, $stage, [
            'cohort.is_open' => false,
        ]);

        $this->assertSame(ParticipantStageState::Blocked, $status->status);

        // No instance must have been created
        $this->assertDatabaseMissing('stage_instances', [
            'participant_stage_status_id' => $status->id,
        ]);
    }

    // -------------------------------------------------------------------------
    // Test 3: Entry rule evaluates TRUE → InProgress + instance created
    // -------------------------------------------------------------------------

    public function test_entering_stage_whose_entry_rule_passes_creates_instance_in_progress(): void
    {
        $stage = $this->makePublishedStage(
            entryExpression: [
                'field' => 'cohort.is_open',
                'operator' => 'equals',
                'value' => true,
            ]
        );

        /** @var AdvanceParticipantStage $service */
        $service = $this->app->make(AdvanceParticipantStage::class);

        $status = $service->enter($this->cohort, $this->participant, $stage, [
            'cohort.is_open' => true,
        ]);

        $this->assertSame(ParticipantStageState::InProgress, $status->status);
        $this->assertNotNull($status->entered_at);

        $instances = StageInstance::query()
            ->where('participant_stage_status_id', $status->id)
            ->get();

        $this->assertCount(1, $instances);
        $this->assertSame($stage->current_published_version_id, $instances->first()->stage_version_id);
    }

    // -------------------------------------------------------------------------
    // Test 4: Complete when exit rule passes (or no exit rule) → Completed + completed_at
    // -------------------------------------------------------------------------

    public function test_completing_stage_without_exit_rule_sets_completed(): void
    {
        $stage = $this->makePublishedStage(); // no exit rule

        /** @var AdvanceParticipantStage $service */
        $service = $this->app->make(AdvanceParticipantStage::class);

        $status = $service->enter($this->cohort, $this->participant, $stage);
        $this->assertSame(ParticipantStageState::InProgress, $status->status);

        $completed = $service->complete($status);

        $this->assertSame(ParticipantStageState::Completed, $completed->status);
        $this->assertNotNull($completed->completed_at);
    }

    // -------------------------------------------------------------------------
    // Test 5: No published version → throws domain exception
    // -------------------------------------------------------------------------

    public function test_entering_stage_with_no_published_version_throws_domain_exception(): void
    {
        $program = Program::create(['name' => 'Unpublished Program']);

        $stage = ProgramStage::create([
            'program_id' => $program->id,
            'key' => 'draft-stage',
            'name' => 'Draft Stage',
            'type' => StageType::Screening,
            'order_index' => 1,
            // current_published_version_id intentionally null
        ]);

        /** @var AdvanceParticipantStage $service */
        $service = $this->app->make(AdvanceParticipantStage::class);

        $this->expectException(StageNotPublishedException::class);

        $service->enter($this->cohort, $this->participant, $stage);
    }

    // -------------------------------------------------------------------------
    // Test 6: Instance is bound to the published version at entry time (invariant)
    // -------------------------------------------------------------------------

    public function test_stage_instance_is_bound_to_published_version_id_at_entry(): void
    {
        $stage = $this->makePublishedStage();
        $publishedVersionId = $stage->current_published_version_id;

        /** @var AdvanceParticipantStage $service */
        $service = $this->app->make(AdvanceParticipantStage::class);

        $status = $service->enter($this->cohort, $this->participant, $stage);

        $instance = StageInstance::query()
            ->where('participant_stage_status_id', $status->id)
            ->firstOrFail();

        // The instance must reference exactly the version that was current_published_version_id at entry
        $this->assertSame($publishedVersionId, $instance->stage_version_id);
    }

    // -------------------------------------------------------------------------
    // Test 7: Complete on Blocked status is a no-op
    // -------------------------------------------------------------------------

    public function test_complete_on_blocked_status_is_a_noop(): void
    {
        $stage = $this->makePublishedStage(
            entryExpression: [
                'field' => 'cohort.is_open',
                'operator' => 'equals',
                'value' => true,
            ]
        );

        /** @var AdvanceParticipantStage $service */
        $service = $this->app->make(AdvanceParticipantStage::class);

        // Enter with a failing entry rule → status is Blocked
        $status = $service->enter($this->cohort, $this->participant, $stage, [
            'cohort.is_open' => false,
        ]);

        $this->assertSame(ParticipantStageState::Blocked, $status->status);

        // Attempt to complete → should be a no-op
        $completed = $service->complete($status);

        $this->assertSame(ParticipantStageState::Blocked, $completed->status);
        $this->assertNull($completed->completed_at);
    }

    // -------------------------------------------------------------------------
    // Test 8: Complete with failing exit rule stays in progress
    // -------------------------------------------------------------------------

    public function test_complete_with_failing_exit_rule_stays_in_progress(): void
    {
        // Exit rule: cohort.is_open must be true — we will pass false in context
        $stage = $this->makePublishedStage(
            exitExpression: [
                'field' => 'cohort.is_open',
                'operator' => 'equals',
                'value' => true,
            ]
        );

        /** @var AdvanceParticipantStage $service */
        $service = $this->app->make(AdvanceParticipantStage::class);

        // Enter successfully (no entry rule to block)
        $status = $service->enter($this->cohort, $this->participant, $stage);
        $this->assertSame(ParticipantStageState::InProgress, $status->status);

        // Try to complete with failing exit rule context
        $completed = $service->complete($status, [
            'cohort.is_open' => false,
        ]);

        $this->assertSame(ParticipantStageState::InProgress, $completed->status);
        $this->assertNull($completed->completed_at);
    }
}

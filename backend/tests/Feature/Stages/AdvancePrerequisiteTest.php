<?php

declare(strict_types=1);

namespace Tests\Feature\Stages;

use App\Modules\Cohorts\Domain\Models\Cohort;
use App\Modules\Identity\Domain\Models\Account;
use App\Modules\Organizations\Domain\Models\OrganizationMembership;
use App\Modules\Programs\Domain\Models\Program;
use App\Modules\Stages\Application\AdvanceParticipantStage;
use App\Modules\Stages\Domain\Exceptions\StagePrerequisiteNotMetException;
use App\Modules\Stages\Domain\Models\ParticipantStageState;
use App\Modules\Stages\Domain\Models\ParticipantStageStatus;
use App\Modules\Stages\Domain\Models\ProgramStage;
use App\Modules\Stages\Domain\Models\StageDependency;
use App\Modules\Stages\Domain\Models\StageType;
use App\Modules\Stages\Domain\Models\StageVersion;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for stage prerequisite (dependency) enforcement on participant entry.
 */
final class AdvancePrerequisiteTest extends TestCase
{
    use RefreshDatabase;

    private Account $participant;

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
            ->where('account_id', $user->id)
            ->firstOrFail();

        $this->app->make(TenantContext::class)
            ->setOrganization($org->id, $membership, $membership->effectivePermissionKeys());

        $this->participant = $this->makeAccount();

        $program = Program::create(['name' => 'Test Program']);

        $this->cohort = Cohort::create([
            'program_id' => $program->id,
            'name' => 'Cohort 2026',
        ]);
    }

    private function makePublishedStage(?Program $program = null): ProgramStage
    {
        $program ??= Program::create(['name' => 'Stage Program '.uniqid()]);

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

        $version->update(['status' => 'published', 'version_number' => 1]);
        $stage->update(['current_published_version_id' => $version->id]);

        return ProgramStage::findOrFail($stage->id);
    }

    // -------------------------------------------------------------------------
    // Test 1: Unmet prerequisite → throws + no status/instance written
    // -------------------------------------------------------------------------

    public function test_entering_stage_with_unmet_prerequisite_throws_and_writes_no_status(): void
    {
        $program = Program::create(['name' => 'Prereq Program']);
        $stageA = $this->makePublishedStage($program);
        $stageB = $this->makePublishedStage($program);

        // B depends on A — A is NOT completed yet
        StageDependency::create([
            'program_stage_id' => $stageB->id,
            'depends_on_program_stage_id' => $stageA->id,
        ]);

        /** @var AdvanceParticipantStage $service */
        $service = $this->app->make(AdvanceParticipantStage::class);

        // Use try/catch (not expectException) so the no-side-effects assertion
        // actually runs after the throw — proving zero rows were written.
        try {
            $service->enter($this->cohort, $this->participant, $stageB);
            $this->fail('Expected StagePrerequisiteNotMetException to be thrown.');
        } catch (StagePrerequisiteNotMetException) {
            // expected
        }

        $this->assertDatabaseMissing('participant_stage_statuses', [
            'cohort_id' => $this->cohort->id,
            'account_id' => $this->participant->id,
            'program_stage_id' => $stageB->id,
        ]);
        $this->assertSame(0, ParticipantStageStatus::query()
            ->where('program_stage_id', $stageB->id)->count());
    }

    // -------------------------------------------------------------------------
    // Test 2: Prerequisite completed → entry succeeds with in_progress
    // -------------------------------------------------------------------------

    public function test_entering_stage_after_prerequisite_completed_succeeds_with_in_progress(): void
    {
        $program = Program::create(['name' => 'Prereq Program']);
        $stageA = $this->makePublishedStage($program);
        $stageB = $this->makePublishedStage($program);

        // B depends on A
        StageDependency::create([
            'program_stage_id' => $stageB->id,
            'depends_on_program_stage_id' => $stageA->id,
        ]);

        // Simulate A being completed by inserting a completed status record
        ParticipantStageStatus::create([
            'cohort_id' => $this->cohort->id,
            'account_id' => $this->participant->id,
            'program_stage_id' => $stageA->id,
            'status' => ParticipantStageState::Completed->value,
            'entered_at' => now(),
            'completed_at' => now(),
        ]);

        /** @var AdvanceParticipantStage $service */
        $service = $this->app->make(AdvanceParticipantStage::class);

        $status = $service->enter($this->cohort, $this->participant, $stageB);

        $this->assertSame(ParticipantStageState::InProgress, $status->status);
        $this->assertNotNull($status->entered_at);
    }

    // -------------------------------------------------------------------------
    // Test 3: No dependencies → enters normally (in_progress)
    // -------------------------------------------------------------------------

    public function test_entering_stage_with_no_dependencies_behaves_as_before(): void
    {
        $stage = $this->makePublishedStage();

        // No StageDependency records created

        /** @var AdvanceParticipantStage $service */
        $service = $this->app->make(AdvanceParticipantStage::class);

        $status = $service->enter($this->cohort, $this->participant, $stage);

        $this->assertSame(ParticipantStageState::InProgress, $status->status);
        $this->assertNotNull($status->entered_at);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Assessments;

use App\Modules\Assessments\Application\CreateScoringModel;
use App\Modules\Assessments\Application\ForkScoringModelDraft;
use App\Modules\Assessments\Application\PublishScoringModel;
use App\Modules\Assessments\Application\SaveScoringModelDraft;
use App\Modules\Assessments\Domain\Exceptions\InvalidCriteriaException;
use App\Modules\Assessments\Domain\Exceptions\NoCriteriaException;
use App\Modules\Assessments\Domain\Exceptions\NoDraftException;
use App\Modules\Assessments\Domain\Models\ScoringModel;
use App\Modules\Assessments\Domain\Models\ScoringModelVersion;
use App\Modules\Programs\Domain\Models\Program;
use App\Shared\Versioning\VersionStateException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ScoringModelServiceTest extends TestCase
{
    use RefreshDatabase;

    private function validCriteria(): array
    {
        return [
            [
                'criterion_id' => 'c1',
                'label' => 'Innovation',
                'max_points' => 30,
                'descriptors' => ['Highly innovative', 'Somewhat innovative', 'Not innovative'],
            ],
            [
                'criterion_id' => 'c2',
                'label' => 'Market Fit',
                'max_points' => 20,
                'descriptors' => null,
            ],
        ];
    }

    private function makeModel(): ScoringModel
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $program = Program::factory()->create();

        return $this->app->make(CreateScoringModel::class)->handle($program, 'Evaluation Model');
    }

    // ── CreateScoringModel ─────────────────────────────────────────────

    public function test_create_scoring_model_creates_model_and_empty_draft(): void
    {
        $model = $this->makeModel();

        $this->assertSame('Evaluation Model', $model->name);
        $this->assertNull($model->current_published_version_id);
        $this->assertNotNull($model->draftVersion());
        $this->assertSame([], $model->draftVersion()->criteria);
        $this->assertSame(0, $model->draftVersion()->version_number);
        $this->assertSame('draft', $model->draftVersion()->status->value);
    }

    // ── SaveScoringModelDraft ──────────────────────────────────────────

    public function test_save_draft_stores_criteria_on_the_draft(): void
    {
        $model = $this->makeModel();

        $draft = $this->app->make(SaveScoringModelDraft::class)->handle($model, $this->validCriteria());

        $this->assertSame('draft', $draft->status->value);
        $this->assertCount(2, $draft->criteria);
        $this->assertSame('Innovation', $draft->criteria[0]['label']);
    }

    public function test_save_draft_throws_no_draft_exception_when_no_draft_exists(): void
    {
        $model = $this->makeModel();
        // promote the only draft to published state via direct update (bypassing ImmutableWhenPublished)
        ScoringModelVersion::withoutGlobalScopes()
            ->where('scoring_model_id', $model->id)
            ->update(['status' => 'published', 'version_number' => 1, 'content_hash' => str_repeat('a', 64), 'published_at' => now()]);

        $this->expectException(NoDraftException::class);
        $this->app->make(SaveScoringModelDraft::class)->handle($model->refresh(), $this->validCriteria());
    }

    public function test_save_draft_rejects_invalid_criterion_max_points_zero(): void
    {
        $model = $this->makeModel();
        $bad = [['criterion_id' => 'c1', 'label' => 'Score', 'max_points' => 0, 'descriptors' => null]];

        $this->expectException(InvalidCriteriaException::class);
        $this->app->make(SaveScoringModelDraft::class)->handle($model, $bad);
    }

    public function test_save_draft_rejects_criterion_without_label(): void
    {
        $model = $this->makeModel();
        $bad = [['criterion_id' => 'c1', 'label' => '', 'max_points' => 10, 'descriptors' => null]];

        $this->expectException(InvalidCriteriaException::class);
        $this->app->make(SaveScoringModelDraft::class)->handle($model, $bad);
    }

    // ── PublishScoringModel ────────────────────────────────────────────

    public function test_publish_promotes_draft_to_immutable_published_version(): void
    {
        $model = $this->makeModel();
        $this->app->make(SaveScoringModelDraft::class)->handle($model, $this->validCriteria());

        $version = $this->app->make(PublishScoringModel::class)->handle($model->refresh());

        $this->assertSame('published', $version->status->value);
        $this->assertSame(1, $version->version_number);
        $this->assertNotNull($version->published_at);
        $this->assertSame(64, strlen((string) $version->content_hash));
        $this->assertSame($version->id, $model->fresh()->current_published_version_id);
        $this->assertNull($model->fresh()->draftVersion());

        $this->expectException(VersionStateException::class);
        $version->update(['criteria' => []]);
    }

    public function test_publish_throws_no_draft_exception_when_no_draft_exists(): void
    {
        $model = $this->makeModel();
        $this->app->make(SaveScoringModelDraft::class)->handle($model, $this->validCriteria());
        $this->app->make(PublishScoringModel::class)->handle($model->refresh()); // consumes draft

        $this->expectException(NoDraftException::class);
        $this->app->make(PublishScoringModel::class)->handle($model->refresh());
    }

    public function test_publish_throws_no_criteria_exception_when_draft_is_empty(): void
    {
        $model = $this->makeModel(); // draft has criteria = []

        $this->expectException(NoCriteriaException::class);
        $this->app->make(PublishScoringModel::class)->handle($model);
    }

    public function test_identical_republish_is_idempotent(): void
    {
        $model = $this->makeModel();
        $this->app->make(SaveScoringModelDraft::class)->handle($model, $this->validCriteria());
        $v1 = $this->app->make(PublishScoringModel::class)->handle($model->refresh());

        // fork same content, republish → same version row
        $this->app->make(ForkScoringModelDraft::class)->handle($model->refresh(), $v1->id);
        $v2 = $this->app->make(PublishScoringModel::class)->handle($model->refresh());

        $this->assertSame($v1->id, $v2->id);
        $this->assertDatabaseCount('scoring_model_versions', 1);
    }

    public function test_fork_then_different_criteria_publish_creates_version_2(): void
    {
        $model = $this->makeModel();
        $this->app->make(SaveScoringModelDraft::class)->handle($model, $this->validCriteria());
        $v1 = $this->app->make(PublishScoringModel::class)->handle($model->refresh());

        $this->app->make(ForkScoringModelDraft::class)->handle($model->refresh(), $v1->id);

        $different = [['criterion_id' => 'c99', 'label' => 'Changed', 'max_points' => 50, 'descriptors' => null]];
        $this->app->make(SaveScoringModelDraft::class)->handle($model->refresh(), $different);

        $v2 = $this->app->make(PublishScoringModel::class)->handle($model->refresh());

        $this->assertSame(2, $v2->version_number);
        $this->assertNotSame($v1->id, $v2->id);
        $this->assertNotNull(ScoringModelVersion::find($v1->id));
        $this->assertDatabaseCount('scoring_model_versions', 2);
    }

    // ── ForkScoringModelDraft ──────────────────────────────────────────

    public function test_fork_creates_new_draft_seeded_from_published_version(): void
    {
        $model = $this->makeModel();
        $this->app->make(SaveScoringModelDraft::class)->handle($model, $this->validCriteria());
        $published = $this->app->make(PublishScoringModel::class)->handle($model->refresh());

        $draft = $this->app->make(ForkScoringModelDraft::class)->handle($model->refresh(), $published->id);

        $this->assertSame('draft', $draft->status->value);
        $this->assertCount(2, $draft->criteria);
        $this->assertSame('Innovation', $draft->criteria[0]['label']);
        $this->assertSame(2, ScoringModelVersion::where('scoring_model_id', $model->id)->count());
    }

    public function test_fork_with_non_published_version_throws(): void
    {
        $model = $this->makeModel();
        $draft = $model->draftVersion();

        $this->expectException(ModelNotFoundException::class);
        $this->app->make(ForkScoringModelDraft::class)->handle($model, $draft->id);
    }

    public function test_fork_with_existing_draft_returns_existing_draft(): void
    {
        $model = $this->makeModel();
        $this->app->make(SaveScoringModelDraft::class)->handle($model, $this->validCriteria());
        $published = $this->app->make(PublishScoringModel::class)->handle($model->refresh());

        $draft1 = $this->app->make(ForkScoringModelDraft::class)->handle($model->refresh(), $published->id);
        $draft2 = $this->app->make(ForkScoringModelDraft::class)->handle($model->refresh(), $published->id);

        $this->assertSame($draft1->id, $draft2->id);
        $this->assertSame(1, ScoringModelVersion::where('scoring_model_id', $model->id)->where('status', 'draft')->count());
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Assessments;

use App\Modules\Assessments\Domain\Models\ScoringModel;
use App\Modules\Assessments\Domain\Models\ScoringModelVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ScoringModelSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_scoring_model_can_be_created_without_a_published_version(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);

        $model = ScoringModel::create(['program_id' => 'prog-01', 'name' => 'Evaluation']);

        $this->assertNull($model->current_published_version_id);
        $this->assertDatabaseHas('scoring_models', ['id' => $model->id, 'name' => 'Evaluation']);
    }

    public function test_scoring_model_version_draft_can_have_null_content_hash(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);

        $model = ScoringModel::create(['program_id' => 'prog-01', 'name' => 'Evaluation']);
        $version = ScoringModelVersion::create([
            'scoring_model_id' => $model->id,
            'status' => 'draft',
            'version_number' => 0,
            'criteria' => [],
        ]);

        $this->assertNull($version->content_hash);
        $this->assertSame([], $version->criteria);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Assessments;

use App\Modules\Assessments\Domain\Models\ScoringModel;
use App\Modules\Assessments\Domain\Models\ScoringModelVersion;
use App\Modules\Assessments\Http\Resources\ScoringModelResource;
use App\Modules\Assessments\Http\Resources\ScoringModelVersionResource;
use App\Modules\Programs\Domain\Models\Program;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

final class ScoringModelResourceTest extends TestCase
{
    use RefreshDatabase;

    private function criteria(): array
    {
        return [
            ['criterion_id' => 'c1', 'label' => 'Innovation', 'max_points' => 30, 'descriptors' => null],
        ];
    }

    public function test_version_resource_emits_version_id_and_model_id(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $program = Program::factory()->create();
        $model = ScoringModel::create(['program_id' => $program->id, 'name' => 'Eval']);
        $draft = ScoringModelVersion::create(['scoring_model_id' => $model->id, 'criteria' => $this->criteria()]);

        $out = (new ScoringModelVersionResource($draft))->toArray(Request::create('/'));

        $this->assertSame($draft->id, $out['version_id']);
        $this->assertSame($model->id, $out['model_id']);
        $this->assertSame(0, $out['version']);
        $this->assertSame('draft', $out['status']);
        $this->assertSame($this->criteria(), $out['criteria']);
        $this->assertNull($out['published_at']);
        $this->assertStringContainsString('T', $out['created_at']);
        $this->assertArrayNotHasKey('id', $out);
        $this->assertArrayNotHasKey('form_id', $out);
    }

    public function test_model_resource_emits_model_id_program_id_and_derived_fields(): void
    {
        [$user, $org] = $this->bootUserWithOrg();
        $this->actingAsTenant($user, $org);
        $program = Program::factory()->create();
        $model = ScoringModel::create(['program_id' => $program->id, 'name' => 'Eval']);

        $v3 = ScoringModelVersion::create([
            'scoring_model_id' => $model->id, 'status' => 'published',
            'version_number' => 3, 'content_hash' => str_repeat('c', 64),
            'criteria' => [], 'published_at' => now(),
        ]);
        $v1 = ScoringModelVersion::create([
            'scoring_model_id' => $model->id, 'status' => 'published',
            'version_number' => 1, 'content_hash' => str_repeat('a', 64),
            'criteria' => [], 'published_at' => now(),
        ]);
        $v2 = ScoringModelVersion::create([
            'scoring_model_id' => $model->id, 'status' => 'published',
            'version_number' => 2, 'content_hash' => str_repeat('b', 64),
            'criteria' => [], 'published_at' => now(),
        ]);
        $draft = ScoringModelVersion::create(['scoring_model_id' => $model->id, 'criteria' => []]);

        $out = (new ScoringModelResource($model->load('versions')))->toArray(Request::create('/'));

        $this->assertSame($model->id, $out['model_id']);
        $this->assertSame($program->id, $out['program_id']);
        $this->assertSame('Eval', $out['name']);
        $this->assertSame(3, $out['latest_version']);
        $this->assertSame(
            [$v1->id, $v2->id, $v3->id],
            $out['published_version_ids'],
            'published_version_ids must be ordered by version_number ascending'
        );
        $this->assertSame($draft->id, $out['current_draft_version_id']);
        $this->assertStringContainsString('T', $out['created_at']);
        $this->assertArrayNotHasKey('id', $out);
    }
}

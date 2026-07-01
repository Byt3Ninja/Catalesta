<?php

declare(strict_types=1);

namespace App\Modules\Assessments\Application;

use App\Modules\Assessments\Domain\Models\ScoringModel;
use App\Modules\Assessments\Domain\Models\ScoringModelVersion;
use App\Modules\Programs\Domain\Models\Program;
use Illuminate\Support\Facades\DB;

/**
 * Creates a program-scoped scoring model and seeds its single empty draft
 * version. The empty draft is valid until publish (criteria can be added
 * incrementally via SaveScoringModelDraft).
 */
final class CreateScoringModel
{
    public function handle(Program $program, string $name): ScoringModel
    {
        return DB::transaction(function () use ($program, $name): ScoringModel {
            $model = ScoringModel::create(['program_id' => $program->id, 'name' => $name]);
            ScoringModelVersion::create([
                'scoring_model_id' => $model->id,
                'status' => 'draft',
                'version_number' => 0,
                'criteria' => [],
            ]);

            return $model->load('versions');
        });
    }
}

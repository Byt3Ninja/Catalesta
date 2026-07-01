<?php

declare(strict_types=1);

namespace App\Modules\Assessments\Application;

use App\Modules\Assessments\Domain\Models\ScoringModel;
use App\Modules\Assessments\Domain\Models\ScoringModelVersion;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class ForkScoringModelDraft
{
    /**
     * @throws ModelNotFoundException when $fromVersionId is not a published
     *                                version of $model (→ 404)
     */
    public function handle(ScoringModel $model, string $fromVersionId): ScoringModelVersion
    {
        // Validate source FIRST — even when a draft exists — so invalid
        // version ids still return 404 rather than silently succeeding.
        /** @var ScoringModelVersion $source */
        $source = ScoringModelVersion::query()
            ->where('scoring_model_id', $model->id)
            ->where('status', 'published')
            ->findOrFail($fromVersionId);

        // Invariant: at most one draft per scoring model. Return existing unchanged.
        /** @var ScoringModelVersion|null $existingDraft */
        $existingDraft = ScoringModelVersion::query()
            ->where('scoring_model_id', $model->id)
            ->where('status', 'draft')
            ->first();

        if ($existingDraft !== null) {
            return $existingDraft;
        }

        return ScoringModelVersion::create([
            'scoring_model_id' => $model->id,
            'criteria' => json_decode(json_encode($source->criteria), true), // deep copy
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Assessments\Application;

use App\Modules\Assessments\Domain\CriteriaValidator;
use App\Modules\Assessments\Domain\Exceptions\InvalidCriteriaException;
use App\Modules\Assessments\Domain\Exceptions\NoDraftException;
use App\Modules\Assessments\Domain\Models\ScoringModel;
use App\Modules\Assessments\Domain\Models\ScoringModelVersion;

final class SaveScoringModelDraft
{
    public function __construct(private readonly CriteriaValidator $validator) {}

    /**
     * @param  array<int, array<string, mixed>>  $criteria
     *
     * @throws NoDraftException when the model has no draft version
     * @throws InvalidCriteriaException when any criterion is structurally invalid
     */
    public function handle(ScoringModel $model, array $criteria): ScoringModelVersion
    {
        /** @var ScoringModelVersion|null $draft */
        $draft = ScoringModelVersion::query()
            ->where('scoring_model_id', $model->id)
            ->where('status', 'draft')
            ->first();

        if ($draft === null) {
            throw new NoDraftException('This scoring model has no draft version to edit.');
        }

        if ($criteria !== []) {
            $this->validator->validate($criteria);
        }

        $draft->criteria = $criteria;
        $draft->save();

        return $draft;
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Assessments\Application;

use App\Modules\Assessments\Domain\CriteriaValidator;
use App\Modules\Assessments\Domain\Exceptions\NoCriteriaException;
use App\Modules\Assessments\Domain\Exceptions\NoDraftException;
use App\Modules\Assessments\Domain\Models\ScoringModel;
use App\Modules\Assessments\Domain\Models\ScoringModelVersion;
use App\Shared\Audit\AuditAction;
use App\Shared\Audit\AuditLogger;
use App\Shared\Versioning\VersionPublisher;
use Illuminate\Support\Facades\DB;

/**
 * Publishes the scoring model's single draft version as an immutable,
 * content-addressed version. Republishing criteria identical to an existing
 * published version returns that version and discards the redundant draft
 * (idempotent — no duplicate row, no UNIQUE collision).
 */
final class PublishScoringModel
{
    public function __construct(
        private readonly CriteriaValidator $validator,
        private readonly VersionPublisher $publisher,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @throws NoDraftException when there is no draft version (→ 409)
     * @throws NoCriteriaException when the draft has no criteria (→ 422)
     */
    public function handle(ScoringModel $model): ScoringModelVersion
    {
        /** @var ScoringModelVersion|null $draft */
        $draft = ScoringModelVersion::query()
            ->where('scoring_model_id', $model->id)
            ->where('status', 'draft')
            ->first();

        if ($draft === null) {
            throw new NoDraftException('This scoring model has no draft to publish.');
        }

        if ($draft->criteria === []) {
            throw new NoCriteriaException('A scoring model must have at least one criterion before it can be published.');
        }

        $hash = hash('sha256', $this->validator->canonicalJson($draft->criteria));

        $version = DB::transaction(function () use ($model, $draft, $hash): ScoringModelVersion {
            /** @var ScoringModelVersion|null $existing */
            $existing = ScoringModelVersion::query()
                ->where('scoring_model_id', $model->id)
                ->where('status', 'published')
                ->where('content_hash', $hash)
                ->first();

            if ($existing !== null) {
                $draft->delete();  // discard redundant draft (avoids UNIQUE collision)
                $model->update(['current_published_version_id' => $existing->id]);

                return $existing;
            }

            $draft->content_hash = $hash; // still draft — mutation allowed
            $draft->save();
            $this->publisher->publish($draft); // sets version_number, Published, published_at
            $model->update(['current_published_version_id' => $draft->id]);

            return $draft->refresh();
        });

        $this->audit->record(
            AuditAction::ScoringModelPublished->value,
            'scoring_model_version',
            $version->id,
            [],
            ['content_hash' => $hash, 'version_number' => $version->version_number],
        );

        return $version;
    }
}

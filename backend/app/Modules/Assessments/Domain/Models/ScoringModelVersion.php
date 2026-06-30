<?php

declare(strict_types=1);

namespace App\Modules\Assessments\Domain\Models;

use App\Shared\Tenancy\BelongsToTenant;
use App\Shared\Versioning\ImmutableWhenPublished;
use App\Shared\Versioning\Versionable;
use App\Shared\Versioning\VersionStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A published, immutable scoring-model version. Once published the row is
 * frozen (ImmutableWhenPublished); each published version keeps its ULID
 * resolvable for historical binding (ADR-0012 immutability invariant).
 */
final class ScoringModelVersion extends Model implements Versionable
{
    use BelongsToTenant;
    use HasUlids;
    use ImmutableWhenPublished;

    protected $fillable = [
        'scoring_model_id', 'version_number', 'status',
        'content_hash', 'criteria', 'published_at',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
        'version_number' => 0,
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected $casts = [
        'status' => VersionStatus::class,
        'criteria' => 'array',
        'version_number' => 'integer',
        'published_at' => 'datetime',
    ];

    public function versionParentColumn(): string
    {
        return 'scoring_model_id';
    }

    public function validateForPublish(): void
    {
        // Structural criterion validation happens in PublishScoringModel
        // (via CriteriaValidator) before VersionPublisher::publish is called;
        // nothing further to assert here.
    }

    /** @return BelongsTo<ScoringModel, $this> */
    public function scoringModel(): BelongsTo
    {
        return $this->belongsTo(ScoringModel::class);
    }
}

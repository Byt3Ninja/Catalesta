<?php

declare(strict_types=1);

namespace App\Modules\Stages\Domain\Models;

use App\Shared\Tenancy\BelongsToTenant;
use App\Shared\Versioning\ImmutableWhenPublished;
use App\Shared\Versioning\Versionable;
use App\Shared\Versioning\VersionStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An immutable, content-addressed snapshot of a program's published stage graph (ADR-0011). */
final class StagePipelineVersion extends Model implements Versionable
{
    use BelongsToTenant;
    use HasUlids;
    use ImmutableWhenPublished;

    protected $fillable = ['stage_pipeline_id', 'version_number', 'status', 'content_hash', 'snapshot', 'published_at'];

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'draft', 'version_number' => 0];

    /** @return array<string, string|class-string> */
    protected $casts = [
        'status' => VersionStatus::class,
        'snapshot' => 'array',
        'version_number' => 'integer',
        'published_at' => 'datetime',
    ];

    public function versionParentColumn(): string
    {
        return 'stage_pipeline_id';
    }

    public function validateForPublish(): void
    {
        // Snapshot is validated by PublishStagePipeline before the version is created.
    }

    /** @return BelongsTo<StagePipeline, $this> */
    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(StagePipeline::class, 'stage_pipeline_id');
    }
}

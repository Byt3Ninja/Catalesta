<?php

declare(strict_types=1);

namespace App\Modules\Stages\Domain\Models;

use App\Shared\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One per program: the version parent for that program's immutable stage-graph snapshots. */
final class StagePipeline extends Model
{
    use BelongsToTenant;
    use HasUlids;

    protected $fillable = ['program_id', 'name', 'current_published_version_id'];

    /** @return HasMany<StagePipelineVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(StagePipelineVersion::class);
    }

    /** @return HasMany<StagePipelineVersion, $this> */
    public function publishedVersions(): HasMany
    {
        return $this->hasMany(StagePipelineVersion::class)->where('status', 'published')->orderBy('version_number');
    }
}

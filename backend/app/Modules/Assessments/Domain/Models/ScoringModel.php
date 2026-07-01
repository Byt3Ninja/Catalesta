<?php

declare(strict_types=1);

namespace App\Modules\Assessments\Domain\Models;

use App\Shared\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A scoring model — the version parent, program-scoped and org-scoped.
 * Immutable, content-addressed versions live in scoring_model_versions.
 */
final class ScoringModel extends Model
{
    use BelongsToTenant;
    use HasUlids;

    protected $fillable = ['program_id', 'name', 'current_published_version_id'];

    /** @return HasMany<ScoringModelVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(ScoringModelVersion::class);
    }

    /** @return HasMany<ScoringModelVersion, $this> */
    public function publishedVersions(): HasMany
    {
        return $this->hasMany(ScoringModelVersion::class)
            ->where('status', 'published')
            ->orderBy('version_number');
    }

    public function draftVersion(): ?ScoringModelVersion
    {
        return $this->versions()->where('status', 'draft')->first();
    }
}

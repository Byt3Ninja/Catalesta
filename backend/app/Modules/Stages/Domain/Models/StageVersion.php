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

final class StageVersion extends Model implements Versionable
{
    use BelongsToTenant;
    use HasUlids;
    use ImmutableWhenPublished;

    protected $guarded = [];

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
        'config' => 'array',
        'version_number' => 'integer',
        'published_at' => 'datetime',
    ];

    public function versionParentColumn(): string
    {
        return 'program_stage_id';
    }

    /**
     * No stage_rules validation yet (added in Task 3.2).
     */
    public function validateForPublish(): void
    {
        // no-op until Task 3.2
    }

    /**
     * @return BelongsTo<ProgramStage, $this>
     */
    public function programStage(): BelongsTo
    {
        return $this->belongsTo(ProgramStage::class);
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Programs\Domain\Models;

use App\Shared\Tenancy\BelongsToTenant;
use App\Shared\Versioning\ImmutableWhenPublished;
use App\Shared\Versioning\Versionable;
use App\Shared\Versioning\VersionStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A published, immutable program version (FR-010/012). `definition` is a snapshot
 * of the program's publishable config taken at publish time. Once published the
 * row is frozen (ImmutableWhenPublished); editing the program and re-publishing
 * creates a new version, and prior version_numbers stay resolvable. Versions are
 * sequenced by version_number (like stage_versions); programs have no
 * content_hash contract — that belongs to forms (Story 1.3).
 */
final class ProgramVersion extends Model implements Versionable
{
    use BelongsToTenant;
    use HasUlids;
    use ImmutableWhenPublished;

    protected $fillable = ['program_id', 'version_number', 'status', 'definition', 'published_at'];

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
        'definition' => 'array',
        'version_number' => 'integer',
        'published_at' => 'datetime',
    ];

    public function versionParentColumn(): string
    {
        return 'program_id';
    }

    public function validateForPublish(): void
    {
        // The program is valid by construction (StoreProgramRequest); the snapshot
        // is taken from already-persisted state, so there is nothing to assert here.
    }

    /**
     * @return BelongsTo<Program, $this>
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }
}

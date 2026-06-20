<?php

declare(strict_types=1);

namespace App\Modules\Forms\Domain\Models;

use App\Shared\Tenancy\BelongsToTenant;
use App\Shared\Versioning\ImmutableWhenPublished;
use App\Shared\Versioning\Versionable;
use App\Shared\Versioning\VersionStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A published, immutable application-form version. content_hash is the
 * content-addressed version id (sha256 of the canonical definition). Once
 * published the row is frozen (ImmutableWhenPublished); editing creates a new
 * version with a new id, and the prior id stays resolvable.
 */
final class FormVersion extends Model implements Versionable
{
    use BelongsToTenant;
    use HasUlids;
    use ImmutableWhenPublished;

    protected $fillable = ['form_id', 'version_number', 'status', 'content_hash', 'definition', 'published_at'];

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
        return 'form_id';
    }

    public function validateForPublish(): void
    {
        // The definition is validated by FormDefinitionValidator before the draft
        // is created (PublishForm); nothing further to assert at publish time.
    }

    /**
     * @return BelongsTo<Form, $this>
     */
    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }
}

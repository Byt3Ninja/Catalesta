<?php

declare(strict_types=1);

namespace App\Modules\Forms\Domain\Models;

use App\Shared\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An application form — the version parent. Org-scoped; its published, immutable,
 * content-addressed versions live in form_versions.
 */
final class Form extends Model
{
    use BelongsToTenant;
    use HasUlids;

    protected $fillable = ['program_id', 'name', 'current_published_version_id'];

    /**
     * @return HasMany<FormVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(FormVersion::class);
    }
}

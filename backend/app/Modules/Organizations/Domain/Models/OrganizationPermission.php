<?php

declare(strict_types=1);

namespace App\Modules\Organizations\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Global permission catalog — not tenant-scoped.
 * No organization_id; permissions are platform-wide definitions.
 *
 * @property string $key
 * @property string|null $description
 */
final class OrganizationPermission extends Model
{
    use HasUlids;

    protected $fillable = ['key', 'description'];

    /** @return BelongsToMany<OrganizationRole, $this, Pivot, 'pivot'> */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            OrganizationRole::class,
            'role_permission_assignments',
            'organization_permission_id',
            'organization_role_id',
        );
    }
}

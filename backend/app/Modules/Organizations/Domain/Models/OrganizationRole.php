<?php

declare(strict_types=1);

namespace App\Modules\Organizations\Domain\Models;

use App\Shared\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A role scoped to an organization.
 * Uses BelongsToTenant to auto-scope queries by TenantContext.
 *
 * @property string $organization_id
 * @property string $key
 * @property string $name
 * @property bool $is_system
 */
final class OrganizationRole extends Model
{
    use BelongsToTenant;
    use HasUlids;

    protected $fillable = ['key', 'name', 'is_system'];

    /**
     * @return array<string, string>
     */
    protected $casts = [
        'is_system' => 'boolean',
    ];

    /** @return BelongsToMany<OrganizationPermission, $this, RolePermissionAssignment, 'pivot'> */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(
            OrganizationPermission::class,
            'role_permission_assignments',
            'organization_role_id',
            'organization_permission_id',
        )->using(RolePermissionAssignment::class);
    }

    /** @return BelongsToMany<OrganizationMembership, $this, OrganizationMembershipRole, 'pivot'> */
    public function memberships(): BelongsToMany
    {
        return $this->belongsToMany(
            OrganizationMembership::class,
            'organization_membership_roles',
            'organization_role_id',
            'organization_membership_id',
        )->using(OrganizationMembershipRole::class);
    }
}

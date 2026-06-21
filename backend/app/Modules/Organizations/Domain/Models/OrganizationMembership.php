<?php

declare(strict_types=1);

namespace App\Modules\Organizations\Domain\Models;

use App\Modules\Identity\Domain\Models\Account;
use App\Shared\Tenancy\BelongsToTenant;
use App\Shared\Tenancy\Contracts\TenantMembership;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A user's membership within an organization.
 *
 * Implements TenantMembership so ResolveTenant can pass it into TenantContext.
 * Uses BelongsToTenant so membership queries are auto-scoped to the active tenant.
 *
 * Global-scope note for effectivePermissionKeys():
 * The method uses TenantContext::runAsSystem() to traverse the role graph
 * without relying on TenantContext being set at call-time. This makes it safe
 * to call from ResolveTenant (before the context is populated) and from unit
 * tests without setting up a tenant context.
 *
 * @property string $organization_id
 * @property string $account_id
 * @property string $status
 */
final class OrganizationMembership extends Model implements TenantMembership
{
    use BelongsToTenant;
    use HasUlids;

    protected $fillable = ['account_id', 'status'];

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /** @return BelongsToMany<OrganizationRole, $this, OrganizationMembershipRole, 'pivot'> */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            OrganizationRole::class,
            'organization_membership_roles',
            'organization_membership_id',
            'organization_role_id',
        )->using(OrganizationMembershipRole::class);
    }

    /**
     * Returns the organization ID this membership belongs to.
     * Satisfies TenantMembership contract.
     */
    public function organizationId(): string
    {
        return (string) $this->organization_id;
    }

    /**
     * Returns distinct permission keys from all roles assigned to this membership.
     *
     * Uses TenantContext::runAsSystem() to traverse OrganizationRole regardless
     * of whether TenantContext has been set (e.g. called from ResolveTenant
     * before the context is populated, or from unit tests).
     *
     * @return array<int,string>
     */
    public function effectivePermissionKeys(): array
    {
        /** @var array<int, OrganizationRole> $roles */
        $roles = app(TenantContext::class)->runAsSystem(fn () => OrganizationRole::query()
            ->whereHas('memberships', function ($q): void {
                $q->where('organization_memberships.id', $this->id);
            })
            ->with(['permissions' => function ($q): void {
                $q->select('organization_permissions.id', 'organization_permissions.key');
            }])
            ->get()
            ->all());

        $keys = [];
        foreach ($roles as $role) {
            /** @var array<int, OrganizationPermission> $permissions */
            $permissions = $role->permissions->all();
            foreach ($permissions as $permission) {
                $keys[] = $permission->key;
            }
        }

        /** @var array<int,string> $unique */
        $unique = array_values(array_unique($keys));

        return $unique;
    }
}

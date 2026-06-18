<?php

declare(strict_types=1);

namespace App\Modules\Organizations\Policies;

use App\Modules\Identity\Domain\Models\ExternalUser;
use App\Modules\Organizations\Domain\Models\Organization;
use App\Shared\Tenancy\TenantContext;

/**
 * Authorization policy for the Organization model.
 *
 * Judgment call — view():
 *   We return `true` unconditionally here. The reason is that data-layer
 *   tenant isolation (BelongsToTenant global scope + ResolveTenant middleware)
 *   already ensures that a request can only reach an Organization record that
 *   belongs to the resolved tenant. If no tenant is resolved (unauthenticated
 *   or wrong header), ResolveTenant aborts before the policy is consulted.
 *   Requiring an additional `organizations.view` permission would be redundant
 *   and would prevent self-service member dashboards from functioning without
 *   an explicit role assignment. If a future requirement mandates a fine-grained
 *   read permission, change this line — tests will catch any regression.
 *
 * update(): delegates to the `organizations.manage` permission key via
 *   TenantContext::can(), which automatically allows platform admins.
 */
final class OrganizationPolicy
{
    /**
     * Determine whether the user can view the organization.
     *
     * Access to the resolved tenant's own record is permitted to any authenticated
     * member. Data-layer scoping prevents cross-tenant access; no extra permission
     * key is needed here.
     */
    public function view(ExternalUser $user, Organization $org): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the organization.
     *
     * Requires the `organizations.manage` permission on the resolved tenant.
     * Platform admins bypass this check via TenantContext.
     */
    public function update(ExternalUser $user, Organization $org): bool
    {
        return app(TenantContext::class)->can('organizations.manage');
    }
}

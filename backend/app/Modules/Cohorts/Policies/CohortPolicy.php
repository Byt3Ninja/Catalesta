<?php

declare(strict_types=1);

namespace App\Modules\Cohorts\Policies;

use App\Modules\Cohorts\Domain\Models\Cohort;
use App\Modules\Identity\Domain\Models\Account;
use App\Shared\Tenancy\TenantContext;

/**
 * Authorization policy for the Cohort model.
 *
 * viewAny/view: any authenticated member of the resolved tenant may read cohorts.
 *   BelongsToTenant global scope + ResolveTenant middleware ensure only the
 *   correct tenant's records are visible; no extra permission key is needed.
 *
 * create/update: requires `cohorts.manage` permission via TenantContext.
 */
final class CohortPolicy
{
    /**
     * Any authenticated tenant member may list cohorts.
     */
    public function viewAny(Account $user): bool
    {
        return true;
    }

    /**
     * Any authenticated tenant member may view a single cohort.
     * BelongsToTenant scope prevents cross-tenant access at the data layer.
     */
    public function view(Account $user, Cohort $cohort): bool
    {
        return true;
    }

    /**
     * Creating a cohort requires the `cohorts.manage` permission.
     */
    public function create(Account $user): bool
    {
        return app(TenantContext::class)->can('cohorts.manage');
    }

    /**
     * Updating a cohort requires the `cohorts.manage` permission.
     */
    public function update(Account $user, Cohort $cohort): bool
    {
        return app(TenantContext::class)->can('cohorts.manage');
    }

    /**
     * Opening a cohort requires the `cohorts.manage` permission.
     */
    public function open(Account $user, Cohort $cohort): bool
    {
        return app(TenantContext::class)->can('cohorts.manage');
    }

    /**
     * Binding a form to a cohort requires the `cohorts.manage` permission.
     */
    public function bindForm(Account $user, Cohort $cohort): bool
    {
        return app(TenantContext::class)->can('cohorts.manage');
    }
}

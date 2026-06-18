<?php

declare(strict_types=1);

namespace App\Modules\Programs\Policies;

use App\Modules\Identity\Domain\Models\ExternalUser;
use App\Modules\Programs\Domain\Models\Program;
use App\Shared\Tenancy\TenantContext;

/**
 * Authorization policy for the Program model.
 *
 * viewAny/view: any authenticated member of the resolved tenant may read programs.
 *   BelongsToTenant global scope + ResolveTenant middleware already ensure only
 *   the correct tenant's records are visible; no extra permission key is needed.
 *
 * create/update: requires `programs.manage` permission via TenantContext.
 * publish: requires `programs.publish` permission via TenantContext.
 */
final class ProgramPolicy
{
    /**
     * Any authenticated tenant member may list programs.
     */
    public function viewAny(ExternalUser $user): bool
    {
        return true;
    }

    /**
     * Any authenticated tenant member may view a single program.
     * BelongsToTenant scope prevents cross-tenant access at the data layer.
     */
    public function view(ExternalUser $user, Program $program): bool
    {
        return true;
    }

    /**
     * Creating a program requires the `programs.manage` permission.
     */
    public function create(ExternalUser $user): bool
    {
        return app(TenantContext::class)->can('programs.manage');
    }

    /**
     * Updating a program requires the `programs.manage` permission.
     * Programs remain editable after publish — not immutable.
     */
    public function update(ExternalUser $user, Program $program): bool
    {
        return app(TenantContext::class)->can('programs.manage');
    }

    /**
     * Publishing a program requires the `programs.publish` permission.
     */
    public function publish(ExternalUser $user, Program $program): bool
    {
        return app(TenantContext::class)->can('programs.publish');
    }
}

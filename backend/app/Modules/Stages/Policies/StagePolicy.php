<?php

declare(strict_types=1);

namespace App\Modules\Stages\Policies;

use App\Modules\Identity\Domain\Models\ExternalUser;
use App\Modules\Stages\Domain\Models\ProgramStage;
use App\Shared\Tenancy\TenantContext;

/**
 * Authorization policy for ProgramStage.
 *
 * viewAny/view: any authenticated tenant member may read stages.
 *   BelongsToTenant global scope + ResolveTenant middleware ensure only the
 *   correct tenant's records are visible; no extra permission key required.
 *
 * create/update/publish/reorder: require `stages.manage` permission.
 */
final class StagePolicy
{
    /**
     * Any authenticated tenant member may list stages.
     */
    public function viewAny(ExternalUser $user): bool
    {
        return true;
    }

    /**
     * Any authenticated tenant member may view a single stage.
     */
    public function view(ExternalUser $user, ProgramStage $stage): bool
    {
        return true;
    }

    /**
     * Creating a stage requires stages.manage.
     */
    public function create(ExternalUser $user): bool
    {
        return app(TenantContext::class)->can('stages.manage');
    }

    /**
     * Updating a stage requires stages.manage.
     */
    public function update(ExternalUser $user, ProgramStage $stage): bool
    {
        return app(TenantContext::class)->can('stages.manage');
    }

    /**
     * Publishing a stage version requires stages.manage.
     */
    public function publish(ExternalUser $user, ProgramStage $stage): bool
    {
        return app(TenantContext::class)->can('stages.manage');
    }

    /**
     * Reordering stages requires stages.manage.
     */
    public function reorder(ExternalUser $user): bool
    {
        return app(TenantContext::class)->can('stages.manage');
    }
}

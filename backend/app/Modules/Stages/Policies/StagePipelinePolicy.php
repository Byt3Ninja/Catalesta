<?php

declare(strict_types=1);

namespace App\Modules\Stages\Policies;

use App\Modules\Identity\Domain\Models\Account;
use App\Modules\Stages\Domain\Models\StagePipeline;
use App\Shared\Tenancy\TenantContext;

/**
 * Authorization policy for the StagePipeline model.
 *
 * viewAny/view: any authenticated tenant member may read pipelines and their versions.
 *   BelongsToTenant global scope + ResolveTenant middleware ensure only the correct
 *   tenant's records are visible; no extra permission key is needed.
 *
 * publish: requires `stages.manage` permission via TenantContext.
 *   Called with StagePipeline::class (no instance) so the method has no model parameter.
 */
final class StagePipelinePolicy
{
    /**
     * Any authenticated tenant member may list pipelines.
     */
    public function viewAny(Account $user): bool
    {
        return true;
    }

    /**
     * Any authenticated tenant member may view a single pipeline.
     * BelongsToTenant scope prevents cross-tenant access at the data layer.
     */
    public function view(Account $user, StagePipeline $pipeline): bool
    {
        return true;
    }

    /**
     * Snapshotting/publishing a pipeline requires the `stages.manage` permission.
     */
    public function publish(Account $user): bool
    {
        return app(TenantContext::class)->can('stages.manage');
    }
}

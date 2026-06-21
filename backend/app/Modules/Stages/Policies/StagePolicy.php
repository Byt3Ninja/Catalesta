<?php

declare(strict_types=1);

namespace App\Modules\Stages\Policies;

use App\Modules\Identity\Domain\Models\Account;
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
    public function viewAny(Account $user): bool
    {
        return true;
    }

    /**
     * Any authenticated tenant member may view a single stage.
     */
    public function view(Account $user, ProgramStage $stage): bool
    {
        return true;
    }

    /**
     * Creating a stage requires stages.manage.
     */
    public function create(Account $user): bool
    {
        return app(TenantContext::class)->can('stages.manage');
    }

    /**
     * Updating a stage requires stages.manage.
     */
    public function update(Account $user, ProgramStage $stage): bool
    {
        return app(TenantContext::class)->can('stages.manage');
    }

    /**
     * Publishing a stage version requires stages.manage.
     */
    public function publish(Account $user, ProgramStage $stage): bool
    {
        return app(TenantContext::class)->can('stages.manage');
    }

    /**
     * Reordering stages requires stages.manage.
     */
    public function reorder(Account $user): bool
    {
        return app(TenantContext::class)->can('stages.manage');
    }

    /**
     * Managing stage dependencies (add/view/remove) requires stages.manage.
     */
    public function manageDependencies(Account $user, ProgramStage $stage): bool
    {
        return app(TenantContext::class)->can('stages.manage');
    }
}

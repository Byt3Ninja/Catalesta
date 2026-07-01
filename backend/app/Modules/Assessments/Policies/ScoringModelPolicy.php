<?php

declare(strict_types=1);

namespace App\Modules\Assessments\Policies;

use App\Modules\Assessments\Domain\Models\ScoringModel;
use App\Modules\Identity\Domain\Models\Account;
use App\Shared\Tenancy\TenantContext;

/**
 * Authorization policy for ScoringModel.
 *
 * viewAny/view: any authenticated tenant member may read scoring models.
 *   BelongsToTenant global scope + ResolveTenant middleware ensure only the
 *   correct tenant's records are visible; no extra permission required.
 *
 * create/update/publish: require `assessments.manage` permission.
 * Deny-by-default for everything else.
 */
final class ScoringModelPolicy
{
    public function viewAny(Account $user): bool
    {
        return true;
    }

    public function view(Account $user, ScoringModel $model): bool
    {
        return true;
    }

    public function create(Account $user): bool
    {
        return app(TenantContext::class)->can('assessments.manage');
    }

    public function update(Account $user, ScoringModel $model): bool
    {
        return app(TenantContext::class)->can('assessments.manage');
    }

    public function publish(Account $user, ScoringModel $model): bool
    {
        return app(TenantContext::class)->can('assessments.manage');
    }
}

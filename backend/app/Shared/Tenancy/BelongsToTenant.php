<?php

declare(strict_types=1);

namespace App\Shared\Tenancy;

use App\Shared\Tenancy\Exceptions\TenantContextMissingException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $organization_id
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder): void {
            $ctx = app(TenantContext::class);
            if ($ctx->has()) {
                $builder->where($builder->getModel()->getTable().'.organization_id', $ctx->organizationId());

                return;
            }
            if ($ctx->isSystem()) {
                return; // explicit cross-tenant access
            }
            $builder->whereRaw('1 = 0'); // fail-closed: no tenant, not system => no rows
        });

        static::creating(function (Model $model): void {
            $ctx = app(TenantContext::class);
            if ($ctx->has()) {
                $model->setAttribute('organization_id', $ctx->organizationId()); // FORCE from context

                return;
            }
            if (! empty($model->getAttribute('organization_id'))) {
                return; // explicit org set in code (system/bootstrap path)
            }
            throw new TenantContextMissingException(sprintf(
                'Cannot persist %s without a resolved tenant or explicit organization_id.',
                $model::class,
            ));
        });
    }
}

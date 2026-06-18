<?php

declare(strict_types=1);

namespace App\Shared\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder): void {
            $ctx = app(TenantContext::class);
            if ($ctx->has()) {
                $builder->where($builder->getModel()->getTable().'.organization_id', $ctx->organizationId());
            }
        });
        static::creating(function (Model $model): void {
            $ctx = app(TenantContext::class);
            if ($ctx->has() && empty($model->organization_id)) {
                $model->organization_id = $ctx->organizationId();
            }
        });
    }
}

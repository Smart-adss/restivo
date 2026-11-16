<?php

namespace App\Models\Concerns;

use App\Support\Tenancy\MissingTenantContext;
use App\Support\Tenancy\TenantContext;
use App\Support\Tenancy\TenantMismatch;
use Illuminate\Database\Eloquent\Builder;

trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder): void {
            $tenantId = app(TenantContext::class)->id();

            if ($tenantId === null) {
                throw new MissingTenantContext(static::class);
            }

            $builder->where($builder->getModel()->getTable().'.tenant_id', $tenantId);
        });

        static::creating(function (self $model): void {
            $tenantId = app(TenantContext::class)->id();

            if ($tenantId === null) {
                if ($model->tenant_id === null && $model->allowsMissingTenant()) {
                    return;
                }

                throw new MissingTenantContext(static::class);
            }

            if ($model->tenant_id !== null && (int) $model->tenant_id !== $tenantId) {
                throw new TenantMismatch;
            }

            $model->tenant_id = $tenantId;
        });
    }

    public function allowsMissingTenant(): bool
    {
        return false;
    }
}

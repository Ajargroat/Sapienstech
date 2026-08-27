<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Add to any Eloquent model whose table has a tenant_id column.
 * Automatically scopes every query to the current tenant and stamps
 * tenant_id on create -- this is Phase 6 of the roadmap ("never do
 * Student::find($id)"), included here since it's the natural partner
 * to the middleware: the middleware resolves the tenant, this trait
 * is what actually enforces it at the data layer.
 */
trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder) {
            if ($tenant = tenant()) {
                $builder->where(
                    $builder->getModel()->getTable().'.tenant_id',
                    $tenant->id
                );
            }
        });

        static::creating(function ($model) {
            if (! $model->tenant_id && $tenant = tenant()) {
                $model->tenant_id = $tenant->id;
            }
        });
    }
}

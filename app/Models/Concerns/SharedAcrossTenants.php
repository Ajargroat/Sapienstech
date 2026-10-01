<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Add to any Eloquent model whose table is a *shared pool*: rows may be
 * deliberately tenant-less (`tenant_id = NULL`) and every tenant reads those,
 * while ordinary tenant-stamped rows keep behaving exactly like
 * BelongsToTenant (invisible across tenants).
 *
 * Used by Question/Answer: both concours tenants build exams from one imported
 * bank, so the pool is shared on purpose. Deliberately NO creating-stamp — a
 * pooled row is NULL by choice, and writers that mean a tenant (tests, the
 * importer when it ever needs one) set tenant_id explicitly.
 */
trait SharedAcrossTenants
{
    protected static function bootSharedAcrossTenants(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder) {
            if ($tenant = tenant()) {
                $table = $builder->getModel()->getTable();

                $builder->where(fn (Builder $query) => $query
                    ->whereNull("{$table}.tenant_id")
                    ->orWhere("{$table}.tenant_id", $tenant->id));
            }
        });
    }
}

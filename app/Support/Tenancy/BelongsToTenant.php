<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Applies row-level tenant isolation to a model:
 *  - a global scope constraining every query to the current tenant, and
 *  - auto-filling tenant_id on create.
 *
 * Every tenant-owned model uses this trait (enforced by tests/Arch + isolation tests).
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        $manager = app(TenantManager::class);

        static::addGlobalScope('tenant', function (Builder $builder) use ($manager): void {
            if ($manager->has()) {
                $builder->where($builder->getModel()->getTable().'.tenant_id', $manager->id());
            }
        });

        static::creating(function (Model $model) use ($manager): void {
            if ($manager->has() && empty($model->getAttribute('tenant_id'))) {
                $model->setAttribute('tenant_id', $manager->id());
            }
        });
    }
}

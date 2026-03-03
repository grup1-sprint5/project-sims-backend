<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    /**
     * Apply the tenant scope to a given Eloquent query builder.
     *
     * SuperAdmin users see all records (no filter applied).
     * Other authenticated users only see records belonging to their tenant.
     * Unauthenticated contexts (e.g. console, queue) are not filtered.
     */
    public function apply(Builder $builder, Model $model): void
    {
        $user = auth()->user();

        if ($user && !$user->isSuperAdmin()) {
            $builder->where($model->getTable() . '.tenant_id', $user->tenant_id);
        }
    }
}

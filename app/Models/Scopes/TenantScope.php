<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    /**
     * With schema-per-tenant isolation (stancl/tenancy), the PostgreSQL schema
     * already restricts every query to the current tenant's data.
     * No additional SQL filter on `tenant_id` is necessary.
     */
    public function apply(Builder $builder, Model $model): void
    {
        // Intentionally no-op (schema isolation already scopes queries).
    }
}


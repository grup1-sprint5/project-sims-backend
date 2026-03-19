<?php

namespace App\Models\Traits;

use App\Models\Scopes\TenantScope;

/**
 * Trait BelongsToTenant
 *
 * Registers the (now no-op) TenantScope for backward compatibility and
 * auto-populates `tenant_id` from the active stancl/tenancy context on create.
 * The actual tenant isolation is handled by PostgreSQL schema separation.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        // The scope is kept for backward compatibility but does nothing
        // (schema isolation already restricts all queries to the tenant).
        static::addGlobalScope(new TenantScope());

        // Auto-assign tenant_id on creating from the active tenancy context.
        static::creating(function ($model) {
            if (empty($model->tenant_id) && tenancy()->initialized) {
                $model->tenant_id = tenant()->id;
            }
        });
    }
}


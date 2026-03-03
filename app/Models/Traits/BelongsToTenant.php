<?php

namespace App\Models\Traits;

use App\Models\Scopes\TenantScope;

/**
 * Trait BelongsToTenant
 *
 * Automatically scopes queries to the authenticated user's tenant.
 * SuperAdmin users bypass the scope and see all records.
 *
 * Usage: Add `use BelongsToTenant;` to any model that has a `tenant_id` column.
 *
 * Also auto-assigns `tenant_id` on creating if the user is not a SuperAdmin.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope());

        // Auto-assign tenant_id on creating if not already set
        static::creating(function ($model) {
            $user = auth()->user();
            if ($user && !$user->isSuperAdmin() && empty($model->tenant_id)) {
                $model->tenant_id = $user->tenant_id;
            }
        });
    }
}

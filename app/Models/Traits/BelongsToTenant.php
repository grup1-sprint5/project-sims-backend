<?php

namespace App\Models\Traits;

/**
 * Trait BelongsToTenant
 *
 * Legacy placeholder kept for compatibility. Tenant isolation is handled
 * by PostgreSQL schema separation, so no tenant_id hooks are needed.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        // Intentionally left empty.
    }
}


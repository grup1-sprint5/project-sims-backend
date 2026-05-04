<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Tenant;

class TenantPolicy
{
    /**
     * Determine if the user can view any tenants.
     * SuperAdmin sees all; TenantAdmin sees only their own.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('tenants.view');
    }

    /**
     * Determine if the user can view a specific tenant.
     * TenantAdmin can only view their own tenant.
     */
    public function view(User $user, Tenant $tenant): bool
    {
        if (!$user->hasPermissionTo('tenants.view')) { return false; }
        if ($user->isSuperAdmin()) { return true; }
        return $this->belongsToTenant($user, $tenant);
    }

    /**
     * Determine if the user can create a new tenant.
     * Only superadmin / users with 'tenants.manage'.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('tenants.manage');
    }

    /**
     * Determine if the user can update a tenant.
     * SuperAdmin can update any tenant.
     * TenantAdmin can update only their own tenant.
     */
    public function update(User $user, Tenant $tenant): bool
    {
        if ($user->hasPermissionTo('tenants.manage')) {
            return true;
        }

        // TenantAdmin can edit their own tenant
        if ($user->isTenantAdmin() && $this->belongsToTenant($user, $tenant)) {
            return true;
        }

        return false;
    }

    private function belongsToTenant(User $user, Tenant $tenant): bool
    {
        if (!function_exists('tenancy') || !tenancy()->initialized) {
            return false;
        }

        return (string) tenancy()->tenant()->id === (string) $tenant->id;
    }

    /**
     * Determine if the user can delete a tenant.
     */
    public function delete(User $user, Tenant $tenant): bool
    {
        return $user->hasPermissionTo('tenants.delete');
    }
}

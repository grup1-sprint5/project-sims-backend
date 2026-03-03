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
        return $user->tenant_id === $tenant->id;
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
        if ($user->isTenantAdmin() && $user->tenant_id === $tenant->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine if the user can delete a tenant.
     */
    public function delete(User $user, Tenant $tenant): bool
    {
        return $user->hasPermissionTo('tenants.delete');
    }
}

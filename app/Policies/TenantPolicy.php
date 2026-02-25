<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Tenant;

class TenantPolicy
{
    /**
     * Determine if the user can view any tenants.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('tenants.view');
    }

    /**
     * Determine if the user can view a specific tenant.
     */
    public function view(User $user, Tenant $tenant): bool
    {
        return $user->hasPermissionTo('tenants.view');
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
     */
    public function update(User $user, Tenant $tenant): bool
    {
        return $user->hasPermissionTo('tenants.manage');
    }

    /**
     * Determine if the user can delete a tenant.
     */
    public function delete(User $user, Tenant $tenant): bool
    {
        return $user->hasPermissionTo('tenants.delete');
    }
}

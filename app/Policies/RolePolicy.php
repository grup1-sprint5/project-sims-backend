<?php

namespace App\Policies;

use App\Models\User;
use Spatie\Permission\Models\Role;

class RolePolicy
{
    /**
     * Protected system roles that cannot be modified.
     */
    private const PROTECTED_ROLES = ['SuperAdmin', 'TenantAdmin', 'Client', 'Maintenance'];

    /**
     * Determine if the user can view any roles.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('roles.view');
    }

    /**
     * Determine if the user can view a specific role.
     * Non-SuperAdmin users cannot view the SuperAdmin role.
     * TenantAdmin can only view system roles or their own tenant's roles.
     */
    public function view(User $user, Role $role): bool
    {
        if (!$user->hasPermissionTo('roles.view')) {
            return false;
        }

        if (!$user->isSuperAdmin()) {
            if (strtolower($role->name) === 'superadmin') {
                return false;
            }
            if ($role->tenant_id !== null && $role->tenant_id !== $user->tenant_id) {
                return false;
            }
        }

        return true;
    }

    /**
     * Determine if the user can create a new role.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('roles.manage');
    }

    /**
     * Determine if the user can update a role.
     * Protected system roles cannot be edited.
     */
    public function update(User $user, Role $role): bool
    {
        // Prevent editing of system roles
        if (in_array($role->name, self::PROTECTED_ROLES)) {
            return false;
        }

        if (!$user->hasPermissionTo('roles.manage')) {
            return false;
        }

        // TenantAdmin can only update roles belonging to their tenant
        if (!$user->isSuperAdmin() && $role->tenant_id !== $user->tenant_id) {
            return false;
        }

        return true;
    }

    /**
     * Determine if the user can delete a role.
     * Protected system roles cannot be deleted.
     */
    public function delete(User $user, Role $role): bool
    {
        // Prevent deletion of system roles
        if (in_array($role->name, self::PROTECTED_ROLES)) {
            return false;
        }

        if (!$user->hasPermissionTo('roles.delete')) {
            return false;
        }

        // TenantAdmin can only delete roles belonging to their tenant
        if (!$user->isSuperAdmin() && $role->tenant_id !== $user->tenant_id) {
            return false;
        }

        return true;
    }
}

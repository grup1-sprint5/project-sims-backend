<?php

namespace App\Policies;

use App\Models\User;
use Spatie\Permission\Models\Role;

class RolePolicy
{
    /**
     * Protected system roles that cannot be modified.
     */
    private const PROTECTED_ROLES = ['Admin', 'Client', 'Maintenance'];

    /**
     * Determine if the user can view any roles.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('roles.view');
    }

    /**
     * Determine if the user can view a specific role.
     */
    public function view(User $user, Role $role): bool
    {
        return $user->hasPermissionTo('roles.view');
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

        return $user->hasPermissionTo('roles.manage');
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

        return $user->hasPermissionTo('roles.delete');
    }
}

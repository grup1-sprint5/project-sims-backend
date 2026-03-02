<?php

namespace App\Policies;

use App\Models\User;
use Spatie\Permission\Models\Role;

class RolePolicy
{
    /**
     * System roles that cannot be modified or deleted.
     */
    private const PROTECTED_ROLES = ['Admin', 'Client', 'Maintenance'];

    /**
     * Safely checks a permission, returning false instead of throwing
     * PermissionDoesNotExist if the permission is not yet seeded.
     */
    private function hasPerm(User $user, string ...$perms): bool
    {
        try {
            return $user->hasAnyPermission($perms);
        } catch (\Exception) {
            return false;
        }
    }

    /** List roles — requires roles.view or roles.manage. */
    public function viewAny(User $user): bool
    {
        return $this->hasPerm($user, 'roles.view', 'roles.manage');
    }

    /** View a single role — requires roles.view or roles.manage. */
    public function view(User $user, Role $role): bool
    {
        return $this->hasPerm($user, 'roles.view', 'roles.manage');
    }

    /** Create role — requires roles.manage. */
    public function create(User $user): bool
    {
        return $this->hasPerm($user, 'roles.manage');
    }

    /** Update role — requires roles.manage; system roles are protected. */
    public function update(User $user, Role $role): bool
    {
        if (in_array($role->name, self::PROTECTED_ROLES)) {
            return false;
        }

        return $this->hasPerm($user, 'roles.manage');
    }

    /** Delete role — requires roles.delete; system roles are protected. */
    public function delete(User $user, Role $role): bool
    {
        if (in_array($role->name, self::PROTECTED_ROLES)) {
            return false;
        }

        return $this->hasPerm($user, 'roles.delete');
    }
}

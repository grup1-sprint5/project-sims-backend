<?php

namespace App\Policies;

use App\Models\User;
use Spatie\Permission\Models\Role;

class RolePolicy
{
    /**
     * System roles that cannot be modified or deleted.
     */
    private const PROTECTED_ROLES = ['SuperAdmin', 'TenantAdmin', 'Client', 'Maintenance'];

    /**
     * Safely checks a permission, returning false instead of throwing.
     */
    private function hasPerm(User $user, string ...$perms): bool
    {
        try {
            return $user->hasAnyPermission($perms);
        } catch (\Exception) {
            return false;
        }
    }

    public function viewAny(User $user): bool
    {
        return $this->hasPerm($user, 'roles.view', 'roles.manage');
    }

    public function view(User $user, Role $role): bool
    {
        if (!$this->hasPerm($user, 'roles.view', 'roles.manage')) {
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

    public function create(User $user): bool
    {
        return $this->hasPerm($user, 'roles.manage');
    }

    public function update(User $user, Role $role): bool
    {
        if (in_array($role->name, self::PROTECTED_ROLES, true)) {
            return false;
        }

        if (!$this->hasPerm($user, 'roles.manage')) {
            return false;
        }

        if (!$user->isSuperAdmin() && $role->tenant_id !== $user->tenant_id) {
            return false;
        }

        return true;
    }

    public function delete(User $user, Role $role): bool
    {
        if (in_array($role->name, self::PROTECTED_ROLES, true)) {
            return false;
        }

        $canDelete = $this->hasPerm($user, 'roles.delete')
            || ($user->hasRole('TenantAdmin') && $this->hasPerm($user, 'roles.manage'));

        if (!$canDelete) {
            return false;
        }

        if (!$user->isSuperAdmin() && $role->tenant_id !== $user->tenant_id) {
            return false;
        }

        return true;
    }
}

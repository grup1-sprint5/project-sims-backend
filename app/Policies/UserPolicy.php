<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    /**
     * Comprova si l'usuari té el permís indicat de forma segura.
     * Spatie llança PermissionDoesNotExist si el permís no existeix a la BD,
     * per això usem try/catch per retornar false en lloc de 500.
     */
    private function hasPerm(User $user, string ...$perms): bool
    {
        try {
            return $user->hasAnyPermission($perms);
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Determine if the user can view any users.
     * Admins with users.view or users.manage can list all users.
     */
    public function viewAny(User $user): bool
    {
        return $this->hasPerm($user, 'users.view', 'users.manage');
    }

    /**
     * Determine if the user can view a specific user.
     * Admin can view any user, users can view their own profile.
     * TenantAdmin can only view users from their own tenant.
     */
    public function view(User $user, User $targetUser): bool
    {
        if ($user->id === $targetUser->id) { return true; }
        if (!$this->hasPerm($user, 'users.view', 'users.manage')) { return false; }
        if ($user->isSuperAdmin()) { return true; }
        return $user->tenant_id && $user->tenant_id === $targetUser->tenant_id;
    }

    /**
     * Determine if the user can create a new user.
     * Only users with users.manage permission.
     */
    public function create(User $user): bool
    {
        return $this->hasPerm($user, 'users.manage');
    }

    /**
     * Determine if the user can update a user.
     * Admin can update any user, users can update their own profile.
     * TenantAdmin can only update users from their own tenant.
     */
    public function update(User $user, User $targetUser): bool
    {
        if ($user->id === $targetUser->id) { return true; }
        if (!$this->hasPerm($user, 'users.manage')) { return false; }
        if ($user->isSuperAdmin()) { return true; }
        return $user->tenant_id && $user->tenant_id === $targetUser->tenant_id;
    }

    /**
     * Determine if the user can delete a user.
     * Only Admins can delete users, and they cannot delete themselves.
     * TenantAdmin can only delete users from their own tenant.
     */
    public function delete(User $user, User $targetUser): bool
    {
        if ($user->id === $targetUser->id) { return false; }
        if (!$this->hasPerm($user, 'users.delete')) { return false; }
        if ($user->isSuperAdmin()) { return true; }
        return $user->tenant_id && $user->tenant_id === $targetUser->tenant_id;
    }

    /**
     * Determine if the user can restore a soft-deleted user.
     */
    public function restore(User $user, User $targetUser): bool
    {
        if (!$this->hasPerm($user, 'users.restore', 'users.manage')) { return false; }
        if ($user->isSuperAdmin()) { return true; }
        return $user->tenant_id && $user->tenant_id === $targetUser->tenant_id;
    }
}

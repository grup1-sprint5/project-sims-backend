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
     * Admin can view anyone, users can view their own profile.
     */
    public function view(User $user, User $targetUser): bool
    {
        if ($this->hasPerm($user, 'users.view', 'users.manage')) {
            return true;
        }

        return $user->id === $targetUser->id;
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
     * Admin can update anyone, users can update their own profile.
     */
    public function update(User $user, User $targetUser): bool
    {
        if ($this->hasPerm($user, 'users.manage')) {
            return true;
        }

        return $user->id === $targetUser->id;
    }

    /**
     * Determine if the user can delete a user.
     * Only admins with users.delete permission, never themselves.
     */
    public function delete(User $user, User $targetUser): bool
    {
        if ($user->id === $targetUser->id) {
            return false;
        }

        return $this->hasPerm($user, 'users.delete');
    }

    /**
     * Determine if the user can restore a soft-deleted user.
     */
    public function restore(User $user, User $targetUser): bool
    {
        return $this->hasPerm($user, 'users.restore', 'users.manage');
    }
}

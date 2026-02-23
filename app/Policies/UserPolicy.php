<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    /**
     * Determine if the user can view any users.
     * Only Admins can list all users.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('users.view');
    }

    /**
     * Determine if the user can view a specific user.
     * Admin can view any user, users can view their own profile.
     */
    public function view(User $user, User $targetUser): bool
    {
        // Admin can view anyone
        if ($user->hasPermissionTo('users.view')) {
            return true;
        }

        // Users can view their own profile
        return $user->id === $targetUser->id;
    }

    /**
     * Determine if the user can create a new user.
     * Only Admins can create users.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('users.manage');
    }

    /**
     * Determine if the user can update a user.
     * Admin can update any user, users can update their own profile.
     */
    public function update(User $user, User $targetUser): bool
    {
        // Admin can update anyone
        if ($user->hasPermissionTo('users.manage')) {
            return true;
        }

        // Users can update their own profile
        return $user->id === $targetUser->id;
    }

    /**
     * Determine if the user can delete a user.
     * Only Admins can delete users, and they cannot delete themselves.
     */
    public function delete(User $user, User $targetUser): bool
    {
        // Prevent self-deletion
        if ($user->id === $targetUser->id) {
            return false;
        }

        return $user->hasPermissionTo('users.delete');
    }
}

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
     * TenantAdmin can only view users from their own tenant.
     */
    public function view(User $user, User $targetUser): bool
    {
        if ($user->id === $targetUser->id) { return true; }
        if (!$user->hasPermissionTo('users.view')) { return false; }
        if ($user->isSuperAdmin()) { return true; }
        return $user->tenant_id && $user->tenant_id === $targetUser->tenant_id;
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
     * TenantAdmin can only update users from their own tenant.
     */
    public function update(User $user, User $targetUser): bool
    {
        if ($user->id === $targetUser->id) { return true; }
        if (!$user->hasPermissionTo('users.manage')) { return false; }
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
        if (!$user->hasPermissionTo('users.delete')) { return false; }
        if ($user->isSuperAdmin()) { return true; }
        return $user->tenant_id && $user->tenant_id === $targetUser->tenant_id;
    }
}

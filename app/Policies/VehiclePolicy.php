<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vehicle;

class VehiclePolicy
{
    /**
     * Determine if the user can view any vehicles.
     * Any authenticated user with 'vehicles.view' permission can list vehicles.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('vehicles.view');
    }

    /**
     * Determine if the user can view a specific vehicle.
     * Must belong to the same tenant (or be an admin without tenant).
     */
    public function view(User $user, Vehicle $vehicle): bool
    {
        if (!$user->hasPermissionTo('vehicles.view')) {
            return false;
        }

        // Super admin (no tenant) can view all
        if ($user->tenant_id === null) {
            return true;
        }

        // User can only view vehicles from their tenant
        return $vehicle->tenant_id === $user->tenant_id;
    }

    /**
     * Determine if the user can create a new vehicle.
     * Only users with 'vehicles.manage' permission (Admin, Maintenance).
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('vehicles.manage');
    }

    /**
     * Determine if the user can update a vehicle.
     * Only users with 'vehicles.manage' permission from the same tenant.
     */
    public function update(User $user, Vehicle $vehicle): bool
    {
        if (!$user->hasPermissionTo('vehicles.manage')) {
            return false;
        }

        // Super admin (no tenant) can update all
        if ($user->tenant_id === null) {
            return true;
        }

        // User can only update vehicles from their tenant
        return $vehicle->tenant_id === $user->tenant_id;
    }

    /**
     * Determine if the user can delete a vehicle.
     * Only admins with 'vehicles.delete' permission from the same tenant.
     */
    public function delete(User $user, Vehicle $vehicle): bool
    {
        if (!$user->hasPermissionTo('vehicles.delete')) {
            return false;
        }

        // Super admin (no tenant) can delete all
        if ($user->tenant_id === null) {
            return true;
        }

        // User can only delete vehicles from their tenant
        return $vehicle->tenant_id === $user->tenant_id;
    }
}
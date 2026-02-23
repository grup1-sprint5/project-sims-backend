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
     */
    public function view(User $user, Vehicle $vehicle): bool
    {
        return $user->hasPermissionTo('vehicles.view');
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
     * Only users with 'vehicles.manage' permission (Admin, Maintenance).
     */
    public function update(User $user, Vehicle $vehicle): bool
    {
        return $user->hasPermissionTo('vehicles.manage');
    }

    /**
     * Determine if the user can delete a vehicle.
     * Only admins with 'vehicles.delete' permission can delete.
     */
    public function delete(User $user, Vehicle $vehicle): bool
    {
        return $user->hasPermissionTo('vehicles.delete');
    }
}
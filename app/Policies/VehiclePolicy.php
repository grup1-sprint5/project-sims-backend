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
     * TenantAdmin can only view vehicles from their own tenant.
     */
    public function view(User $user, Vehicle $vehicle): bool
    {
        if (!$user->hasPermissionTo('vehicles.view')) { return false; }
        if ($user->isSuperAdmin()) { return true; }
        return $vehicle->tenant_id === null || $vehicle->tenant_id === $user->tenant_id;
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
     * TenantAdmin can only update vehicles from their own tenant.
     */
    public function update(User $user, Vehicle $vehicle): bool
    {
        if (!$user->hasPermissionTo('vehicles.manage')) { return false; }
        if ($user->isSuperAdmin()) { return true; }
        return $vehicle->tenant_id === null || $vehicle->tenant_id === $user->tenant_id;
    }

    /**
     * Determine if the user can delete a vehicle.
     * TenantAdmin can only delete vehicles from their own tenant.
     */
    public function delete(User $user, Vehicle $vehicle): bool
    {
        $canDelete = $user->hasPermissionTo('vehicles.delete')
            || ($user->hasRole('TenantAdmin') && $user->hasPermissionTo('vehicles.manage'));

        if (!$canDelete) { return false; }
        if ($user->isSuperAdmin()) { return true; }
        return $vehicle->tenant_id === null || $vehicle->tenant_id === $user->tenant_id;
    }
}
<?php

namespace App\Policies;

use App\Models\Geofence;
use App\Models\User;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

class GeofencePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'geofences.view')
            || $this->hasPermission($user, 'geofences.manage')
            || $this->hasPermission($user, 'vehicles.view')
            || $this->hasPermission($user, 'vehicles.manage');
    }

    public function view(User $user, Geofence $geofence): bool
    {
        if (!$this->viewAny($user)) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return $geofence->tenant_id === $user->tenant_id;
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'geofences.manage')
            || $this->hasPermission($user, 'vehicles.manage');
    }

    public function update(User $user, Geofence $geofence): bool
    {
        if (!$this->hasPermission($user, 'geofences.manage')
            && !$this->hasPermission($user, 'vehicles.manage')) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return $geofence->tenant_id === $user->tenant_id;
    }

    public function delete(User $user, Geofence $geofence): bool
    {
        $canDelete = $this->hasPermission($user, 'geofences.delete')
            || ($user->hasRole('TenantAdmin')
                && ($this->hasPermission($user, 'geofences.manage')
                    || $this->hasPermission($user, 'vehicles.manage')));

        if (!$canDelete) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return $geofence->tenant_id === $user->tenant_id;
    }

    private function hasPermission(User $user, string $permission): bool
    {
        try {
            return $user->hasPermissionTo($permission);
        } catch (PermissionDoesNotExist) {
            return false;
        }
    }
}

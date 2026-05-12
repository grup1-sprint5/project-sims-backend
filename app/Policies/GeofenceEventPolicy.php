<?php

namespace App\Policies;

use App\Models\GeofenceEvent;
use App\Models\User;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

class GeofenceEventPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'geofences.view')
            || $this->hasPermission($user, 'geofences.manage')
            || $this->hasPermission($user, 'vehicles.view')
            || $this->hasPermission($user, 'vehicles.manage');
    }

    public function view(User $user, GeofenceEvent $event): bool
    {
        if (!$this->viewAny($user)) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return $event->tenant_id === $user->tenant_id;
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

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class CentralPermissionsSeeder extends Seeder
{
    public function run()
    {
        $permissions = [
            'users.view',
            'users.manage',
            'users.delete',
            'users.restore',

            'roles.view',
            'roles.manage',
            'roles.delete',

            'reservations.view',
            'reservations.manage',

            'vehicles.view',
            'vehicles.manage',

            'geofences.view',
            'geofences.manage',

            'geofence-events.view',
            'geofence-events.manage',

            'tickets.view',
            'tickets.manage',

            // add more central permissions if required
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        // Ensure SuperAdmin role exists and has all permissions
        $role = Role::firstOrCreate(['name' => 'SuperAdmin', 'guard_name' => 'web']);
        $role->givePermissionTo($permissions);
    }
}

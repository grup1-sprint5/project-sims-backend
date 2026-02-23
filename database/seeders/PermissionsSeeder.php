<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionsSeeder extends Seeder
{
    /**
     * Seed the permissions table with a simplified structure.
     * Format: `module.action` where action is: view, manage (create/edit), delete
     */
    public function run(): void
    {
        // Clear all cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Define simplified permissions: 3 levels per module
        $permissions = [
            // Users Module
            'users.view',
            'users.manage',    // Create, Edit
            'users.delete',

            // Roles Module
            'roles.view',
            'roles.manage',     // Create, Edit
            'roles.delete',

            // Vehicles Module
            'vehicles.view',
            'vehicles.manage',  // Create, Edit, Maintain
            'vehicles.delete',

            // Tickets Module
            'tickets.view',
            'tickets.manage',   // Create, Respond
            'tickets.delete',

            // Reservations Module
            'reservations.view',
            'reservations.manage', // Create, Activate, Cancel, Finish
            'reservations.delete',
        ];

        // Create or retrieve permissions
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission, 'guard_name' => 'web']
            );
        }
    }
}
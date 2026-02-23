<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesSeeder extends Seeder
{
    /**
     * Seed the roles and assign permissions according to business rules.
     */
    public function run(): void
    {
        // Create core roles
        $adminRole = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        $clientRole = Role::firstOrCreate(['name' => 'Client', 'guard_name' => 'web']);
        $maintenanceRole = Role::firstOrCreate(['name' => 'Maintenance', 'guard_name' => 'web']);

        // Admin: Full access to all permissions
        $adminRole->syncPermissions(Permission::all());

        // Client: Limited permissions
        // Can view vehicles and manage own tickets/reservations
        $clientRole->syncPermissions([
            'vehicles.view',
            'tickets.view',
            'tickets.manage',
            'reservations.view',
            'reservations.manage',
        ]);

        // Maintenance: Vehicle management
        // Can view and manage vehicles (maintenance, repairs, etc.)
        $maintenanceRole->syncPermissions([
            'vehicles.view',
            'vehicles.manage',
        ]);
    }
}
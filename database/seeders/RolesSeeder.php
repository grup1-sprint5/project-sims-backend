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
        $superAdminRole = Role::firstOrCreate(['name' => 'SuperAdmin', 'guard_name' => 'web']);
        $tenantAdminRole = Role::firstOrCreate(['name' => 'TenantAdmin', 'guard_name' => 'web']);
        $clientRole = Role::firstOrCreate(['name' => 'Client', 'guard_name' => 'web']);
        $maintenanceRole = Role::firstOrCreate(['name' => 'Maintenance', 'guard_name' => 'web']);

        // SuperAdmin: Full access to all permissions (cross-tenant)
        $superAdminRole->syncPermissions(Permission::all());

        // TenantAdmin: Manage resources within own tenant
        $tenantAdminRole->syncPermissions([
            'users.view',
            'users.manage',
            'roles.view',
            'vehicles.view',
            'vehicles.manage',
            'geofences.view',
            'geofences.manage',
            'tickets.view',
            'tickets.manage',
            'reservations.view',
            'reservations.manage',
            'tenants.view',
        ]);

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
            'geofences.view',
        ]);
    }
}
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
        // Legacy cleanup: this role was renamed to TenantWorker.
        Role::where('name', 'Maintenance')->delete();

        // Create core roles
        $superAdminRole = Role::firstOrCreate(['name' => 'SuperAdmin', 'guard_name' => 'web']);
        $tenantAdminRole = Role::firstOrCreate(['name' => 'TenantAdmin', 'guard_name' => 'web']);
        $tenantWorkerRole = Role::firstOrCreate(['name' => 'TenantWorker', 'guard_name' => 'web']);
        $clientRole = Role::firstOrCreate(['name' => 'Client', 'guard_name' => 'web']);

        // SuperAdmin: Full access to all permissions (cross-tenant)
        $superAdminRole->syncPermissions(Permission::all());

        // TenantAdmin: Manage resources within own tenant
        $tenantAdminRole->syncPermissions([
            'users.view',
            'users.manage',
            'roles.view',
            'roles.manage',
            'vehicles.view',
            'vehicles.manage',
            'tickets.view',
            'tickets.manage',
            'reservations.view',
            'reservations.manage',
            'tenants.view',
        ]);

        // TenantWorker: operational role inside own tenant
        $tenantWorkerRole->syncPermissions([
            'vehicles.view',
            'vehicles.manage',
            'tickets.view',
            'tickets.manage',
            'reservations.view',
            'reservations.manage',
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

    }
}

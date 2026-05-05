<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Ensures geofence-related permissions exist in the tenant database.
     * This migration handles cases where the PermissionsSeeder did not run
     * or where permissions may have been deleted.
     */
    public function up(): void
    {
        // Clear permission cache
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Ensure geofence permissions exist
        $geofencePermissions = [
            'geofences.view',
            'geofences.manage',
            'geofences.delete',
        ];

        foreach ($geofencePermissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission, 'guard_name' => 'web']
            );
        }

        // Sync TenantAdmin role with geofencing permissions
        $tenantAdminRole = Role::where('name', 'TenantAdmin')->where('guard_name', 'web')->first();
        if ($tenantAdminRole) {
            $tenantAdminRole->givePermissionTo(['geofences.view', 'geofences.manage']);
        }

        // Sync Maintenance role with geofence view
        $maintenanceRole = Role::where('name', 'Maintenance')->where('guard_name', 'web')->first();
        if ($maintenanceRole) {
            $maintenanceRole->givePermissionTo('geofences.view');
        }

        // Clear permission cache again after modifications
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Intentionally left blank - do not remove permissions on rollback
    }
};

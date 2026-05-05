<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class EnsureGeofencePermissions extends Command
{
    protected $signature = 'geofence:ensure-permissions';
    protected $description = 'Ensure geofence-related permissions exist in the current tenant database. Safe to run multiple times.';

    public function handle()
    {
        if (function_exists('tenancy') && !tenancy()->initialized) {
            $this->error('Tenancy is not initialized; refusing to run on the central database.');
            $this->line('Run via stancl/tenancy:');
            $this->line('  php artisan tenants:run geofence:ensure-permissions');
            $this->line('  php artisan tenants:run --tenants=sims-corp geofence:ensure-permissions');

            return 1;
        }

        $this->info('Ensuring geofence permissions exist in current tenant...');

        // Clear permission cache
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Ensure geofence permissions exist
        $geofencePermissions = ['geofences.view', 'geofences.manage', 'geofences.delete'];
        $created = 0;

        foreach ($geofencePermissions as $permission) {
            $result = Permission::firstOrCreate(
                ['name' => $permission, 'guard_name' => 'web']
            );
            if ($result->wasRecentlyCreated) {
                $this->info("✓ Created permission: {$permission}");
                $created++;
            }
        }

        // Sync roles with permissions
        $tenantAdminRole = Role::where('name', 'TenantAdmin')->where('guard_name', 'web')->first();
        if ($tenantAdminRole) {
            $tenantAdminRole->givePermissionTo(['geofences.view', 'geofences.manage']);
            $this->info('✓ Synced TenantAdmin role with geofence permissions');
        }

        $maintenanceRole = Role::where('name', 'Maintenance')->where('guard_name', 'web')->first();
        if ($maintenanceRole) {
            $maintenanceRole->givePermissionTo('geofences.view');
            $this->info('✓ Synced Maintenance role with geofence view permission');
        }

        // Clear cache again
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        if ($created > 0) {
            $this->info("\n<fg=green>Successfully created {$created} permission(s)</>");
        } else {
            $this->info("\n<fg=cyan>All geofence permissions already exist</>");
        }

        return 0;
    }
}

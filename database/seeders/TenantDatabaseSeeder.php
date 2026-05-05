<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class TenantDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $currentTenantId = function_exists('tenant') && tenant() ? (string) tenant('id') : null;

        // 1. Seed global things inside this tenant
        $this->call([
            PermissionsSeeder::class,
            RolesSeeder::class,
        ]);

        $password = Hash::make('password');

        // 2. Create standard users for this specific tenant.
        // Seeders must be idempotent even if users are soft-deleted.
        $admin = User::withoutGlobalScopes()->withTrashed()->updateOrCreate(
            ['email' => 'admin@test.com'],
            [
                'name' => 'Admin User',
                'username' => 'admin',
                'password' => $password,
                'active' => true,
                'tenant_id' => $currentTenantId,
                'deleted_at' => null,
            ]
        );
        if (method_exists($admin, 'restore') && $admin->trashed()) {
            $admin->restore();
        }
        $admin->assignRole('SuperAdmin');

        $client = User::withoutGlobalScopes()->withTrashed()->updateOrCreate(
            ['email' => 'client@test.com'],
            [
                'name' => 'Client User',
                'username' => 'client',
                'password' => $password,
                'active' => true,
                'tenant_id' => $currentTenantId,
                'deleted_at' => null,
            ]
        );
        if (method_exists($client, 'restore') && $client->trashed()) {
            $client->restore();
        }
        $client->assignRole('Client');

        $maint = User::withoutGlobalScopes()->withTrashed()->updateOrCreate(
            ['email' => 'maint@test.com'],
            [
                'name' => 'Maintenance User',
                'username' => 'maint',
                'password' => $password,
                'active' => true,
                'tenant_id' => $currentTenantId,
                'deleted_at' => null,
            ]
        );
        if (method_exists($maint, 'restore') && $maint->trashed()) {
            $maint->restore();
        }
        $maint->assignRole('Maintenance');

        // Add additional test data specifically for this tenant
        $this->call([
            TestDataSeeder::class,
        ]);
    }
}

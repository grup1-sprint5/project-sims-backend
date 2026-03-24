<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class TenantDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Seed global things inside this tenant
        $this->call([
            PermissionsSeeder::class,
            RolesSeeder::class,
        ]);

        $password = Hash::make('password');

        // 2. Create standard users for this specific tenant
        // Notice: We use withoutGlobalScopes to avoid unique constraint 
        // issues if the user already exists in the global 'users' table.
        User::withoutGlobalScopes()->updateOrCreate(
            ['email' => 'admin@test.com'],
            [
                'name' => 'Admin User',
                'username' => 'admin',
                'password' => $password,
                'active' => true,
                'tenant_id' => tenant('id'),
            ]
        )->assignRole('SuperAdmin');

        User::withoutGlobalScopes()->updateOrCreate(
            ['email' => 'client@test.com'],
            [
                'name' => 'Client User',
                'username' => 'client',
                'password' => $password,
                'active' => true,
                'tenant_id' => tenant('id'),
            ]
        )->assignRole('Client');

        // Add additional test data specifically for this tenant
        $this->call([
            TestDataSeeder::class,
        ]);
    }
}

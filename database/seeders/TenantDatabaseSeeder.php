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
        // Notice: No tenant_id needed here because we are ALREADY inside the tenant connection/context
        User::firstOrCreate(
            ['email' => 'admin@test.com'],
            [
                'name' => 'Admin User',
                'username' => 'admin',
                'password' => $password,
                'active' => true,
            ]
        )->assignRole('SuperAdmin');

        User::firstOrCreate(
            ['email' => 'client@test.com'],
            [
                'name' => 'Client User',
                'username' => 'client',
                'password' => $password,
                'active' => true,
            ]
        )->assignRole('Client');

        // Add additional test data specifically for this tenant
        $this->call([
            TestDataSeeder::class,
        ]);
    }
}

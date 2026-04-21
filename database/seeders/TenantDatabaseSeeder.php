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

        $tenantId = function_exists('tenant') && tenant() ? (string) tenant('id') : null;
        if (!$tenantId) {
            echo "⚠️  No tenant context initialized. Skipping TenantDatabaseSeeder.\n";
            return;
        }

        $password = Hash::make('password');

        $profiles = [
            'sims-corp' => [
                ['name' => 'SIMS Admin', 'username' => 'admin_sims', 'email' => 'admin@simscorp.com', 'role' => 'TenantAdmin', 'active' => true],
                ['name' => 'SIMS Worker 1', 'username' => 'worker1_sims', 'email' => 'worker1@simscorp.com', 'role' => 'TenantWorker', 'active' => true],
                ['name' => 'SIMS Worker 2', 'username' => 'worker2_sims', 'email' => 'worker2@simscorp.com', 'role' => 'TenantWorker', 'active' => true],
                ['name' => 'SIMS Client 1', 'username' => 'client1_sims', 'email' => 'client1@simscorp.com', 'role' => 'Client', 'active' => true],
                ['name' => 'SIMS Client 2', 'username' => 'client2_sims', 'email' => 'client2@simscorp.com', 'role' => 'Client', 'active' => true],
                ['name' => 'SIMS Client 3', 'username' => 'client3_sims', 'email' => 'client3@simscorp.com', 'role' => 'Client', 'active' => true],
            ],
            'ecomove' => [
                ['name' => 'EcoMove Admin', 'username' => 'admin_ecomove', 'email' => 'admin@ecomove.es', 'role' => 'TenantAdmin', 'active' => true],
                ['name' => 'EcoMove Worker 1', 'username' => 'worker1_ecomove', 'email' => 'worker1@ecomove.es', 'role' => 'TenantWorker', 'active' => true],
                ['name' => 'EcoMove Worker 2', 'username' => 'worker2_ecomove', 'email' => 'worker2@ecomove.es', 'role' => 'TenantWorker', 'active' => true],
                ['name' => 'EcoMove Client 1', 'username' => 'client1_ecomove', 'email' => 'client1@ecomove.es', 'role' => 'Client', 'active' => true],
                ['name' => 'EcoMove Client 2', 'username' => 'client2_ecomove', 'email' => 'client2@ecomove.es', 'role' => 'Client', 'active' => true],
                ['name' => 'EcoMove Client 3', 'username' => 'client3_ecomove', 'email' => 'client3@ecomove.es', 'role' => 'Client', 'active' => false],
            ],
        ];

        foreach ($profiles[$tenantId] ?? [] as $profile) {
            $user = User::updateOrCreate(
                ['email' => $profile['email']],
                [
                    'tenant_id' => $tenantId,
                    'name' => $profile['name'],
                    'username' => $profile['username'],
                    'password' => $password,
                    'active' => $profile['active'],
                ]
            );
            $user->syncRoles([$profile['role']]);
        }

        // Single global super-admin account for platform-wide dashboard.
        if ($tenantId === 'sims-corp') {
            $superAdmin = User::updateOrCreate(
                ['email' => 'superadmin@simsplatform.com'],
                [
                    'tenant_id' => $tenantId,
                    'name' => 'Platform SuperAdmin',
                    'username' => 'superadmin',
                    'password' => $password,
                    'active' => true,
                ]
            );
            $superAdmin->syncRoles(['SuperAdmin']);
        }

        // Add additional test data specifically for this tenant
        $this->call([
            TestDataSeeder::class,
        ]);
    }
}

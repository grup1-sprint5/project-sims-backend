<?php

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1) Seed central tenants catalog.
        $this->call([
            TenantsSeeder::class,
        ]);

        $query = Tenant::query()
            ->where('active', true)
            ->whereIn('slug', ['sims-corp', 'ecomove']);

        $tenants = $query->get();
        if ($tenants->count() < 2) {
            throw new \RuntimeException('Required tenants not found after TenantsSeeder.');
        }

        // 2) Seed each tenant DB with roles/users/test data using deterministic profiles.
        foreach ($tenants as $tenant) {
            if (!$tenant instanceof Tenant) {
                continue;
            }

            tenancy()->initialize($tenant);

            try {
                $this->call([
                    TenantDatabaseSeeder::class,
                ]);
            } finally {
                tenancy()->end();
            }
        }
    }
}
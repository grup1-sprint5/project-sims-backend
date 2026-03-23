<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Cargar solo los inquilinos primero
        $this->call([
            TenantsSeeder::class,
        ]);

        $simsTenant = \App\Models\Tenant::find('sims-corp');
        $ecoTenant = \App\Models\Tenant::find('ecomove');

        if (!$simsTenant || !$ecoTenant) {
            throw new \RuntimeException('Required tenants not found after TenantsSeeder.');
        }

        // We skip Permissions and Roles globally because those migrations are in database/migrations/tenant/
        // To seed permissions and roles correctly, use: php artisan tenants:seed --class=PermissionsSeeder (and RolesSeeder)
        
        // However, I'll provide a way to seed everything for a tenant if needed.
        // For now, let's keep the user creation minimal on central if shared, 
        // but typically users in this architecture are per-tenant.
    }
}
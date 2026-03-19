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
        // 1. Cargar Permisos y Roles primero
        $this->call([
            PermissionsSeeder::class,
            RolesSeeder::class,
            TenantsSeeder::class,
        ]);

        $simsTenant = \App\Models\Tenant::find('sims-corp');
        $ecoTenant = \App\Models\Tenant::find('ecomove');

        if (!$simsTenant || !$ecoTenant) {
            throw new \RuntimeException('Required tenants not found after TenantsSeeder.');
        }

        $password = Hash::make('password'); // Contraseña común para test

        // 2. Crear ADMIN (El Jefe)
        $admin = User::firstOrCreate(
            ['email' => 'admin@test.com'], // Cambiado a test.com para uniformidad
            [
                'name' => 'Super Admin',
                'username' => 'admin',
                'password' => $password,
                'active' => true,
                'tenant_id' => $simsTenant->id,
            ]
        );
        $admin->assignRole('SuperAdmin');

        // 3. Crear CLIENTE (El usuario estándar)
        $client = User::firstOrCreate(
            ['email' => 'client@test.com'],
            [
                'name' => 'Cliente de Prueba',
                'username' => 'client',
                'password' => $password,
                'active' => true,
                'tenant_id' => $simsTenant->id,
            ]
        );
        $client->assignRole('Client');

        // 4. Crear MANTENIMIENTO (El técnico)
        $maintenance = User::firstOrCreate(
            ['email' => 'maint@test.com'],
            [
                'name' => 'Técnico Mantenimiento',
                'username' => 'maintenance',
                'password' => $password,
                'active' => true,
                'tenant_id' => $ecoTenant->id,
            ]
        );
        $maintenance->assignRole('Maintenance');

        // 5. Crear TENANT ADMIN para SIMS Corp
        $tenantAdmin1 = User::firstOrCreate(
            ['email' => 'admin@simscorp.com'],
            [
                'name' => 'Admin SIMS Corp',
                'username' => 'admin_sims',
                'password' => $password,
                'active' => true,
                'tenant_id' => $simsTenant->id,
            ]
        );
        $tenantAdmin1->assignRole('TenantAdmin');

        // 6. Crear TENANT ADMIN para EcoMove SL
        $tenantAdmin2 = User::firstOrCreate(
            ['email' => 'admin@ecomove.es'],
            [
                'name' => 'Admin EcoMove',
                'username' => 'admin_ecomove',
                'password' => $password,
                'active' => true,
                'tenant_id' => $ecoTenant->id,
            ]
        );
        $tenantAdmin2->assignRole('TenantAdmin');

        // 7. Crear datos de prueba
        $this->call([
            TestDataSeeder::class,
            MongoVehicleLocationsSeeder::class,
        ]);
    }
}
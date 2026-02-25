<?php

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;

class TenantsSeeder extends Seeder
{
    /**
     * Seed the tenants table with sample data.
     */
    public function run(): void
    {
        $tenants = [
            [
                'name'    => 'SIMS Corp',
                'slug'    => 'sims-corp',
                'tax_id'  => 'B12345678',
                'email'   => 'info@simscorp.com',
                'phone'   => '+34 600 000 001',
                'address' => 'Calle Principal 1, Barcelona',
                'active'  => true,
            ],
            [
                'name'    => 'EcoMove SL',
                'slug'    => 'ecomove',
                'tax_id'  => 'B87654321',
                'email'   => 'contact@ecomove.es',
                'phone'   => '+34 600 000 002',
                'address' => 'Av. Diagonal 200, Barcelona',
                'active'  => true,
            ],
            [
                'name'    => 'UrbanRide',
                'slug'    => 'urbanride',
                'tax_id'  => null,
                'email'   => 'hello@urbanride.io',
                'phone'   => null,
                'address' => null,
                'active'  => false,
            ],
        ];

        foreach ($tenants as $tenant) {
            Tenant::firstOrCreate(['slug' => $tenant['slug']], $tenant);
        }
    }
}

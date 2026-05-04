<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

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
                'city'    => 'Amposta',
                'map_center_lat' => 40.709950,
                'map_center_lng' => 0.579650,
                'active'  => true,
            ],
            [
                'name'    => 'EcoMove SL',
                'slug'    => 'ecomove',
                'tax_id'  => 'B87654321',
                'email'   => 'contact@ecomove.es',
                'phone'   => '+34 600 000 002',
                'address' => 'Av. Diagonal 200, Barcelona',
                'city'    => 'La Ràpita',
                'map_center_lat' => 40.620900,
                'map_center_lng' => 0.592800,
                'active'  => true,
            ],
        ];

        $connection = (string) (config('tenancy.database.central_connection')
            ?? config('database.default')
            ?? 'pgsql');

        $hasDataColumn = Schema::connection($connection)->hasColumn('tenants', 'data');

        foreach ($tenants as $tenant) {
            $slug = strtolower((string) $tenant['slug']);

            $payload = [
                'name' => $tenant['name'],
                'slug' => $slug,
                'tax_id' => $tenant['tax_id'],
                'email' => $tenant['email'],
                'phone' => $tenant['phone'],
                'address' => $tenant['address'],
                'active' => (bool) $tenant['active'],
            ];

            $existing = DB::connection($connection)
                ->table('tenants')
                ->where('slug', $slug)
                ->first();

            if ($existing) {
                DB::connection($connection)
                    ->table('tenants')
                    ->where('id', $existing->id)
                    ->update(array_merge($payload, ['updated_at' => now()]));
                $tenantId = (string) $existing->id;
            } else {
                $tenantId = (string) Str::uuid();
                DB::connection($connection)
                    ->table('tenants')
                    ->insert(array_merge($payload, [
                        'id' => $tenantId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]));
            }

            if ($hasDataColumn) {
                $existingData = DB::connection($connection)
                    ->table('tenants')
                    ->where('id', $tenantId)
                    ->value('data');
                $decodedData = is_string($existingData)
                    ? (json_decode($existingData, true) ?: [])
                    : ((array) $existingData);

                $newData = array_merge($decodedData, [
                    'city' => $tenant['city'],
                    'map_center_lat' => (float) $tenant['map_center_lat'],
                    'map_center_lng' => (float) $tenant['map_center_lng'],
                ]);

                DB::connection($connection)
                    ->table('tenants')
                    ->where('id', $tenantId)
                    ->update([
                        'data' => json_encode($newData),
                        'updated_at' => now(),
                    ]);
            }
        }
    }
}

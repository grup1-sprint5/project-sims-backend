<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

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

        foreach ($tenants as $tenant) {
            $payload = $tenant;
            $payload['id'] = $tenant['slug'];
            unset($payload['city'], $payload['map_center_lat'], $payload['map_center_lng']);
            unset($payload['slug']);

            $exists = DB::table('tenants')->where('id', $payload['id'])->exists();
            if ($exists) {
                DB::table('tenants')->where('id', $payload['id'])->update(array_merge($payload, [
                    'updated_at' => now(),
                ]));
            } else {
                DB::table('tenants')->insert(array_merge($payload, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }

            $existingData = DB::table('tenants')->where('id', $payload['id'])->value('data');
            $decodedData = is_string($existingData) ? (json_decode($existingData, true) ?: []) : ((array) $existingData);

            $newData = array_merge($decodedData, [
                'city' => $tenant['city'],
                'map_center_lat' => (float) $tenant['map_center_lat'],
                'map_center_lng' => (float) $tenant['map_center_lng'],
            ]);

            DB::table('tenants')
                ->where('id', $payload['id'])
                ->update(['data' => json_encode($newData)]);
        }
    }
}

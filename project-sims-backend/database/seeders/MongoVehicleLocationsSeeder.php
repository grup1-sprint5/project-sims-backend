<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MongoVehicleLocationsSeeder extends Seeder
{
    public function run()
    {
        $locations = [
            [
                'license_plate' => 'ABC123',
                'latitude' => 40.7361,
                'longitude' => 0.5170,
                'active' => true,
            ],
            [
                'license_plate' => 'DEF456',
                'latitude' => 40.7370,
                'longitude' => 0.5185,
                'active' => false,
            ],
            [
                'license_plate' => 'GHI789',
                'latitude' => 40.7350,
                'longitude' => 0.5150,
                'active' => true,
            ],
        ];

        // Inserta/actualiza por license_plate
        $connection = DB::connection('mongodb');
        foreach ($locations as $loc) {
            $connection->table('vehicle_locations')->updateOrInsert(
                ['license_plate' => $loc['license_plate']],
                $loc
            );
        }
    }
}

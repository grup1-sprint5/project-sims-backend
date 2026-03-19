<?php

namespace Database\Seeders;

use App\Models\Vehicle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MongoVehicleLocationsSeeder extends Seeder
{
    public function run()
    {
        $connection = DB::connection('mongodb');

        $vehicles = Vehicle::query()
            ->select(['id', 'license_plate'])
            ->orderBy('id')
            ->limit(5)
            ->get();

        if ($vehicles->isEmpty()) {
            return;
        }

        $connection->table('vehicle_locations')->delete();

        $ampostaCoordinates = [
            ['latitude' => 40.709950, 'longitude' => 0.579650],
            ['latitude' => 40.715850, 'longitude' => 0.585050],
            ['latitude' => 40.703850, 'longitude' => 0.573750],
            ['latitude' => 40.711750, 'longitude' => 0.567250],
            ['latitude' => 40.706900, 'longitude' => 0.590350],
        ];

        foreach ($vehicles as $index => $vehicle) {
            $point = $ampostaCoordinates[$index] ?? $ampostaCoordinates[array_key_last($ampostaCoordinates)];

            $location = [
                'vehicle_id' => $vehicle->id,
                'license_plate' => $vehicle->license_plate,
                'latitude' => (float) $point['latitude'],
                'longitude' => (float) $point['longitude'],
                'active' => false,
            ];

            $connection->table('vehicle_locations')->updateOrInsert(
                ['license_plate' => $vehicle->license_plate],
                $location
            );
        }
    }
}

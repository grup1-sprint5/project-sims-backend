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
            ->get();

        if ($vehicles->isEmpty()) {
            return;
        }

        $baseLat = 40.7095;
        $baseLng = 0.5795;

        foreach ($vehicles as $index => $vehicle) {
            $existing = $connection->table('vehicle_locations')
                ->where('license_plate', $vehicle->license_plate)
                ->orWhere('licensePlate', $vehicle->license_plate)
                ->first();

            $latOffset = (($index % 5) - 2) * 0.0012;
            $lngOffset = (int) floor($index / 5) * 0.0015;

            $location = [
                'vehicle_id' => $vehicle->id,
                'license_plate' => $vehicle->license_plate,
                'latitude' => isset($existing->latitude)
                    ? (float) $existing->latitude
                    : round($baseLat + $latOffset, 6),
                'longitude' => isset($existing->longitude)
                    ? (float) $existing->longitude
                    : round($baseLng + $lngOffset, 6),
                'active' => isset($existing->active) ? (bool) $existing->active : false,
            ];

            $connection->table('vehicle_locations')->updateOrInsert(
                ['license_plate' => $vehicle->license_plate],
                $location
            );
        }
    }
}

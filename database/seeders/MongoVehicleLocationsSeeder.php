<?php

namespace Database\Seeders;

use App\Models\Vehicle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MongoVehicleLocationsSeeder extends Seeder
{
    public function run()
    {
        $tenantId = function_exists('tenant') && tenant() ? (string) tenant('id') : null;
        if (!$tenantId) {
            return;
        }

        $connection = DB::connection('mongodb');

        $vehicleLimitByTenant = [
            'sims-corp' => 5,
            'ecomove' => 4,
        ];

        $vehicleLimit = $vehicleLimitByTenant[$tenantId] ?? 5;

        $vehicles = Vehicle::query()
            ->select(['id', 'license_plate'])
            ->orderBy('id')
            ->limit($vehicleLimit)
            ->get();

        if ($vehicles->isEmpty()) {
            return;
        }

        $connection->table('vehicle_locations')
            ->where('tenant_id', $tenantId)
            ->delete();

        $connection->table('vehicle_locations')
            ->whereNull('tenant_id')
            ->delete();

        $coordinatesByTenant = [
            'sims-corp' => [
                ['latitude' => 40.709950, 'longitude' => 0.579650],
                ['latitude' => 40.715850, 'longitude' => 0.585050],
                ['latitude' => 40.703850, 'longitude' => 0.573750],
                ['latitude' => 40.711750, 'longitude' => 0.567250],
                ['latitude' => 40.706900, 'longitude' => 0.590350],
            ],
            'ecomove' => [
                ['latitude' => 40.620900, 'longitude' => 0.592800],
                ['latitude' => 40.616200, 'longitude' => 0.598900],
                ['latitude' => 40.627700, 'longitude' => 0.603300],
                ['latitude' => 40.623300, 'longitude' => 0.585100],
                ['latitude' => 40.613800, 'longitude' => 0.590200],
            ],
        ];

        $coordinates = $coordinatesByTenant[$tenantId] ?? $coordinatesByTenant['sims-corp'];

        foreach ($vehicles as $index => $vehicle) {
            $point = $coordinates[$index] ?? $coordinates[array_key_last($coordinates)];

            $location = [
                'tenant_id' => $tenantId,
                'vehicle_id' => $vehicle->id,
                'license_plate' => $vehicle->license_plate,
                'latitude' => (float) $point['latitude'],
                'longitude' => (float) $point['longitude'],
                'active' => false,
            ];

            $connection->table('vehicle_locations')->updateOrInsert(
                ['tenant_id' => $tenantId, 'license_plate' => $vehicle->license_plate],
                $location
            );
        }
    }
}

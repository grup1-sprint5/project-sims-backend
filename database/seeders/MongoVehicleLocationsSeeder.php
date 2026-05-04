<?php

namespace Database\Seeders;

use App\Models\Vehicle;
use App\Services\VehicleLocationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MongoVehicleLocationsSeeder extends Seeder
{
    private VehicleLocationService $locationService;

    public function __construct(VehicleLocationService $locationService)
    {
        $this->locationService = $locationService;
    }

    public function run()
    {
        $tenantSlug = function_exists('tenant') && tenant() ? (string) tenant()->slug : null;
        if (!$tenantSlug) {
            echo "⚠️  No tenant context initialized. Skipping MongoVehicleLocationsSeeder.\n";
            return;
        }

        $vehicleLimitByTenant = [
            'sims-corp' => 5,
            'ecomove' => 4,
        ];

        $vehicleLimit = $vehicleLimitByTenant[$tenantSlug] ?? 5;

        $vehicles = Vehicle::query()
            ->select(['id', 'license_plate'])
            ->orderBy('id')
            ->limit($vehicleLimit)
            ->get();

        if ($vehicles->isEmpty()) {
            echo "⚠️  No vehicles found for tenant {$tenantSlug}. Skipping MongoVehicleLocationsSeeder.\n";
            return;
        }

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

        $coordinates = $coordinatesByTenant[$tenantSlug] ?? $coordinatesByTenant['sims-corp'];

        foreach ($vehicles as $index => $vehicle) {
            $point = $coordinates[$index] ?? $coordinates[array_key_last($coordinates)];

            // This service method now handles both MongoDB and PostgreSQL fallback
            $this->locationService->upsertLocation(
                $vehicle,
                (float) $point['latitude'],
                (float) $point['longitude'],
                false // active
            );
        }

        echo "✅ Vehicle locations seeded for tenant {$tenantSlug} (MongoDB if available + SQL fallback populated)!\n";
    }
}

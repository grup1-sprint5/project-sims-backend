<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class VehicleLocationService
{
    /**
     * Obtiene las ubicaciones de los vehículos desde MongoDB Atlas
     * Devuelve un array indexado por license_plate
     */
    public function getLocations(): array
    {
        $locations = DB::connection('mongodb')
            ->table('vehicle_locations')
            ->get();

        $result = [];
        foreach ($locations as $location) {
            $licensePlate = $location->license_plate ?? $location->licensePlate ?? null;
            if ($licensePlate) {
                $result[$licensePlate] = [
                    'latitude' => (float) ($location->latitude ?? $location->lat ?? 0),
                    'longitude' => (float) ($location->longitude ?? $location->lng ?? $location->lon ?? 0),
                    'active' => isset($location->active) ? (bool) $location->active : null,
                ];
            }
        }

        return $result;
    }

    /**
     * Obtiene la ubicación de un vehículo específico por matrícula
     */
    public function getLocationByPlate(string $licensePlate): ?array
    {
        $location = DB::connection('mongodb')
            ->table('vehicle_locations')
            ->where('license_plate', $licensePlate)
            ->orWhere('licensePlate', $licensePlate)
            ->first();

        if (!$location) {
            return null;
        }

        return [
            'latitude' => (float) ($location->latitude ?? $location->lat ?? 0),
            'longitude' => (float) ($location->longitude ?? $location->lng ?? $location->lon ?? 0),
            'active' => isset($location->active) ? (bool) $location->active : null,
        ];
    }
}

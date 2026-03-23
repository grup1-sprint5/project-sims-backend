<?php

namespace App\Services;

use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;

class VehicleLocationService
{
    /**
     * Obtiene las ubicaciones de los vehículos desde MongoDB Atlas
     * Devuelve un array indexado por license_plate
     */
    public function getLocations(): array
    {
        $tenantId = function_exists('tenant') && tenant() ? (string) tenant('id') : null;

        $query = DB::connection('mongodb')
            ->table('vehicle_locations');

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $locations = $query->get();

        $tenantVehiclePlates = null;
        if ($tenantId) {
            $tenantVehiclePlates = array_flip(
                Vehicle::query()->pluck('license_plate')->all()
            );
        }

        $result = [];
        foreach ($locations as $location) {
            $licensePlate = $location->license_plate ?? $location->licensePlate ?? null;
            if ($licensePlate) {
                if ($tenantVehiclePlates !== null && !isset($tenantVehiclePlates[$licensePlate])) {
                    continue;
                }

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
        $tenantId = function_exists('tenant') && tenant() ? (string) tenant('id') : null;

        $query = DB::connection('mongodb')
            ->table('vehicle_locations')
            ->where(function ($q) use ($licensePlate) {
                $q->where('license_plate', $licensePlate)
                  ->orWhere('licensePlate', $licensePlate);
            });

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $location = $query->first();

        if (!$location) {
            return null;
        }

        return [
            'latitude' => (float) ($location->latitude ?? $location->lat ?? 0),
            'longitude' => (float) ($location->longitude ?? $location->lng ?? $location->lon ?? 0),
            'active' => isset($location->active) ? (bool) $location->active : null,
        ];
    }

    /**
     * Crea o actualiza la ubicación de un vehículo en Mongo.
     */
    public function upsertLocation(Vehicle $vehicle, float $latitude, float $longitude, bool $active = false): void
    {
        $tenantId = function_exists('tenant') && tenant() ? (string) tenant('id') : null;

        $payload = [
            'tenant_id' => $tenantId,
            'vehicle_id' => $vehicle->id,
            'license_plate' => $vehicle->license_plate,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'active' => $active,
        ];

        DB::connection('mongodb')
            ->table('vehicle_locations')
            ->updateOrInsert(
                ['tenant_id' => $tenantId, 'license_plate' => $vehicle->license_plate],
                $payload
            );
    }

    /**
     * Elimina la ubicación de un vehículo por matrícula en el tenant actual.
     */
    public function deleteLocationByPlate(string $licensePlate): void
    {
        $tenantId = function_exists('tenant') && tenant() ? (string) tenant('id') : null;

        DB::connection('mongodb')
            ->table('vehicle_locations')
            ->where('tenant_id', $tenantId)
            ->where('license_plate', $licensePlate)
            ->delete();
    }
}

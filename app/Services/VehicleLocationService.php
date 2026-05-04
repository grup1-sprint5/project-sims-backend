<?php

namespace App\Services;

use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VehicleLocationService
{
    /**
     * Check if MongoDB is available. If not, fallback to PostgreSQL for locations.
     */
    private function canUseMongo(): bool
    {
        // Don't even try if the URI is missing (saves time and errors)
        if (!config('database.connections.mongodb.dsn') && !env('MONGODB_URI')) {
            return false;
        }

        try {
            // Check connection by pinging or simple operation
            DB::connection('mongodb')->getMongoClient();
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Obtiene las ubicaciones de los vehículos.
     * Si Mongo falla, intenta leer de PostgreSQL como fallback.
     */
    public function getLocations(): array
    {
        $tenantId = function_exists('tenant') && tenant() ? (string) tenant()->id : null;
        
        $locations = [];

        if ($this->canUseMongo()) {
            try {
                $locations = DB::connection('mongodb')
                    ->table('vehicle_locations')
                    ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
                    ->get();
            } catch (\Throwable $e) {
                Log::warning('MongoDB fails in getLocations, falling back to SQL: ' . $e->getMessage());
                $locations = $this->getSqlLocations();
            }
        } else {
            $locations = $this->getSqlLocations();
        }

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

    private function getSqlLocations()
    {
        try {
            return DB::table('vehicle_locations')->get();
        } catch (\Throwable $e) {
            Log::error('SQL Fallback also failed in getLocations: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene la ubicación de un vehículo específico por matrícula.
     */
    public function getLocationByPlate(string $licensePlate): ?array
    {
        $tenantId = function_exists('tenant') && tenant() ? (string) tenant()->id : null;

        $location = null;

        if ($this->canUseMongo()) {
            try {
                $location = DB::connection('mongodb')
                    ->table('vehicle_locations')
                    ->where(function ($q) use ($licensePlate) {
                        $q->where('license_plate', $licensePlate)
                          ->orWhere('licensePlate', $licensePlate);
                    })
                    ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
                    ->first();
            } catch (\Throwable $e) {
                $location = $this->getSqlLocationByPlate($licensePlate);
            }
        } else {
            $location = $this->getSqlLocationByPlate($licensePlate);
        }

        if (!$location) return null;

        return [
            'latitude' => (float) ($location->latitude ?? $location->lat ?? 0),
            'longitude' => (float) ($location->longitude ?? $location->lng ?? $location->lon ?? 0),
            'active' => isset($location->active) ? (bool) $location->active : null,
        ];
    }

    private function getSqlLocationByPlate(string $licensePlate)
    {
        try {
            return DB::table('vehicle_locations')
                ->where('license_plate', $licensePlate)
                ->first();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Crea o actualiza la ubicación en Mongo y SQL (para asegurar fallback).
     */
    public function upsertLocation(Vehicle $vehicle, float $latitude, float $longitude, bool $active = false): void
    {
        $tenantId = function_exists('tenant') && tenant() ? (string) tenant()->id : null;

        $payload = [
            'vehicle_id' => $vehicle->id,
            'license_plate' => $vehicle->license_plate,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'active' => $active,
            'updated_at' => now(),
        ];

        // Try Mongo
        if ($this->canUseMongo()) {
            try {
                DB::connection('mongodb')
                    ->table('vehicle_locations')
                    ->updateOrInsert(
                        ['tenant_id' => $tenantId, 'license_plate' => $vehicle->license_plate],
                        array_merge($payload, ['tenant_id' => $tenantId])
                    );
            } catch (\Throwable $e) {
                Log::warning('MongoDB fails in upsertLocation: ' . $e->getMessage());
            }
        }

        // ALWAYS sync with SQL as a fallback
        try {
            $sqlPayload = $payload;
            $sqlPayload['created_at'] = now();
            
            DB::table('vehicle_locations')->updateOrInsert(
                ['license_plate' => $vehicle->license_plate],
                $sqlPayload
            );
        } catch (\Throwable $e) {
            Log::error('SQL upsertLocation failed: ' . $e->getMessage());
        }
    }

    /**
     * Elimina la ubicación.
     */
    public function deleteLocationByPlate(string $licensePlate): void
    {
        $tenantId = function_exists('tenant') && tenant() ? (string) tenant()->id : null;

        // Mongo
        if ($this->canUseMongo()) {
            try {
                DB::connection('mongodb')
                    ->table('vehicle_locations')
                    ->where('tenant_id', $tenantId)
                    ->where('license_plate', $licensePlate)
                    ->delete();
            } catch (\Throwable $e) {}
        }

        // SQL (Fallback)
        try {
            DB::table('vehicle_locations')
                ->where('license_plate', $licensePlate)
                ->delete();
        } catch (\Throwable $e) {}
    }
}

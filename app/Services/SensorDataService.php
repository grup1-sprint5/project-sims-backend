<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class SensorDataService
{
    private function iotBaseUrl(): string
    {
        $base = env('IOT_MICROSERVICE_URL')
            ?? env('IOT_API_URL')
            ?? 'http://host.docker.internal:8002';

        return rtrim((string) $base, '/');
    }

    private function iotTimeoutSeconds(): int
    {
        return (int) (env('IOT_TIMEOUT', 3));
    }

    /**
     * Fa una petició al microservei IoT (FastAPI) i retorna el JSON.
     */
    private function iotGet(string $path, array $query = []): array
    {
        $url = $this->iotBaseUrl() . $path;

        $res = Http::timeout($this->iotTimeoutSeconds())
            ->acceptJson()
            ->get($url, $query);

        if (!$res->successful()) {
            return [
                'success' => false,
                'message' => $res->body() ?: ('IoT microservice error (' . $res->status() . ')'),
                'status'  => $res->status(),
            ];
        }

        return (array) $res->json();
    }

    /**
     * Obté les últimes lectures de sensors des de MongoDB.
     *
     * @param  int         $limit       Nombre màxim de resultats (per defecte 50)
     * @param  string|null $deviceId    Filtra per device_id (opcional)
     * @param  string|null $sensorType  Filtra per sensor_type (opcional)
     * @return array
     */
    public function getReadings(int $limit = 50, ?string $deviceId = null, ?string $sensorType = null): array
    {
        // Arquitectura del sprint: Laravel consumeix el microservei IoT via API.
        // Això evita inconsistències entre el Mongo local del docker-compose i MongoDB Atlas.

        $query = ['limit' => $limit];
        if ($deviceId) {
            $query['device_id'] = $deviceId;
        }
        if ($sensorType) {
            $query['sensor_type'] = $sensorType;
        }

        $json = $this->iotGet('/api/sensor-data', $query);
        $readings = $json['data']['readings'] ?? [];

        return is_array($readings) ? $readings : [];
    }

    /**
     * Obté els device_ids únics que han enviat lectures.
     *
     * @return array
     */
    public function getDeviceIds(): array
    {
        $json = $this->iotGet('/api/sensor-data/devices');

        // FastAPI retorna: data: { devices: string[], total: number }
        $devices = $json['data']['devices'] ?? $json['data'] ?? [];
        if (!is_array($devices)) {
            return [];
        }

        // Normalitza i filtra valors buits
        $devices = array_values(array_filter(array_map(fn($d) => is_string($d) ? $d : null, $devices)));
        sort($devices);

        return $devices;
    }

    /**
     * Obté l'última lectura d'un dispositiu concret.
     *
     * @param  string $deviceId
     * @return array|null
     */
    public function getLatestByDevice(string $deviceId): ?array
    {
        $encoded = rawurlencode($deviceId);
        $json = $this->iotGet('/api/sensor-data/device/' . $encoded . '/latest');

        $reading = $json['data'] ?? null;
        return is_array($reading) ? $reading : null;
    }
}

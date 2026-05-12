<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class SensorDataService
{
    private function iotBaseUrl(): string
    {
        $base = config('services.iot.url', 'http://iot_api:8000');

        if (in_array($base, ['http://host.docker.internal:8002', 'http://host.docker.internal:8000'], true)) {
            $base = 'http://iot_api:8000';
        }

        return rtrim((string) $base, '/');
    }

    private function iotTimeoutSeconds(): int
    {
        return (int) config('services.iot.timeout', 3);
    }

    /**
     * Fa una petició al microservei IoT (FastAPI) i retorna el JSON.
     */
    private function iotGet(string $path, array $query = []): array
    {
        $url = $this->iotBaseUrl() . $path;

        try {
            $res = Http::timeout($this->iotTimeoutSeconds())
                ->acceptJson()
                ->get($url, $query);
        } catch (ConnectionException $e) {
            return [
                'success' => false,
                'message' => 'IoT microservice unreachable: ' . $e->getMessage(),
                'status' => 503,
            ];
        }

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
     * Fa una petició POST al microservei IoT (FastAPI) i retorna el JSON.
     */
    private function iotPost(string $path, array $payload = []): array
    {
        $url = $this->iotBaseUrl() . $path;

        try {
            $res = Http::timeout($this->iotTimeoutSeconds())
                ->acceptJson()
                ->post($url, $payload);
        } catch (ConnectionException $e) {
            return [
                'success' => false,
                'message' => 'IoT microservice unreachable: ' . $e->getMessage(),
                'status' => 503,
            ];
        }

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

    /**
     * Consulta l'estat actual de l'actuador (LED) via microservei IoT.
     */
    public function getActuatorStatus(): array
    {
        $json = $this->iotGet('/api/actuator/status');

        return [
            'success' => (bool) ($json['success'] ?? false),
            'message' => (string) ($json['message'] ?? ''),
            'current_state' => $json['current_state'] ?? null,
        ];
    }

    /**
     * Envia una ordre ON/OFF a l'actuador (LED) via microservei IoT.
     */
    public function setActuatorState(string $state): array
    {
        $normalizedState = strtoupper(trim($state));
        if (!in_array($normalizedState, ['ON', 'OFF'], true)) {
            return [
                'success' => false,
                'message' => "state must be 'ON' or 'OFF'",
                'current_state' => null,
            ];
        }

        $json = $this->iotPost('/api/actuator', [
            'state' => $normalizedState,
        ]);

        return [
            'success' => (bool) ($json['success'] ?? false),
            'message' => (string) ($json['message'] ?? ''),
            'current_state' => $json['current_state'] ?? null,
        ];
    }
}

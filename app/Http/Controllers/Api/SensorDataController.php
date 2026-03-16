<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SensorDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SensorDataController extends Controller
{
    public function __construct(private readonly SensorDataService $sensorDataService) {}

    /**
     * GET /api/sensor-data
     *
     * Retorna les últimes lectures de sensors des de MongoDB.
     * Paràmetres opcionals de query:
     *   - limit        (int, per defecte 50, màx 500)
     *   - device_id    (string)
     *   - sensor_type  (string)
     */
    public function index(Request $request): JsonResponse
    {
        $limit      = (int) $request->query('limit', 50);
        $limit      = min(max($limit, 1), 500);
        $deviceId   = $request->query('device_id');
        $sensorType = $request->query('sensor_type');

        $readings = $this->sensorDataService->getReadings($limit, $deviceId, $sensorType);

        return response()->json([
            'success' => true,
            'data'    => [
                'readings' => $readings,
                'total'    => count($readings),
            ],
        ]);
    }

    /**
     * GET /api/sensor-data/devices
     *
     * Retorna la llista de device_ids únics que han enviat lectures.
     */
    public function devices(): JsonResponse
    {
        $devices = $this->sensorDataService->getDeviceIds();

        return response()->json([
            'success' => true,
            'data'    => $devices,
        ]);
    }

    /**
     * GET /api/sensor-data/devices/{deviceId}/latest
     *
     * Retorna l'última lectura d'un dispositiu concret.
     */
    public function latestByDevice(string $deviceId): JsonResponse
    {
        $reading = $this->sensorDataService->getLatestByDevice($deviceId);

        if (!$reading) {
            return response()->json([
                'success' => false,
                'message' => "No s'han trobat lectures per al dispositiu '{$deviceId}'",
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $reading,
        ]);
    }
}

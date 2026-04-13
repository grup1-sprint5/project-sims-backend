<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SensorDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActuatorController extends Controller
{
    public function __construct(private readonly SensorDataService $sensorDataService) {}

    /**
     * GET /api/actuator/status
     *
     * Retorna l'estat actual del LED.
     */
    public function status(): JsonResponse
    {
        $result = $this->sensorDataService->getActuatorStatus();

        return response()->json([
            'success' => (bool) ($result['success'] ?? false),
            'message' => $result['message'] ?? 'Actuator status unavailable',
            'data' => [
                'current_state' => $result['current_state'] ?? null,
            ],
        ], (bool) ($result['success'] ?? false) ? 200 : 502);
    }

    /**
     * POST /api/actuator
     *
     * Rep una ordre ON/OFF i la reenvia al microservei IoT.
     */
    public function setState(Request $request): JsonResponse
    {
        $state = strtoupper((string) $request->input('state', ''));

        if (!in_array($state, ['ON', 'OFF'], true)) {
            return response()->json([
                'success' => false,
                'message' => "state must be 'ON' or 'OFF'",
            ], 422);
        }

        $result = $this->sensorDataService->setActuatorState($state);

        return response()->json([
            'success' => (bool) ($result['success'] ?? false),
            'message' => $result['message'] ?? 'Actuator command failed',
            'data' => [
                'current_state' => $result['current_state'] ?? null,
            ],
        ], (bool) ($result['success'] ?? false) ? 200 : 502);
    }
}

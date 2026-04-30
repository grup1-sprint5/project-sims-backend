<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Geofence\IngestVehiclePositionRequest;
use App\Models\Vehicle;
use App\Services\Geofencing\GeofenceEvaluatorService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

class VehiclePositionController extends Controller
{
    public function __construct(private readonly GeofenceEvaluatorService $evaluatorService)
    {
    }

    public function store(IngestVehiclePositionRequest $request): JsonResponse
    {
        $vehicle = Vehicle::query()->findOrFail((int) $request->validated('vehicle_id'));
        $this->authorize('update', $vehicle);

        $events = $this->evaluatorService->processVehiclePosition(
            $vehicle,
            (float) $request->validated('lat'),
            (float) $request->validated('lng'),
            CarbonImmutable::parse((string) $request->validated('timestamp')),
            (array) $request->validated('metadata', [])
        );

        return response()->json([
            'message' => 'Position processed successfully.',
            'data' => [
                'events_count' => count($events),
                'events' => collect($events)->map(fn ($event) => [
                    'id' => $event->id,
                    'geofence_id' => $event->geofence_id,
                    'vehicle_id' => $event->vehicle_id,
                    'event_type' => $event->event_type,
                    'position' => [
                        'lat' => $event->position_lat,
                        'lng' => $event->position_lng,
                    ],
                    'occurred_at' => optional($event->occurred_at)->toISOString(),
                    'metadata' => $event->metadata,
                ])->values(),
            ],
        ], 202);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GeofenceEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GeofenceEventController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', GeofenceEvent::class);

        $query = GeofenceEvent::query()
            ->with(['geofence:id,name', 'vehicle:id,license_plate'])
            ->when(!$request->user()->isSuperAdmin(), fn ($q) => $q->where('tenant_id', (string) $request->user()->tenant_id));

        if ($request->filled('vehicle_id')) {
            $query->where('vehicle_id', (int) $request->input('vehicle_id'));
        }

        if ($request->filled('geofence_id')) {
            $query->where('geofence_id', (string) $request->input('geofence_id'));
        }

        if ($request->filled('event_type')) {
            $query->where('event_type', (string) $request->input('event_type'));
        }

        if ($request->filled('from')) {
            $query->where('occurred_at', '>=', $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->where('occurred_at', '<=', $request->input('to'));
        }

        return response()->json(
            $query->orderByDesc('occurred_at')->paginate((int) $request->input('per_page', 50))
        );
    }
}

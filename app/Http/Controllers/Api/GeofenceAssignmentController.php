<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Geofence\StoreGeofenceAssignmentRequest;
use App\Models\Geofence;
use App\Models\GeofenceAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GeofenceAssignmentController extends Controller
{
    public function store(StoreGeofenceAssignmentRequest $request, Geofence $geofence): JsonResponse
    {
        $this->authorize('update', $geofence);

        if (!$request->user()->isSuperAdmin() && $geofence->tenant_id !== (string) $request->user()->tenant_id) {
            abort(404);
        }

        $data = $request->validated();

        $assignment = GeofenceAssignment::query()->firstOrCreate([
            'tenant_id' => (string) $geofence->tenant_id,
            'geofence_id' => $geofence->id,
            'assign_type' => $data['assign_type'],
            'assign_id' => (string) $data['assign_id'],
        ]);

        return response()->json([
            'message' => 'Assignment created successfully.',
            'data' => $assignment,
        ], 201);
    }

    public function destroy(Request $request, Geofence $geofence, int $assignmentId): JsonResponse
    {
        $this->authorize('update', $geofence);

        if (!$request->user()->isSuperAdmin() && $geofence->tenant_id !== (string) $request->user()->tenant_id) {
            abort(404);
        }

        $assignment = $geofence->assignments()->where('id', $assignmentId)->firstOrFail();
        $assignment->delete();

        return response()->json([
            'message' => 'Assignment deleted successfully.',
        ]);
    }
}

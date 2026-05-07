<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Geofence\StoreGeofenceAssignmentRequest;
use App\Models\Geofence;
use App\Models\GeofenceAssignment;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GeofenceAssignmentController extends Controller
{
    public function store(StoreGeofenceAssignmentRequest $request, $geofence): JsonResponse
    {
        $crossTenantSuperAdmin = $this->isCrossTenantSuperAdminRequest($request);
        $geofence = $this->resolveGeofenceForRequest($request, $geofence);

        if (!$crossTenantSuperAdmin) {
            $this->authorize('update', $geofence);
        }

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

    public function destroy(Request $request, $geofence, int $assignmentId): JsonResponse
    {
        $crossTenantSuperAdmin = $this->isCrossTenantSuperAdminRequest($request);
        $geofence = $this->resolveGeofenceForRequest($request, $geofence);

        if (!$crossTenantSuperAdmin) {
            $this->authorize('update', $geofence);
        }

        if (!$request->user()->isSuperAdmin() && $geofence->tenant_id !== (string) $request->user()->tenant_id) {
            abort(404);
        }

        $assignment = $geofence->assignments()->where('id', $assignmentId)->firstOrFail();
        $assignment->delete();

        return response()->json([
            'message' => 'Assignment deleted successfully.',
        ]);
    }

    private function resolveGeofenceForRequest(Request $request, string|Geofence $id): Geofence
    {
        if ($id instanceof Geofence) {
            return $id;
        }

        if ($this->isCrossTenantSuperAdminRequest($request)) {
            $tenant = Tenant::findOrFail((string) $request->query('tenant_id'));
            tenancy()->initialize($tenant);
        }

        return Geofence::findOrFail($id);
    }

    private function isCrossTenantSuperAdminRequest(Request $request): bool
    {
        $user = $request->user();

        return $request->filled('tenant_id') && $user && $user->isSuperAdmin();
    }
}

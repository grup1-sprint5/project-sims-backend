<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Geofence\StoreGeofenceRequest;
use App\Http\Requests\Geofence\UpdateGeofenceRequest;
use App\Models\Geofence;
use App\Services\Geofencing\GeofenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GeofenceController extends Controller
{
    public function __construct(private readonly GeofenceService $geofenceService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Geofence::class);

        $user = $request->user();

        $query = Geofence::query()
            ->with('assignments')
            ->when(!$user->isSuperAdmin(), fn ($q) => $q->where('tenant_id', (string) $user->tenant_id));

        if ($request->has('active')) {
            $query->where('active', filter_var($request->input('active'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('name')) {
            $name = trim((string) $request->input('name'));
            $query->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($name) . '%']);
        }

        if ($request->filled('assign_type') || $request->filled('assign_id')) {
            $assignType = $request->input('assign_type');
            $assignId = $request->input('assign_id');

            $query->whereHas('assignments', function ($assignmentQuery) use ($assignType, $assignId): void {
                if ($assignType) {
                    $assignmentQuery->where('assign_type', (string) $assignType);
                }
                if ($assignId) {
                    $assignmentQuery->where('assign_id', (string) $assignId);
                }
            });
        }

        $rows = $query->orderByDesc('created_at')->paginate((int) $request->input('per_page', 15));

        return response()->json($rows);
    }

    public function store(StoreGeofenceRequest $request): JsonResponse
    {
        $this->authorize('create', Geofence::class);

        $geofence = $this->geofenceService->createFromPayload($request->validated(), $request->user());

        return response()->json([
            'message' => 'Geofence created successfully.',
            'data' => $geofence->load('assignments'),
        ], 201);
    }

    public function show(Request $request, Geofence $geofence): JsonResponse
    {
        $this->authorize('view', $geofence);

        if (!$request->user()->isSuperAdmin() && $geofence->tenant_id !== (string) $request->user()->tenant_id) {
            abort(404);
        }

        return response()->json([
            'data' => $geofence->load('assignments'),
        ]);
    }

    public function update(UpdateGeofenceRequest $request, Geofence $geofence): JsonResponse
    {
        $this->authorize('update', $geofence);

        if (!$request->user()->isSuperAdmin() && $geofence->tenant_id !== (string) $request->user()->tenant_id) {
            abort(404);
        }

        $updated = $this->geofenceService->updateFromPayload($geofence, $request->validated());

        return response()->json([
            'message' => 'Geofence updated successfully.',
            'data' => $updated->load('assignments'),
        ]);
    }

    public function destroy(Request $request, Geofence $geofence): JsonResponse
    {
        $this->authorize('delete', $geofence);

        if (!$request->user()->isSuperAdmin() && $geofence->tenant_id !== (string) $request->user()->tenant_id) {
            abort(404);
        }

        $geofence->delete();

        return response()->json([
            'message' => 'Geofence deleted successfully.',
        ]);
    }
}

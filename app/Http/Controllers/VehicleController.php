<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use App\Http\Requests\Vehicle\StoreVehicleRequest;
use App\Http\Requests\Vehicle\UpdateVehicleRequest;
use App\Services\VehicleLocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VehicleController extends Controller
{
    private VehicleLocationService $locationService;

    public function __construct(VehicleLocationService $locationService) {
        $this->locationService = $locationService;
    }

    /**
     * Lista paginada de vehículos con filtros
     * 
     * Filtros disponibles:
     * - search: busca por license_plate, brand o model
     * - license_plate: filtro exacto por matrícula
     * - brand: filtro por marca
     * - model: filtro por modelo
     * - active: filtro por estado activo (true/false)
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $query = Vehicle::query();

        // Búsqueda general por license_plate, brand o model
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('license_plate', 'ILIKE', "%{$search}%")
                  ->orWhere('brand', 'ILIKE', "%{$search}%")
                  ->orWhere('model', 'ILIKE', "%{$search}%");
            });
        }

        // Filtros específicos
        if ($request->filled('license_plate')) {
            $query->where('license_plate', 'ILIKE', "%{$request->input('license_plate')}%");
        }

        if ($request->filled('brand')) {
            $query->where('brand', 'ILIKE', "%{$request->input('brand')}%");
        }

        if ($request->filled('model')) {
            $query->where('model', 'ILIKE', "%{$request->input('model')}%");
        }

        if ($request->has('active')) {
            $query->where('active', filter_var($request->input('active'), FILTER_VALIDATE_BOOLEAN));
        }

        $vehicles = $query
            ->withCount([
                'reservations as active_reservations_count' => function ($q) {
                    $q->whereIn('status', ['pending', 'active', 'confirmed']);
                }
            ])
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 15));

        $locations = $this->locationService->getLocations();

        // Attach latitude/longitude (and mongo_active) from Mongo to each vehicle in the paginated collection
        $vehicles->getCollection()->transform(function ($vehicle) use ($locations) {
            $location = $locations[$vehicle->license_plate] ?? null;
            $hasActiveReservation = ((int) ($vehicle->active_reservations_count ?? 0)) > 0;
            $mongoRunning = $hasActiveReservation && (($location['active'] ?? false) === true);
            $effectiveStatus = $mongoRunning ? 'running' : ($hasActiveReservation ? 'occupied' : 'available');

            // Use setAttribute so the values are included when the Eloquent models are serialized to JSON
            $vehicle->setAttribute('latitude', $location['latitude'] ?? null);
            $vehicle->setAttribute('longitude', $location['longitude'] ?? null);
            $vehicle->setAttribute('mongo_active', $mongoRunning);
            $vehicle->setAttribute('postgres_active', $hasActiveReservation);
            $vehicle->setAttribute('status', $effectiveStatus);
            return $vehicle;
        });

        return response()->json($vehicles);
    }

    /**
     * Crear un nuevo vehículo
     */
    public function store(StoreVehicleRequest $request): JsonResponse
    {
        $this->authorize('create', Vehicle::class);

        $data = $request->validated();
        $latitude = array_key_exists('latitude', $data) ? $data['latitude'] : null;
        $longitude = array_key_exists('longitude', $data) ? $data['longitude'] : null;

        unset($data['latitude'], $data['longitude']);
        
        // Assign tenant_id from authenticated user
        $data['tenant_id'] = $request->user()->tenant_id;

        $vehicle = Vehicle::create($data);

        if ($latitude !== null && $longitude !== null) {
            $this->locationService->upsertLocation(
                $vehicle,
                (float) $latitude,
                (float) $longitude,
                false
            );
            $vehicle->setAttribute('latitude', (float) $latitude);
            $vehicle->setAttribute('longitude', (float) $longitude);
        }

        return response()->json([
            'message' => 'Vehículo creado correctamente',
            'data' => $vehicle,
        ], 201);
    }

    /**
     * Mostrar un vehículo específico
     */
    public function show(Vehicle $vehicle): JsonResponse
    {
        $this->authorize('view', $vehicle);

        return response()->json([
            'data' => $vehicle,
        ]);
    }

    /**
     * Actualizar un vehículo
     */
    public function update(UpdateVehicleRequest $request, Vehicle $vehicle): JsonResponse
    {
        $this->authorize('update', $vehicle);

        $data = $request->validated();

        $oldPlate = $vehicle->license_plate;
        $oldLocation = $this->locationService->getLocationByPlate($oldPlate);

        $hasLatitude = array_key_exists('latitude', $data);
        $hasLongitude = array_key_exists('longitude', $data);
        $latitude = $hasLatitude ? (float) $data['latitude'] : ($oldLocation['latitude'] ?? null);
        $longitude = $hasLongitude ? (float) $data['longitude'] : ($oldLocation['longitude'] ?? null);

        unset($data['latitude'], $data['longitude']);

        $vehicle->update($data);

        if ($vehicle->license_plate !== $oldPlate) {
            $this->locationService->deleteLocationByPlate($oldPlate);
        }

        if ($latitude !== null && $longitude !== null) {
            $this->locationService->upsertLocation($vehicle, $latitude, $longitude, (bool) ($oldLocation['active'] ?? false));
            $vehicle->setAttribute('latitude', $latitude);
            $vehicle->setAttribute('longitude', $longitude);
        }

        return response()->json([
            'message' => 'Vehículo actualizado correctamente',
            'data' => $vehicle,
        ]);
    }

    /**
     * Eliminar un vehículo (soft delete)
     */
    public function destroy(Vehicle $vehicle): JsonResponse
    {
        $this->authorize('delete', $vehicle);

        $this->locationService->deleteLocationByPlate($vehicle->license_plate);

        $vehicle->delete();

        return response()->json([
            'message' => 'Vehículo eliminado correctamente',
        ]);
    }

    /**
     * Obtener vehículos con ubicaciones para el mapa
     */
    public function map(): JsonResponse
    {
        $this->authorize('viewAny', Vehicle::class);

        $vehicles = Vehicle::query()
            ->withCount([
                'reservations as active_reservations_count' => function ($q) {
                    $q->whereIn('status', ['pending', 'active', 'confirmed']);
                }
            ])
            ->get();
        $locations = $this->locationService->getLocations();

        $result = $vehicles->map(function ($vehicle) use ($locations) {
            $location = $locations[$vehicle->license_plate] ?? null;
            $hasActiveReservation = ((int) ($vehicle->active_reservations_count ?? 0)) > 0;
            $mongoRunning = $hasActiveReservation && (($location['active'] ?? false) === true);
            $effectiveStatus = $mongoRunning ? 'running' : ($hasActiveReservation ? 'occupied' : 'available');

            return [
                'id' => $vehicle->id,
                'plate' => $vehicle->license_plate,
                'brand' => $vehicle->brand,
                'model' => $vehicle->model,
                'status' => $effectiveStatus,
                'postgres_active' => $hasActiveReservation,
                'mongo_active' => $mongoRunning,
                'latitude' => $location['latitude'] ?? null,
                'longitude' => $location['longitude'] ?? null,
            ];
        })
        // Keep only those vehicles that have coordinates
        ->filter(fn($v) => $v['latitude'] !== null && $v['longitude'] !== null)->values();

        return response()->json($result);
    }

    /**
     * Obtener vehedculos para admin (incluye inactivos y datos extra)
     */
    public function adminMap(): JsonResponse
    {
        $this->authorize('viewAny', Vehicle::class);

        $vehicles = Vehicle::query()
            ->withCount([
                'reservations as active_reservations_count' => function ($q) {
                    $q->whereIn('status', ['pending', 'active', 'confirmed']);
                }
            ])
            ->get();
        $locations = $this->locationService->getLocations();

        $result = $vehicles->map(function ($vehicle) use ($locations) {
            $location = $locations[$vehicle->license_plate] ?? null;
            $hasActiveReservation = ((int) ($vehicle->active_reservations_count ?? 0)) > 0;
            $mongoRunning = $hasActiveReservation && (($location['active'] ?? false) === true);
            $effectiveStatus = $mongoRunning ? 'running' : ($hasActiveReservation ? 'occupied' : 'available');

            return [
                'id' => $vehicle->id,
                'plate' => $vehicle->license_plate,
                'brand' => $vehicle->brand,
                'model' => $vehicle->model,
                'latitude' => $location['latitude'] ?? null,
                'longitude' => $location['longitude'] ?? null,
                'mongo_active' => $mongoRunning,
                'postgres_active' => $hasActiveReservation,
                'status' => $effectiveStatus,
            ];
        })->values();

        return response()->json($result);
    }
}

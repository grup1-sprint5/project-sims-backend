<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Http\Requests\Tenant\StoreTenantRequest;
use App\Http\Requests\Tenant\UpdateTenantRequest;
use App\Http\Resources\TenantResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantController extends Controller
{
    /**
     * Lista paginada de tenants con filtros y búsqueda.
     *
     * Parámetros de query:
     * - search: busca por name, slug, email o tax_id
     * - active: filtro por estado activo (true/false)
     * - sort: campo para ordenar (name, slug, created_at) — default: created_at
     * - dir: dirección de orden (asc, desc) — default: desc
     * - per_page: resultados por página — default: 15
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Tenant::class);

        $user = auth()->user();

        // TenantAdmin can only see their own tenant
        if (!$user->isSuperAdmin() && $user->tenant_id) {
            $tenant = Tenant::with('domains')->find($user->tenant_id);
            return response()->json([
                'data' => $tenant ? [new TenantResource($tenant)] : [],
            ]);
        }

        $query = Tenant::query();

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ILIKE', "%{$search}%")
                  ->orWhere('id', 'ILIKE', "%{$search}%")
                  ->orWhere('email', 'ILIKE', "%{$search}%")
                  ->orWhere('tax_id', 'ILIKE', "%{$search}%");
            });
        }

        if ($request->has('active')) {
            $query->where('active', filter_var($request->input('active'), FILTER_VALIDATE_BOOLEAN));
        }

        $sortField = in_array($request->input('sort'), ['name', 'id', 'created_at'])
            ? $request->input('sort')
            : 'created_at';
        $sortDir = in_array($request->input('dir'), ['asc', 'desc'])
            ? $request->input('dir')
            : 'desc';

        $tenants = $query->with('domains')->orderBy($sortField, $sortDir)
                         ->paginate($request->input('per_page', 15));

        return TenantResource::collection($tenants)->response();
    }

    /**
     * Crear un nuevo tenant.
     * The `slug` from the request becomes the tenant's primary key (`id`).
     * stancl/tenancy automatically creates the PostgreSQL schema and runs
     * tenant migrations when the Tenant model is saved.
     */
    public function store(StoreTenantRequest $request): JsonResponse
    {
        $this->authorize('create', Tenant::class);

        $data       = $request->validated();
        $data['id'] = $data['slug'];  // slug is the string primary key
        unset($data['slug']);

        $tenant = Tenant::create($data);

        return response()->json([
            'message' => 'Tenant creado correctamente',
            'data'    => new TenantResource($tenant),
        ], 201);
    }

    /**
     * Mostrar un tenant específico.
     */
    public function show(Tenant $tenant): JsonResponse
    {
        $this->authorize('view', $tenant);

        $tenant->loadMissing('domains');

        return response()->json([
            'data' => new TenantResource($tenant),
        ]);
    }

    /**
     * Actualizar un tenant.
     */
    public function update(UpdateTenantRequest $request, Tenant $tenant): JsonResponse
    {
        $this->authorize('update', $tenant);

        $tenant->update($request->validated());

        return response()->json([
            'message' => 'Tenant actualizado correctamente',
            'data'    => new TenantResource($tenant),
        ]);
    }

    /**
     * Eliminar un tenant (soft delete).
     */
    public function destroy(Tenant $tenant): JsonResponse
    {
        $this->authorize('delete', $tenant);

        $tenant->delete();

        return response()->json([
            'message' => 'Tenant eliminado correctamente',
        ]);
    }

    /**
     * Activar/desactivar un tenant rápidamente.
     */
    public function toggleActive(Tenant $tenant): JsonResponse
    {
        $this->authorize('update', $tenant);

        $tenant->update(['active' => !$tenant->active]);
        $tenant->loadMissing('domains');

        return response()->json([
            'message' => $tenant->active ? 'Tenant activado' : 'Tenant desactivado',
            'data'    => new TenantResource($tenant),
        ]);
    }
}

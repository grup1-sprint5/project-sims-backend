<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Http\Resources\RoleResource;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    /**
     * List all roles with permissions.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Role::class);

        $user = auth()->user();
        $query = Role::with('permissions');

        // Central SuperAdmin sees roles across all tenants. Some legacy central
        // SuperAdmin rows still have tenant_id set, so role/context wins here.
        $isCentralSuperAdmin = !(function_exists('tenancy') && tenancy()->initialized)
            && $user->isSuperAdmin();

        if (!$isCentralSuperAdmin) {
            $query->where('name', '!=', 'SuperAdmin');

            if (!empty($user->tenant_id)) {
                $query->where(fn($q) => $q
                    ->whereNull('tenant_id')
                    ->orWhere('tenant_id', $user->tenant_id));
            }
        }

        if ($search = $request->input('search')) {
            $query->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($search) . '%']);
        }

        if ($isCentralSuperAdmin) {
            return $this->indexForSuperAdmin($request);
        }

        $perPage = (int) $request->input('per_page', 15);

        return RoleResource::collection($query->paginate(min($perPage, 100)));
    }

    private function indexForSuperAdmin(Request $request)
    {
        $centralConnection = (string) (config('tenancy.database.central_connection')
            ?? config('database.default')
            ?? 'pgsql');
        $prefix = config('tenancy.database.prefix', 'tenant_');
        $search = $request->input('search');
        $rows = collect();

        try {
            $centralRoles = DB::connection($centralConnection)
                ->table('roles')
                ->where('guard_name', 'web')
                ->when($search, fn($q) => $q->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($search) . '%']))
                ->get();

            $centralRoleIds = $centralRoles->pluck('id')->toArray();
            $centralPermissions = collect();

            if (!empty($centralRoleIds)) {
                try {
                    $centralPermissions = DB::connection($centralConnection)
                        ->table('role_has_permissions as rhp')
                        ->join('permissions as p', 'p.id', '=', 'rhp.permission_id')
                        ->whereIn('rhp.role_id', $centralRoleIds)
                        ->select('rhp.role_id', 'p.id', 'p.name')
                        ->get()
                        ->groupBy('role_id');
                } catch (\Throwable $e) {
                    Log::warning('SuperAdmin role list: central permissions unavailable: ' . $e->getMessage());
                }
            }

            $rows = $rows->concat($centralRoles->map(function ($role) use ($centralPermissions) {
                return [
                    'id' => $role->id,
                    'source_key' => 'central:' . $role->id,
                    'name' => $role->name,
                    'guard_name' => $role->guard_name,
                    'permissions' => ($centralPermissions->get($role->id) ?? collect())
                        ->map(fn($permission) => ['id' => $permission->id, 'name' => $permission->name])
                        ->values(),
                    'tenant' => ['id' => null, 'name' => 'Central'],
                    'tenant_id' => null,
                    'created_at' => $role->created_at,
                    'updated_at' => $role->updated_at,
                ];
            }));
        } catch (\Throwable $e) {
            Log::error('SuperAdmin role list: central database error: ' . $e->getMessage());
        }

        foreach (Tenant::query()->where('id', '!=', 'central')->get(['id', 'name']) as $tenant) {
            $schema = '"' . $prefix . $tenant->id . '"';

            try {
                $tenantRoles = DB::table(DB::raw("{$schema}.roles"))
                    ->where('guard_name', 'web')
                    ->when($search, fn($q) => $q->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($search) . '%']))
                    ->get();

                $tenantRoleIds = $tenantRoles->pluck('id')->toArray();
                $tenantPermissions = collect();

                if (!empty($tenantRoleIds)) {
                    try {
                        $tenantPermissions = DB::table(DB::raw("{$schema}.role_has_permissions as rhp"))
                            ->join(DB::raw("{$schema}.permissions as p"), 'p.id', '=', 'rhp.permission_id')
                            ->whereIn('rhp.role_id', $tenantRoleIds)
                            ->select('rhp.role_id', 'p.id', 'p.name')
                            ->get()
                            ->groupBy('role_id');
                    } catch (\Throwable $e) {
                        Log::warning("SuperAdmin role list: schema {$schema} permissions unavailable: " . $e->getMessage());
                    }
                }
            } catch (\Throwable $e) {
                Log::error("SuperAdmin role list: schema {$schema} error: " . $e->getMessage());
                continue;
            }

            $tenantCopy = $tenant;
            $rows = $rows->concat($tenantRoles->map(function ($role) use ($tenantCopy, $tenantPermissions) {
                return [
                    'id' => $role->id,
                    'source_key' => $tenantCopy->id . ':' . $role->id,
                    'name' => $role->name,
                    'guard_name' => $role->guard_name,
                    'permissions' => ($tenantPermissions->get($role->id) ?? collect())
                        ->map(fn($permission) => ['id' => $permission->id, 'name' => $permission->name])
                        ->values(),
                    'tenant' => ['id' => $tenantCopy->id, 'name' => $tenantCopy->name],
                    'tenant_id' => $tenantCopy->id,
                    'created_at' => $role->created_at,
                    'updated_at' => $role->updated_at,
                ];
            }));
        }

        $rows = $rows
            ->sortBy([
                fn($a, $b) => strcmp((string) ($a['tenant_id'] ?? ''), (string) ($b['tenant_id'] ?? '')),
                fn($a, $b) => strcmp(strtolower((string) $a['name']), strtolower((string) $b['name'])),
            ])
            ->values();

        $perPage = min((int) $request->input('per_page', 15), 100);
        $page = max((int) $request->input('page', 1), 1);
        $total = $rows->count();
        $slice = $rows->slice(($page - 1) * $perPage, $perPage)->values();

        $paginator = new LengthAwarePaginator(
            $slice,
            $total,
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return response()->json($paginator);
    }

    /**
     * Create a new role with optional permissions.
     */
    public function store(StoreRoleRequest $request)
    {
        $this->authorize('create', Role::class);

        $data = $request->validated();
        $user = auth()->user();

        $role = Role::create([
            'name' => $data['name'],
            'guard_name' => $data['guard_name'] ?? 'web',
            'tenant_id' => $user->isSuperAdmin() ? null : $user->tenant_id,
        ]);

        if (!empty($data['permissions'])) {
            $role->syncPermissions($data['permissions']);
        }

        return (new RoleResource($role->load('permissions')))->response()->setStatusCode(201);
    }

    public function show(Role $role)
    {
        $this->authorize('view', $role);

        return new RoleResource($role->load('permissions'));
    }

    public function update(UpdateRoleRequest $request, Role $role)
    {
        $this->authorize('update', $role);

        if (strtolower($role->name) === 'superadmin') {
            return response()->json([
                'message' => 'Cannot modify the SuperAdmin role',
            ], 403);
        }

        $data = $request->validated();

        $role->update([
            'name' => $data['name'] ?? $role->name,
            'guard_name' => $data['guard_name'] ?? $role->guard_name,
        ]);

        if (array_key_exists('permissions', $data)) {
            $role->syncPermissions($data['permissions']);
        }

        return new RoleResource($role->load('permissions'));
    }

    public function destroy(Role $role)
    {
        $this->authorize('delete', $role);

        if (strtolower($role->name) === 'superadmin') {
            return response()->json([
                'message' => 'Cannot delete the SuperAdmin role',
            ], 403);
        }

        $role->delete();

        return response()->json(['message' => 'Role deleted successfully']);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Http\Resources\RoleResource;
use Illuminate\Http\Request;
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
        $query = Role::with('permissions')
            ->where('guard_name', 'web');

        $perPage = min((int) $request->input('per_page', 15), 100);

        if ($search = $request->input('search')) {
            $query->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($search) . '%']);
        }

        return RoleResource::collection($query->orderBy('id')->paginate($perPage));
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

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
     * List all roles with their permissions.
     * Supports ?search= filter and pagination.
     * Requires 'roles.view' permission.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Role::class);

        $query = Role::with('permissions');

        if ($search = $request->input('search')) {
            $query->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($search) . '%']);
        }

        $perPage = (int) $request->input('per_page', 15);

        return RoleResource::collection($query->paginate(min($perPage, 100)));
    }

    /**
     * Create a new role with optional permissions.
     * Requires 'roles.manage' permission.
     */
    public function store(StoreRoleRequest $request)
    {
        $this->authorize('create', Role::class);

        $data = $request->validated();

        $role = Role::create([
            'name'       => $data['name'],
            'guard_name' => $data['guard_name'] ?? 'web',
        ]);

        if (!empty($data['permissions'])) {
            $role->syncPermissions($data['permissions']);
        }

        return (new RoleResource($role->load('permissions')))->response()->setStatusCode(201);
    }

    /**
     * Show a specific role with its permissions.
     * Requires 'roles.view' permission.
     */
    public function show(Role $role)
    {
        $this->authorize('view', $role);

        return new RoleResource($role->load('permissions'));
    }

    /**
     * Update a role's name and/or permissions.
     * Requires 'roles.manage' permission.
     * System roles (Admin, Client, Maintenance) cannot be modified.
     */
    public function update(UpdateRoleRequest $request, Role $role)
    {
        $data = $request->validated();

        $role->update([
            'name'       => $data['name'] ?? $role->name,
            'guard_name' => $data['guard_name'] ?? $role->guard_name,
        ]);

        if (array_key_exists('permissions', $data)) {
            $role->syncPermissions($data['permissions']);
        }

        return new RoleResource($role->load('permissions'));
    }

    /**
     * Delete a role.
     * Requires 'roles.delete' permission.
     * System roles (Admin, Client, Maintenance) cannot be deleted.
     */
    public function destroy(Role $role)
    {
        $this->authorize('delete', $role);

        $role->delete();

        return response()->json(['message' => 'Role deleted successfully']);
    }
}

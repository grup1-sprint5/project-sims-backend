<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    /**
     * List all roles with their permissions.
     * Requires 'roles.view' permission.
     * SuperAdmin sees all roles. TenantAdmin sees system roles (except SuperAdmin) + their tenant's custom roles.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Role::class);

        $user = auth()->user();
        $query = Role::with('permissions');

        if (!$user->isSuperAdmin()) {
            // Hide SuperAdmin role and show only: system roles (tenant_id=null) + own tenant's roles
            $query->where('name', '!=', 'SuperAdmin')
                  ->where(function ($q) use ($user) {
                      $q->whereNull('tenant_id')
                        ->orWhere('tenant_id', $user->tenant_id);
                  });
        }

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->whereRaw('LOWER(name) LIKE ?', ["%".strtolower($search)."%"]);
        }

        $roles = $query->paginate(10);

        return response()->json($roles);
    }

    /**
     * Create a new role.
     * Requires 'roles.manage' permission.
     * Cannot create system roles (Admin, Client, Maintenance).
     */
    public function store(Request $request)
    {
        $this->authorize('create', Role::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name'],
            'description' => ['sometimes', 'string', 'max:255'],
            'guard_name' => ['sometimes', 'string', 'max:255'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ]);

        $user = auth()->user();

        $role = Role::create([
            'name' => $data['name'],
            'description' => $data['description'] ?? '',
            'guard_name' => $data['guard_name'] ?? 'web',
            'tenant_id' => $user->isSuperAdmin() ? null : $user->tenant_id,
        ]);

        if (isset($data['permissions'])) {
            $role->syncPermissions($data['permissions']);
        }

        return response()->json($role->load('permissions'), 201);
    }

    /**
     * Show a specific role with its permissions.
     * Requires 'roles.view' permission.
     * Non-SuperAdmin users cannot view the SuperAdmin role.
     */
    public function show(Role $role)
    {
        $this->authorize('view', $role);

        return response()->json($role->load('permissions'));
    }

    /**
     * Update a role.
     * Requires 'roles.manage' permission.
     * Cannot update system roles (SuperAdmin, TenantAdmin, Client, Maintenance).
     */
    public function update(Request $request, Role $role)
    {
        $this->authorize('update', $role);

        // Prevent editing SuperAdmin role
        if (strtolower($role->name) === 'superadmin') {
            return response()->json([
                'message' => 'Cannot modify the SuperAdmin role',
            ], 403);
        }

        $data = $request->validate([
            'name' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('roles', 'name')->ignore($role->id),
            ],
            'description' => ['sometimes', 'string', 'max:255'],
            'guard_name' => ['sometimes', 'string', 'max:255'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ]);

        $role->update([
            'name' => $data['name'] ?? $role->name,
            'description' => $data['description'] ?? $role->description,
            'guard_name' => $data['guard_name'] ?? $role->guard_name,
        ]);

        if (isset($data['permissions'])) {
            $role->syncPermissions($data['permissions']);
        }

        return response()->json($role->load('permissions'));
    }

    /**
     * Delete a role.
     * Requires 'roles.delete' permission.
     * Cannot delete system roles (SuperAdmin, TenantAdmin, Client, Maintenance).
     */
    public function destroy(Role $role)
    {
        $this->authorize('delete', $role);

        // Prevent deleting SuperAdmin role
        if (strtolower($role->name) === 'superadmin') {
            return response()->json([
                'message' => 'Cannot delete the SuperAdmin role',
            ], 403);
        }

        $role->delete();

        return response()->json([
            'message' => 'Role deleted successfully',
        ]);
    }
}

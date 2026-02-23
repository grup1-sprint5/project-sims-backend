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
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Role::class);

        $query = Role::with('permissions');
        
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

        $role = Role::create([
            'name' => $data['name'],
            'description' => $data['description'] ?? '',
            'guard_name' => $data['guard_name'] ?? 'web',
        ]);

        if (isset($data['permissions'])) {
            $role->syncPermissions($data['permissions']);
        }

        return response()->json($role->load('permissions'), 201);
    }

    /**
     * Show a specific role with its permissions.
     * Requires 'roles.view' permission.
     */
    public function show(Role $role)
    {
        $this->authorize('view', $role);

        return response()->json($role->load('permissions'));
    }

    /**
     * Update a role.
     * Requires 'roles.manage' permission.
     * Cannot update system roles (Admin, Client, Maintenance).
     */
    public function update(Request $request, Role $role)
    {
        // Prevent editing Admin role
        if (strtolower($role->name) === 'admin') {
            return response()->json([
                'message' => 'Cannot modify the Admin role',
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
     * Cannot delete system roles (Admin, Client, Maintenance).
     */
    public function destroy(Role $role)
    {
        // Prevent deleting Admin role
        if (strtolower($role->name) === 'admin') {
            return response()->json([
                'message' => 'Cannot delete the Admin role',
            ], 403);
        }

        $role->delete();

        return response()->json([
            'message' => 'Role deleted successfully',
        ]);
    }
}

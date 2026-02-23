<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    /**
     * List all users.
     * Requires 'users.view' permission.
     */
    public function index()
    {
        $this->authorize('viewAny', User::class);

        return response()->json(User::with('roles')->get());
    }

    /**
     * Show a specific user.
     * Users can view their own profile, admins can view any profile.
     */
    public function show(User $user)
    {
        $this->authorize('view', $user);

        return response()->json($user->load('roles'));
    }

    /**
     * Create a new user.
     * Requires 'users.manage' permission.
     */
    public function store(Request $request)
    {
        $this->authorize('create', User::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
            'active' => ['sometimes', 'boolean'],
            'role_id' => ['nullable', 'integer', 'exists:roles,id'],
        ]);

        $roleId = $data['role_id'] ?? null;
        unset($data['role_id']);

        $data['password'] = Hash::make($data['password']);
        $user = User::create($data);
        
        if ($roleId) {
            $role = Role::find($roleId);
            if ($role) {
                $user->assignRole($role);
            }
        }

        return response()->json($user, 201);
    }

    /**
     * Update a user.
     * Users can update their own profile, admins can update any profile.
     */
    public function update(Request $request, User $user)
    {
        $this->authorize('update', $user);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'username' => ['sometimes', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'password' => ['nullable', 'string', 'min:6'],
            'active' => ['sometimes', 'boolean'],
            'role_id' => ['nullable', 'integer', 'exists:roles,id'],
        ]);

        if (isset($data['password']) && $data['password'] !== null) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }
        
        $roleId = $data['role_id'] ?? null;
        unset($data['role_id']);

        $user->update($data);
        
        if ($roleId !== null) {
            $role = Role::find($roleId);
            if ($role) {
                $user->syncRoles([$role]);
            }
        }

        return response()->json($user);
    }

    /**
     * Delete a user.
     * Only admins with 'users.delete' permission.
     * Users cannot delete themselves.
     */
    public function destroy(User $user)
    {
        $this->authorize('delete', $user);

        $user->delete();

        return response()->json(['message' => 'User deleted successfully']);
    }
}

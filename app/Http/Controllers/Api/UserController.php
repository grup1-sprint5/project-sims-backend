<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
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

        $query = User::with('roles', 'tenant');

        // Filters
        if ($search = request('search')) {
            $query->where(fn($q) => $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
        }
        if ($role = request('role')) {
            $query->whereHas('roles', fn($q) => $q->where('name', $role));
        }
        if ($tenant = request('tenant_id')) {
            $query->where('tenant_id', $tenant);
        }
        if (!is_null(request('active'))) {
            $query->where('active', (bool) request('active'));
        }

        $perPage = (int) request('per_page', 15);

        return UserResource::collection($query->paginate(min($perPage, 100)));
    }

    /**
     * Show a specific user.
     * Users can view their own profile, admins can view any profile.
     */
    public function show(User $user)
    {
        $this->authorize('view', $user);
        return new UserResource($user->load('roles', 'tenant'));
    }

    /**
     * Create a new user.
     * Requires 'users.manage' permission.
     */
    public function store(StoreUserRequest $request)
    {
        $data = $request->validated();

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

        return (new UserResource($user->load('roles', 'tenant')))->response()->setStatusCode(201);
    }

    /**
     * Update a user.
     * Users can update their own profile, admins can update any profile.
     */
    public function update(UpdateUserRequest $request, User $user)
    {
        $this->authorize('update', $user);

        $data = $request->validated();

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

        return new UserResource($user->load('roles', 'tenant'));
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

    /**
     * Restore a soft-deleted user.
     */
    public function restore($id)
    {
        $user = User::withTrashed()->findOrFail($id);

        $this->authorize('restore', $user);

        $user->restore();

        return new UserResource($user->load('roles', 'tenant'));
    }
}

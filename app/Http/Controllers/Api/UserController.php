<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    /**
     * List all users.
     * Requires 'users.view' permission.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', User::class);

        $authUser = auth()->user();

        // Determine tenant context: from active tenancy or from the user's own tenant_id.
        $tenantId = null;
        if (function_exists('tenancy') && tenancy()->initialized) {
            $tenantId = (string) tenant('id');
        } elseif (!empty($authUser->tenant_id)) {
            $tenantId = (string) $authUser->tenant_id;
        }

        // Only call indexForSuperAdmin for truly central SuperAdmin:
        // must have SuperAdmin role AND no tenant context (neither tenancy nor tenant_id).
        if (!$tenantId && $authUser && $authUser->isSuperAdmin()) {
            return $this->indexForSuperAdmin($request);
        }

        if (!$tenantId) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $query = User::with(['roles', 'tenant'])
            ->where('tenant_id', $tenantId);

        if ($search = $request->input('search')) {
            $query->where(fn($q) => $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
        }
        if ($role = $request->input('role')) {
            $query->whereHas('roles', fn($q) => $q->where('name', $role));
        }
        if (!is_null($request->input('active'))) {
            $query->where('active', (bool) $request->input('active'));
        }

        $perPage = (int) $request->input('per_page', 15);

        return UserResource::collection($query->paginate(min($perPage, 100)));
    }

    private function indexForSuperAdmin(Request $request)
    {
        $prefix  = config('tenancy.database.prefix', 'tenant_');
        $tenants = Tenant::query()->where('id', '!=', 'central')->get(['id', 'name']);
        $rows    = collect();
        $search  = $request->input('search');
        $roleFilter   = $request->input('role');
        $activeFilter = $request->input('active');

        foreach ($tenants as $tenant) {
            $schema = '"' . $prefix . $tenant->id . '"';

            try {
                // Query users directly via schema-qualified tables — no tenancy init needed.
                $userQuery = DB::table(DB::raw("{$schema}.users as u"))
                    ->whereNull('u.deleted_at');

                if ($search) {
                    $userQuery->where(fn($q) => $q->where('u.name', 'ilike', "%{$search}%")
                        ->orWhere('u.email', 'ilike', "%{$search}%"));
                }
                if (!is_null($activeFilter)) {
                    $userQuery->where('u.active', (bool) $activeFilter);
                }
                if ($roleFilter) {
                    $userQuery->whereExists(function ($sub) use ($schema, $roleFilter) {
                        $sub->select(DB::raw(1))
                            ->from(DB::raw("{$schema}.model_has_roles as mhr"))
                            ->join(DB::raw("{$schema}.roles as rf"), 'rf.id', '=', 'mhr.role_id')
                            ->whereColumn('mhr.model_id', 'u.id')
                            ->where('mhr.model_type', 'App\\Models\\User')
                            ->where('rf.name', $roleFilter);
                    });
                }

                $users = $userQuery->get();

                // Load roles for all users in this schema in one query.
                $userIds = $users->pluck('id')->toArray();
                $roles   = DB::table(DB::raw("{$schema}.model_has_roles as mhr"))
                    ->join(DB::raw("{$schema}.roles as r"), 'r.id', '=', 'mhr.role_id')
                    ->whereIn('mhr.model_id', $userIds)
                    ->where('mhr.model_type', 'App\\Models\\User')
                    ->select('mhr.model_id', 'r.id as role_id', 'r.name as role_name', 'r.guard_name')
                    ->get()
                    ->groupBy('model_id');

            } catch (\Throwable $e) {
                Log::error("SuperAdmin user list: schema {$schema} error: " . $e->getMessage());
                continue;
            }

            $tenantCopy = $tenant;
            $rows = $rows->concat($users->map(function ($user) use ($tenantCopy, $roles) {
                $userRoles = ($roles->get($user->id) ?? collect())->map(fn($r) => [
                    'id'         => $r->role_id,
                    'name'       => $r->role_name,
                    'guard_name' => $r->guard_name,
                ])->values();

                return [
                    'id'         => $user->id,
                    'name'       => $user->name,
                    'username'   => $user->username ?? null,
                    'email'      => $user->email,
                    'active'     => (bool) $user->active,
                    'roles'      => $userRoles,
                    'tenant'     => ['id' => $tenantCopy->id, 'name' => $tenantCopy->name],
                    'tenant_id'  => $tenantCopy->id,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                ];
            }));
        }

        if ($tenantFilter = $request->input('tenant_id')) {
            $rows = $rows->where('tenant_id', $tenantFilter)->values();
        }

        $rows    = $rows->sortByDesc('created_at')->values();
        $perPage = min((int) $request->input('per_page', 15), 100);
        $page    = max((int) $request->input('page', 1), 1);
        $total   = $rows->count();
        $slice   = $rows->slice(($page - 1) * $perPage, $perPage)->values();

        $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
            $slice, $total, $perPage, $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return response()->json($paginator);
    }

    /**
     * Show a specific user.
     */
    public function show(User $user)
    {
        $this->authorize('view', $user);

        return new UserResource($user->load(['roles', 'tenant']));
    }

    /**
     * Create a new user.
     */
    public function store(StoreUserRequest $request)
    {
        $data = $request->validated();

        $roleId   = $data['role_id']   ?? null;
        $tenantId = $data['tenant_id'] ?? null;
        unset($data['role_id'], $data['tenant_id']);

        $data['password'] = Hash::make($data['password']);

        // When a tenant_id is provided (superadmin creating a user for a specific tenant),
        // switch to that tenant's database so the user lands in the right schema.
        if ($tenantId) {
            $tenant = \App\Models\Tenant::find($tenantId);
            if (!$tenant) {
                return response()->json(['message' => 'Tenant not found'], 404);
            }
            tenancy()->initialize($tenant);
            // Explicitly set tenant_id so BelongsToTenant scope can find the user.
            $data['tenant_id'] = $tenantId;
        }

        $user = User::create($data);

        $authUser = auth()->user();
        if ($roleId) {
            $role = Role::find($roleId);
            if ($role) {
                $isSuperAdmin = $authUser && $authUser->isSuperAdmin();
                if (strtolower($role->name) === 'superadmin' && !$isSuperAdmin) {
                    $user->delete();
                    return response()->json(['message' => 'Only SuperAdmin can assign the SuperAdmin role.'], 403);
                }
                $user->assignRole($role);
            }
        } elseif (!$authUser) {
            // Public self-registration: assign Client role by default.
            $clientRole = Role::where('name', 'Client')->first();
            if ($clientRole) {
                $user->assignRole($clientRole);
            }
        }

        return (new UserResource($user->load('roles')))->response()->setStatusCode(201);
    }

    /**
     * Update a user.
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

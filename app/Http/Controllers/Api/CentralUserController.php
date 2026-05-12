<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CentralUserController extends Controller
{
    /**
     * Get current central user (superadmin)
     * Uses direct DB queries to avoid global TenantScope issues
     */
    public function show(Request $request)
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $centralConnection = (string) (config('tenancy.database.central_connection')
            ?? config('database.default')
            ?? 'pgsql');

        // Get user data from central database (bypass TenantScope)
        $userData = DB::connection($centralConnection)
            ->table('users')
            ->where('id', $user->id)
            ->first();

        if (!$userData) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // Get user roles from central database
        $roles = DB::connection($centralConnection)
            ->table('model_has_roles')
            ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
            ->where('model_has_roles.model_id', $user->id)
            ->where('model_has_roles.model_type', 'App\\Models\\User')
            ->select('roles.name', 'roles.id')
            ->get();

        return response()->json([
            'user' => array_merge(
                (array) $userData,
                [
                    'roles' => $roles->pluck('name')->toArray(),
                    'role_objs' => $roles->toArray(),
                ]
            )
        ]);
    }
}

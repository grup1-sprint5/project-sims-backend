<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Incorrect credentials.'],
            ]);
        }

        if (!$user->active) {
            return response()->json([
                'message' => 'User inactive.'
            ], 403);
        }

        $tenantId = null;
        if (function_exists('tenant') && tenant()) {
            $tenantId = (string) tenant('id');
        }

        $abilities = $tenantId ? ["tenant:{$tenantId}"] : ['tenant:central'];
        $token = $user->createToken('api-token', $abilities)->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'token' => $token,
            'user' => $user,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logout successful'
        ]);
    }

    public function user(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // For central users (superadmin) with no tenant context
        if (!function_exists('tenancy') || !tenancy()->initialized) {
            $centralConnection = (string) (config('tenancy.database.central_connection')
                ?? config('database.default')
                ?? 'pgsql');

            // Query central database directly
            $centralUser = \Illuminate\Support\Facades\DB::connection($centralConnection)
                ->table('users')
                ->where('id', $user->id)
                ->first();

            if (!$centralUser) {
                return response()->json(['message' => 'User not found'], 404);
            }

            // Get central user roles
            $roles = \Illuminate\Support\Facades\DB::connection($centralConnection)
                ->table('model_has_roles')
                ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
                ->where('model_has_roles.model_id', $user->id)
                ->where('model_has_roles.model_type', 'App\\Models\\User')
                ->select('roles.id', 'roles.name', 'roles.guard_name')
                ->get();

            return response()->json([
                'user' => array_merge(
                    (array) $centralUser,
                    ['roles' => $roles->toArray()]
                )
            ]);
        }

        // For tenant users load normally
        return response()->json([
            'user' => $user->load('roles.permissions')
        ]);
    }
}

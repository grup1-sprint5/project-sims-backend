<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CentralAdminController extends Controller
{
    /**
     * Get central users (superadmin data)
     */
    public function users(Request $request)
    {
        $centralConnection = (string) (config('tenancy.database.central_connection')
            ?? config('database.default')
            ?? 'pgsql');

        $query = DB::connection($centralConnection)
            ->table('users');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(email) LIKE ?', ['%' . strtolower($search) . '%'])
                    ->orWhereRaw('LOWER(name) LIKE ?', ['%' . strtolower($search) . '%']);
            });
        }

        $perPage = (int) $request->input('per_page', 15);
        $users = $query->paginate(min($perPage, 100));

        return response()->json($users);
    }

    /**
     * Get central roles
     */
    public function roles(Request $request)
    {
        $centralConnection = (string) (config('tenancy.database.central_connection')
            ?? config('database.default')
            ?? 'pgsql');

        $query = DB::connection($centralConnection)
            ->table('roles')
            ->where('guard_name', 'web');

        if ($search = $request->input('search')) {
            $query->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($search) . '%']);
        }

        $perPage = (int) $request->input('per_page', 15);
        $roles = $query->paginate(min($perPage, 100));

        return response()->json($roles);
    }

    /**
     * Get all permissions from central database
     */
    public function permissions(Request $request)
    {
        $centralConnection = (string) (config('tenancy.database.central_connection')
            ?? config('database.default')
            ?? 'pgsql');

        $permissions = DB::connection($centralConnection)
            ->table('permissions')
            ->where('guard_name', 'web')
            ->get();

        return response()->json(['data' => $permissions]);
    }
}


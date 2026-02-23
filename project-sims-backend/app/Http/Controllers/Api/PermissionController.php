<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Spatie\Permission\Models\Permission;
use Illuminate\Http\JsonResponse;

class PermissionController extends Controller
{
    /**
     * Get all permissions grouped by module
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        try {
            $permissions = Permission::all()->groupBy(function ($permission) {
                // Extract module from permission name (e.g., "can.view.vehicles" -> "vehicles")
                $parts = explode('.', (string) $permission->name);
                if (count($parts) >= 3) {
                    $module = $parts[count($parts) - 1];
                    return ucfirst($module);
                }
                return 'Other';
            });

            $grouped = [];
            foreach ($permissions as $module => $perms) {
                $grouped[$module] = $perms->map(function ($permission) use ($module) {
                    return [
                        'id' => $permission->id,
                        'name' => (string) $permission->name,
                        'description' => $permission->description ?? '',
                        'module' => $module,
                    ];
                })->values()->toArray();
            }

            return response()->json($grouped);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Failed to load permissions',
            ], 500);
        }
    }
}

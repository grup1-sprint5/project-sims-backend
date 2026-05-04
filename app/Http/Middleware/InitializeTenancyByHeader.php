<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Spatie\Permission\PermissionRegistrar;
use Closure;
use Illuminate\Http\Request;
use Throwable;

/**
 * Identifies the current tenant using an explicit tenant key:
 *
 * 1. X-Tenant header (preferred), cookie or querystring fallback.
 *
 * If not resolved, request fails with a tenant_not_identified error.
 */
class InitializeTenancyByHeader
{
    public function handle(Request $request, Closure $next): mixed
    {
        // 1. Prefer explicit tenant key from request data.
        $header = config('tenancy.identification.header', 'X-Tenant');
        $tenantKey = $request->header($header)
            ?? $request->cookie(config('tenancy.identification.cookie'))
            ?? $request->query(config('tenancy.identification.querystring'));

        if ($tenantKey) {
            $tenant = $this->resolveTenantByKey((string) $tenantKey);

            if ($tenant) {
                try {
                    tenancy()->initialize($tenant);
                    // Ensure permission cache is scoped to the current tenant.
                    app(PermissionRegistrar::class)->forgetCachedPermissions();
                } catch (Throwable $e) {
                    report($e);

                    $message = strtolower($e->getMessage());
                    $looksLikeMissingTenantDatabase = (
                        (str_contains($message, 'schema') && str_contains($message, 'does not exist'))
                        || str_contains($message, 'unknown database')
                        || str_contains($message, 'database') && str_contains($message, 'does not exist')
                    );

                    if ($request->expectsJson() || $request->is('api/*')) {
                        return response()->json([
                            'message' => $looksLikeMissingTenantDatabase
                                ? 'Tenant database is not initialized on the server.'
                                : 'Tenant initialization failed.',
                            'error' => $looksLikeMissingTenantDatabase
                                ? 'tenant_database_missing'
                                : 'tenant_initialization_failed',
                        ], $looksLikeMissingTenantDatabase ? 409 : 500);
                    }

                    throw $e;
                }

                return $next($request);
            }
        }

        // No tenant resolved.
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => 'Tenant not identified. Provide a valid X-Tenant header.',
                'error' => 'tenant_not_identified',
            ], 422);
        }

        abort(422, 'Tenant not identified.');
    }

    private function resolveTenantByKey(string $tenantKey): mixed
    {
        return Tenant::query()
            ->whereRaw('LOWER(slug) = ?', [strtolower($tenantKey)])
            ->orWhereRaw('LOWER(id) = ?', [strtolower($tenantKey)])
            ->first();
    }
}

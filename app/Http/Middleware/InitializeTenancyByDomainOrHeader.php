<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Identifies the current tenant using explicit tenant key strategy:
 *
 * 1. Explicit tenant key from request data (`X-Tenant` header by default,
 *    cookie or querystring fallback).
 *
 * If not resolved, request fails with a tenant_not_identified error.
 */
class InitializeTenancyByDomainOrHeader
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
                'message' => 'Tenant not identified. Provide X-Tenant header.',
                'error' => 'tenant_not_identified',
            ], 400);
        }

        abort(400, 'Tenant not identified.');
    }

    private function resolveTenantByKey(string $tenantKey): mixed
    {
        $tenantModel = config('tenancy.tenant_model');
        if (!is_string($tenantModel) || !class_exists($tenantModel)) {
            return null;
        }

        $connection = (string) (config('tenancy.database.central_connection')
            ?? config('database.default')
            ?? 'pgsql');

        $hasSlugColumn = false;

        try {
            $hasSlugColumn = Schema::connection($connection)->hasColumn('tenants', 'slug');
        } catch (Throwable $e) {
            report($e);
        }

        if ($hasSlugColumn) {
            $tenantBySlug = $tenantModel::query()->whereRaw('LOWER(slug) = ?', [strtolower($tenantKey)])->first();
            if ($tenantBySlug) {
                return $tenantBySlug;
            }
        }

        if (ctype_digit($tenantKey)) {
            return $tenantModel::query()->find((int) $tenantKey);
        }

        // When there is no legacy slug column, id is expected to be string-like.
        if (!$hasSlugColumn) {
            return $tenantModel::query()->find($tenantKey);
        }

        return null;
    }
}

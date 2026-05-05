<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Stancl\Tenancy\Database\Models\Domain;
use Throwable;

/**
 * Identifies the current tenant using two strategies (in order):
 *
 * 1. Explicit tenant key from request data (`X-Tenant` header by default,
 *    cookie or querystring fallback).
 * 2. Domain / subdomain: looks up the request Host in the `domains` table.
 *
 * If neither strategy resolves a tenant, the request continues without tenant
 * context (routes that require tenancy will then fail as usual).
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
            $tenantModel = config('tenancy.tenant_model');
            $tenant = $tenantModel::find($tenantKey);

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

        $host = $request->getHost();

        // 2. Fall back to domain lookup
        $domain = Domain::where('domain', $host)->first();

        if ($domain) {
            try {
                tenancy()->initialize($domain->tenant);
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

        // 3. Optional fallback for single-tenant deployments (e.g., a VPS)
        // where the frontend may not send X-Tenant and no custom domains are configured.
        $defaultTenantKey = env('TENANCY_DEFAULT_TENANT');
        if (is_string($defaultTenantKey) && $defaultTenantKey !== '') {
            $tenantModel = config('tenancy.tenant_model');
            $tenant = $tenantModel::find($defaultTenantKey);

            if ($tenant) {
                try {
                    tenancy()->initialize($tenant);
                } catch (Throwable $e) {
                    report($e);

                    if ($request->expectsJson() || $request->is('api/*')) {
                        return response()->json([
                            'message' => 'Tenant initialization failed.',
                            'error' => 'tenant_initialization_failed',
                        ], 500);
                    }

                    throw $e;
                }

                return $next($request);
            }
        }

        // Neither strategy resolved a tenant.
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => 'Tenant not identified. Provide X-Tenant header or use a registered tenant domain.',
                'error' => 'tenant_not_identified',
            ], 400);
        }

        abort(400, 'Tenant not identified.');
    }
}

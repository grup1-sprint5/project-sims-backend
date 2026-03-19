<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Stancl\Tenancy\Database\Models\Domain;
use Stancl\Tenancy\Exceptions\TenantCouldNotBeIdentifiedByRequestDataException;

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
                tenancy()->initialize($tenant);
                return $next($request);
            }
        }

        $host = $request->getHost();

        // 2. Fall back to domain lookup
        $domain = Domain::where('domain', $host)->first();

        if ($domain) {
            tenancy()->initialize($domain->tenant);
            return $next($request);
        }

        // Neither resolved → throw the concrete tenancy exception for request data.
        throw new TenantCouldNotBeIdentifiedByRequestDataException($tenantKey ?: $host);
    }
}

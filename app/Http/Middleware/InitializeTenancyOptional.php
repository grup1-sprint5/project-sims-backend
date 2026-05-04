<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Stancl\Tenancy\Exceptions\TenantCouldNotBeIdentifiedException;

class InitializeTenancyOptional
{
    /**
     * Try to initialize tenancy, but don't fail if unable to identify tenant.
     * This allows central routes to work without X-Tenant header.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $middleware = app(\App\Http\Middleware\InitializeTenancyByDomainOrHeader::class);

        try {
            return $middleware->handle($request, $next);
        } catch (TenantCouldNotBeIdentifiedException $e) {
            // No tenant identified, proceed without tenancy context (central user)
            return $next($request);
        }
    }
}

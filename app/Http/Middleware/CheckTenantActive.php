<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckTenantActive
{
    /**
     * Check if the current tenant is active.
     * 
     * If tenancy is initialized but the tenant's active flag is false,
     * reject the request with a 403 Forbidden response.
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            if (tenancy()->initialized) {
                $tenant = tenancy()->tenant();
                
                if ($tenant && !$tenant->active) {
                    return $this->tenantInactiveResponse($request);
                }
            }
        } catch (\Throwable $e) {
            // Tenancy errors handled elsewhere, just continue
        }

        return $next($request);
    }

    /**
     * Return a response indicating the tenant is inactive.
     */
    private function tenantInactiveResponse(Request $request): Response
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => 'This tenant is currently inactive and cannot be accessed.',
                'error' => 'tenant_inactive',
            ], 403);
        }

        return response()->view('errors.403', [], 403);
    }
}

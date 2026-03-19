<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetTenantLogContext
{
    /**
     * Handle an incoming request and set tenant_id in logs context.
     *
     * This middleware ensures that all logs made during the request
     * include the current tenant_id for easier debugging and auditing.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenantId = $this->resolveTenantId();

        // Set in Log context so manual logs include tenant_id
        \Illuminate\Support\Facades\Log::withContext(['tenant_id' => $tenantId]);

        return $next($request);
    }

    /**
     * Resolve tenant ID from tenancy or use 'central'.
     */
    private function resolveTenantId(): string
    {
        try {
            if (tenancy()->initialized) {
                return tenancy()->tenant()->id ?? 'unknown';
            }
        } catch (\Throwable $e) {
            // Not available
        }

        return 'central';
    }
}

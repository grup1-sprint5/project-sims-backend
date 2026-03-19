<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Stancl\Tenancy\Database\Models\Domain;

class TenantDomainController extends Controller
{
    /**
     * List all domains for a tenant.
     */
    public function index(Tenant $tenant): JsonResponse
    {
        $this->authorize('update', $tenant);

        return response()->json([
            'data' => $tenant->domains,
        ]);
    }

    /**
     * Attach a new domain to a tenant.
     */
    public function store(Request $request, Tenant $tenant): JsonResponse
    {
        $this->authorize('update', $tenant);

        $validated = $request->validate([
            'domain' => [
                'required',
                'string',
                'max:255',
                'unique:domains,domain',
                // Disallow central domains
                function ($attribute, $value, $fail) {
                    $central = config('tenancy.central_domains', []);
                    if (in_array($value, $central, true)) {
                        $fail('This domain is reserved for the central application.');
                    }
                },
            ],
        ]);

        $domain = $tenant->domains()->create(['domain' => $validated['domain']]);

        return response()->json([
            'message' => 'Domain attached.',
            'data'    => $domain,
        ], 201);
    }

    /**
     * Remove a domain from a tenant.
     */
    public function destroy(Tenant $tenant, Domain $domain): JsonResponse
    {
        $this->authorize('update', $tenant);

        if ($domain->tenant_id !== $tenant->id) {
            abort(404);
        }

        $domain->delete();

        return response()->json(['message' => 'Domain removed.']);
    }
}

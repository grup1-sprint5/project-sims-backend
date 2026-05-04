<?php

namespace App\Http\Controllers;

use App\Mail\NewTenantRequestMail;
use App\Mail\TenantApprovedMail;
use App\Models\Tenant;
use App\Models\TenantRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;

class TenantRequestController extends Controller
{
    // Public endpoint: create a tenant registration request
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:64',
            'email' => 'required|email|max:255',
            'notes' => 'nullable|string',
        ]);

        $existing = TenantRequest::where('slug', $validated['slug'])->orWhere('email', $validated['email'])->first();
        if ($existing) {
            return response()->json(['message' => 'A request with that slug or email already exists.'], 409);
        }

        $req = TenantRequest::create([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'email' => $validated['email'],
            'notes' => $validated['notes'] ?? null,
            'requested_at' => now(),
            'status' => 'pending',
        ]);

        // Notify superadmin email that a request arrived
        $adminEmail = env('SUPERADMIN_EMAIL');
        if ($adminEmail) {
            Mail::to($adminEmail)->queue(new NewTenantRequestMail($req));
        }

        return response()->json(['message' => 'Request received', 'data' => $req], 201);
    }

    // Admin: list requests
    public function index()
    {
        $user = auth()->user();
        if (!$user || !method_exists($user, 'isSuperAdmin') || !$user->isSuperAdmin()) {
            abort(403);
        }

        return response()->json(['data' => TenantRequest::orderBy('requested_at', 'desc')->get()]);
    }

    // Admin: approve request
    public function approve($id)
    {
        $user = auth()->user();
        if (!$user || !method_exists($user, 'isSuperAdmin') || !$user->isSuperAdmin()) {
            abort(403);
        }

        $req = TenantRequest::findOrFail($id);
        if ($req->status !== 'pending') {
            return response()->json(['message' => 'Request is not pending'], 409);
        }

        // Create Tenant
        $tenantData = [
            'id' => $req->slug,
            'name' => $req->name,
            'email' => $req->email,
            'active' => true,
        ];

        $tenant = Tenant::create($tenantData);

        // Determine base domain
        $central = config('tenancy.central_domains', []);
        $baseDomain = null;
        foreach ($central as $d) {
            if (str_contains($d, '.') && !in_array($d, ['127.0.0.1', 'localhost'], true)) {
                $baseDomain = $d;
                break;
            }
        }

        $domain = null;
        if ($baseDomain) {
            $domain = $tenant->id . '.' . $baseDomain;
            $tenant->domains()->create(['domain' => $domain]);
        }

        // Run tenant migrations for this tenant
        try {
            Artisan::call('tenants:migrate', ['--tenants' => $tenant->id, '--force' => true]);
        } catch (\Throwable $e) {
            report($e);
        }

        $req->update(['status' => 'approved', 'approved_at' => now(), 'domain' => $domain]);

        // Notify tenant by email that their tenant is ready
        try {
            Mail::to($req->email)->queue(new TenantApprovedMail($req, $domain));
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json(['message' => 'Approved', 'tenant' => $tenant, 'domain' => $domain]);
    }
}

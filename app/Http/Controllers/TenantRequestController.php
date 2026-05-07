<?php

namespace App\Http\Controllers;

use App\Mail\NewTenantRequestMail;
use App\Mail\TenantApprovedMail;
use App\Models\Tenant;
use App\Models\TenantRequest;
use App\Services\TenantProvisioningService;
use Illuminate\Http\Request;
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

        // Ensure slug/email not already used by a Tenant or an active (non-rejected) request
        $existingRequest = TenantRequest::whereIn('status', ['pending', 'approved'])
            ->where(function ($q) use ($validated) {
                $q->where('slug', $validated['slug'])->orWhere('email', $validated['email']);
            })->first();
        if ($existingRequest) {
            return response()->json(['message' => 'A request with that slug or email already exists.'], 409);
        }

        $existingTenant = \App\Models\Tenant::find($validated['slug']);
        if ($existingTenant) {
            return response()->json(['message' => 'Tenant with that slug already exists. Choose a different slug.'], 409);
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
            Mail::to($adminEmail)->send(new NewTenantRequestMail($req));
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

        $provisioning = app(TenantProvisioningService::class)->provision($tenant);
        $domain = $provisioning['domains'][0] ?? null;

        $req->update(['status' => 'approved', 'approved_at' => now(), 'domain' => $domain]);

        // Notify tenant by email that their tenant is ready
        try {
            $mailable = new TenantApprovedMail($req, $domain);
            Mail::to($req->email)->send($mailable);
            \Log::info("Approval email sent to {$req->email} for tenant {$req->slug}");
        } catch (\Throwable $e) {
            \Log::error("Failed to send approval email: " . $e->getMessage());
            report($e);
        }

        // Reload to get updated model
        $req->refresh();
        
        return response()->json([
            'message' => 'Approved',
            'tenant' => $tenant,
            'domain' => $domain,
            'domains' => $provisioning['domains'],
            'credentials' => $provisioning['credentials'],
            'default_password' => $provisioning['password'],
            'request' => $req,
        ]);
    }

    // Admin: reject request
    public function reject($id)
    {
        $user = auth()->user();
        if (!$user || !method_exists($user, 'isSuperAdmin') || !$user->isSuperAdmin()) {
            abort(403);
        }

        $req = TenantRequest::findOrFail($id);
        if ($req->status !== 'pending') {
            return response()->json(['message' => 'Request is not pending'], 409);
        }

        $req->update(['status' => 'rejected']);

        return response()->json(['message' => 'Rejected', 'request' => $req]);
    }

    // Public: check if a slug is available (not used by Tenant or pending request)
    public function checkSlug(Request $request)
    {
        $slug = $request->query('slug');
        if (!is_string($slug) || trim($slug) === '') {
            return response()->json(['available' => false, 'message' => 'Missing slug'], 400);
        }

        $slug = trim($slug);

        // Simple format validation
        if (!preg_match('/^[a-z0-9\-]+$/', $slug)) {
            return response()->json(['available' => false, 'message' => 'Invalid slug format']);
        }

        $existsTenant = \App\Models\Tenant::find($slug) !== null;
        $existsRequest = TenantRequest::where('slug', $slug)->exists();

        if ($existsTenant) {
            return response()->json(['available' => false, 'message' => 'Slug already used by an existing tenant']);
        }

        if ($existsRequest) {
            return response()->json(['available' => false, 'message' => 'Slug already requested and pending']);
        }

        return response()->json(['available' => true, 'message' => 'Slug is available']);
    }
}

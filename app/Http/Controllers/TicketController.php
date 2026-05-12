<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\Ticket;
use App\Http\Resources\TicketResource;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

/**
 * Handles ticket CRUD operations.
 * Admins (tickets.manage permission) see all tickets.
 * Clients (tickets.view permission) see only their own.
 */
class TicketController extends Controller
{
    /**
     * List tickets.
     * Admin: all tickets with user info.
     * Client: own tickets only.
     */
    public function index()
    {
        $user = Auth::user();
        $currentTenant = function_exists('tenant') ? tenant()?->id : null;

        // SuperAdmin sees all tickets from all other tenants
        if ($user->isSuperAdmin()) {
            return $this->indexForSuperAdmin();
        }

        // Admin/Support sees tickets (scoped by tenant via global scope)
        if ($user->hasPermissionTo('tickets.manage')) {

            $tickets = Ticket::with(['user', 'messages'])->orderBy('created_at', 'desc')->get();
            return TicketResource::collection($tickets);
        }

        if ($user->hasPermissionTo('tickets.view')) {
            // Regular user: own tickets only
            $tickets = Ticket::where('user_id', $user->id)
                ->with('messages')
                ->orderBy('created_at', 'desc')
                ->get();
            return TicketResource::collection($tickets);
        }

        return response()->json(['message' => 'Unauthorized'], 403);
    }

    private function indexForSuperAdmin()
    {
        $originalTenant = function_exists('tenant') ? tenant() : null;
        $rows = collect();

        // Get all active tenants EXCEPT the "central" tenant (which is for SuperAdmin)
        $tenants = Tenant::query()->where('active', true)->where('id', '!=', 'central')->get(['id', 'name']);

        foreach ($tenants as $tenant) {
            try {
                tenancy()->initialize($tenant);

                if (!\Illuminate\Support\Facades\Schema::hasTable('tickets')) {
                    continue;
                }

                $tenantTickets = Ticket::with(['user', 'messages'])
                    ->orderBy('created_at', 'desc')
                    ->get()
                    ->map(function ($ticket) use ($tenant) {
                        $data = (new TicketResource($ticket))->toArray(request());
                        $data['tenant_id'] = $tenant->id;
                        $data['tenant'] = [
                            'id' => $tenant->id,
                            'name' => $tenant->name,
                        ];
                        return $data;
                    });

                $rows = $rows->concat($tenantTickets);
            } catch (\Throwable) {
            } finally {
                try {
                    tenancy()->end();
                } catch (\Throwable) {
                }
            }
        }

        if ($originalTenant) {
            try { tenancy()->initialize($originalTenant); } catch (\Throwable) {}
        }

        return response()->json(['data' => $rows->sortByDesc('created_at')->values()]);
    }

    /**
     * Create a new ticket for the authenticated user.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Ticket::class);

        $data = $request->validate([
            'vehicle_id'  => ['nullable', 'exists:vehicles,id'],
            'title'       => ['required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
        ]);

        $ticket = $request->user()->tickets()->create($data);

        return response(new TicketResource($ticket), Response::HTTP_CREATED);
    }

    /**
     * Show a single ticket with messages and user info.
     */
    public function show(Request $request, $ticket)
    {
        $crossTenantSuperAdmin = $this->isCrossTenantSuperAdminRequest($request);
        $ticket = $this->resolveTicketForRequest($request, $ticket);

        if (!$crossTenantSuperAdmin) {
            $this->authorize('view', $ticket);
        }

        return new TicketResource($ticket->load(['messages.user', 'user']));
    }

    /**
     * Update a ticket (title, description, status).
     * Admins can close/reopen; owners can edit their own tickets.
     */
    public function update(Request $request, $ticket)
    {
        $crossTenantSuperAdmin = $this->isCrossTenantSuperAdminRequest($request);
        $ticket = $this->resolveTicketForRequest($request, $ticket);

        if (!$crossTenantSuperAdmin) {
            $this->authorize('update', $ticket);
        }

        $data = $request->validate([
            'vehicle_id'  => ['sometimes', 'nullable', 'exists:vehicles,id'],
            'title'       => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'active'      => ['sometimes', 'boolean'],
        ]);

        $ticket->update($data);

        return new TicketResource($ticket->load(['messages.user', 'user']));
    }

    /**
     * Delete a ticket (admin only via tickets.delete permission).
     */
    public function destroy(Request $request, $ticket)
    {
        $crossTenantSuperAdmin = $this->isCrossTenantSuperAdminRequest($request);
        $ticket = $this->resolveTicketForRequest($request, $ticket);

        if (!$crossTenantSuperAdmin) {
            $this->authorize('delete', $ticket);
        }

        $ticket->delete();
        return response(null, Response::HTTP_NO_CONTENT);
    }

    private function resolveTicketForRequest(Request $request, int|string|Ticket $id): Ticket
    {
        if ($id instanceof Ticket) {
            return $id;
        }

        if ($this->isCrossTenantSuperAdminRequest($request)) {
            $tenant = Tenant::findOrFail((string) $request->query('tenant_id'));
            tenancy()->initialize($tenant);
        }

        return Ticket::findOrFail($id);
    }

    private function isCrossTenantSuperAdminRequest(Request $request): bool
    {
        $user = $request->user();

        return $request->filled('tenant_id') && $user && $user->isSuperAdmin();
    }
}

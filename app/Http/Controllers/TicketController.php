<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\Ticket;
use App\Http\Resources\TicketResource;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
    public function index(Request $request)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Central context (no tenant) → superadmin cross-tenant view
        $inTenantContext = function_exists('tenancy') && tenancy()->initialized;

        if (!$inTenantContext) {
            return $this->indexForSuperAdmin();
        }

        // Inside a tenant context: check permissions via Spatie (tables exist here)
        try {
            if ($user->hasPermissionTo('tickets.manage')) {
                $tickets = Ticket::with(['user', 'messages'])->orderBy('created_at', 'desc')->get();
                return TicketResource::collection($tickets);
            }

            if ($user->hasPermissionTo('tickets.view')) {
                $tickets = Ticket::where('user_id', $user->id)
                    ->with('messages')
                    ->orderBy('created_at', 'desc')
                    ->get();
                return TicketResource::collection($tickets);
            }
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Server error: ' . $e->getMessage()], 500);
        }

        return response()->json(['message' => 'Unauthorized'], 403);
    }

    private function indexForSuperAdmin()
    {
        $rows = collect();
        $tenants = Tenant::where('active', true)->where('id', '!=', 'central')->get(['id', 'name']);

        foreach ($tenants as $tenant) {
            try {
                tenancy()->initialize($tenant);

                if (!Schema::hasTable('tickets')) {
                    continue;
                }

                $hasMessages = Schema::hasTable('ticket_messages');

                $tickets = DB::table('tickets')
                    ->leftJoin('users', 'tickets.user_id', '=', 'users.id')
                    ->select(
                        'tickets.id',
                        'tickets.user_id',
                        'tickets.title',
                        'tickets.description',
                        'tickets.active',
                        'tickets.created_at',
                        'tickets.updated_at',
                        'users.name as user_name',
                        'users.email as user_email'
                    )
                    ->orderByDesc('tickets.created_at')
                    ->get();

                // Count messages per ticket with a single query
                $messageCounts = $hasMessages
                    ? DB::table('ticket_messages')
                        ->select('ticket_id', DB::raw('COUNT(*) as cnt'))
                        ->groupBy('ticket_id')
                        ->pluck('cnt', 'ticket_id')
                    : collect();

                $tenantRows = $tickets->map(function ($row) use ($tenant, $messageCounts) {
                    return [
                        'id'          => $row->id,
                        'tenant_id'   => (string) $tenant->id,
                        'user_id'     => $row->user_id,
                        'title'       => $row->title,
                        'description' => $row->description,
                        'active'      => (bool) $row->active,
                        'created_at'  => $row->created_at,
                        'updated_at'  => $row->updated_at,
                        'tenant'      => ['id' => (string) $tenant->id, 'name' => $tenant->name],
                        'user'        => $row->user_id ? ['id' => $row->user_id, 'name' => $row->user_name, 'email' => $row->user_email] : null,
                        'messages'    => array_fill(0, (int) ($messageCounts[$row->id] ?? 0), null),
                    ];
                });

                $rows = $rows->concat($tenantRows);
            } catch (\Throwable) {
            } finally {
                try { tenancy()->end(); } catch (\Throwable) {}
            }
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

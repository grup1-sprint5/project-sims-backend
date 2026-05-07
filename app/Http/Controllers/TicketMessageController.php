<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\Tenant;
use App\Http\Resources\TicketMessageResource;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class TicketMessageController extends Controller
{
    /**
     * Store a new message on a ticket.
     * Uses route model binding: POST /tickets/{ticket}/messages
     */
    public function store(Request $request, $ticket)
    {
        $crossTenantSuperAdmin = $this->isCrossTenantSuperAdminRequest($request);
        $ticket = $this->resolveTicketForRequest($request, $ticket);

        if (!$crossTenantSuperAdmin) {
            $this->authorize('update', $ticket);
        }

        $data = $request->validate([
            'message' => ['required', 'string', 'max:1000'],
        ]);

        $msg = TicketMessage::create([
            'ticket_id' => $ticket->id,
            'user_id'   => Auth::id(),
            'message'   => $data['message'],
        ]);

        // Re-open ticket when a new message is sent
        if (!$ticket->active) {
            $ticket->update(['active' => true]);
        }

        return response(new TicketMessageResource($msg->load('user')), Response::HTTP_CREATED);
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

    /**
     * Delete a ticket message.
     * Only the owner or an admin (tickets.delete) may delete.
     */
    public function destroy(TicketMessage $message)
    {
        $user = Auth::user();

        if ($user->id !== $message->user_id && !$user->hasPermissionTo('tickets.delete')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $message->delete();
        return response(null, Response::HTTP_NO_CONTENT);
    }
}

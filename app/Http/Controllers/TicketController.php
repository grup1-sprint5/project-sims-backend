<?php

namespace App\Http\Controllers;

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

        if ($user->hasPermissionTo('tickets.delete')) {
            // Admin or support: all tickets
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
    public function show(Ticket $ticket)
    {
        $this->authorize('view', $ticket);

        return new TicketResource($ticket->load(['messages.user', 'user']));
    }

    /**
     * Update a ticket (title, description, status).
     * Admins can close/reopen; owners can edit their own tickets.
     */
    public function update(Request $request, Ticket $ticket)
    {
        $this->authorize('update', $ticket);

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
    public function destroy(Ticket $ticket)
    {
        $this->authorize('delete', $ticket);

        $ticket->delete();
        return response(null, Response::HTTP_NO_CONTENT);
    }
}

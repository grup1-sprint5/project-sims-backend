<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class TicketMessageController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'ticket_id' => ['required', 'exists:tickets,id'],
            'message' => ['required', 'string', 'max:1000'],
        ]);

        $ticket = Ticket::findOrFail($data['ticket_id']);


        $this->authorize('update', $ticket);

        $msg = TicketMessage::create([
            'ticket_id' => $data['ticket_id'],
            'user_id' => Auth::id(),
            'message' => $data['message'],
        ]);
        
        if (!$ticket->active) {
            $ticket->update(['active' => true]);
        }

        return response($msg, Response::HTTP_CREATED);
    }

    public function destroy(TicketMessage $message)
    {
        $user = Auth::user();

        // Only the owner or an admin with delete permission can delete
        if ($user->id !== $message->user_id && !$user->hasPermissionTo('tickets.delete')) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $message->delete();
        return response(null, Response::HTTP_NO_CONTENT);
    }
}
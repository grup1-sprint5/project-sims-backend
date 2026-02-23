<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;

class TicketPolicy
{
    /**
     * Determine if the user can view any tickets.
     * Only users with 'tickets.view' permission can list (filtered by controller).
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('tickets.view');
    }

    /**
     * Determine if the user can view a specific ticket.
     * Admins can view any ticket, users can only view their own.
     */
    public function view(User $user, Ticket $ticket): bool
    {
        // Admin can view any ticket
        if ($user->hasPermissionTo('tickets.delete')) {
            return true;
        }

        // Users can only view their own tickets
        return $user->hasPermissionTo('tickets.view') && $user->id === $ticket->user_id;
    }

    /**
     * Determine if the user can create a ticket.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('tickets.manage');
    }

    /**
     * Determine if the user can update (respond to) a ticket.
     * Admins can update any ticket, users can only update their own.
     */
    public function update(User $user, Ticket $ticket): bool
    {
        // Admin can update any ticket
        if ($user->hasPermissionTo('tickets.delete')) {
            return true;
        }

        // Users can update their own tickets if they have manage permission
        return $user->hasPermissionTo('tickets.manage') && $user->id === $ticket->user_id;
    }

    /**
     * Determine if the user can delete a ticket.
     * Only admins with 'tickets.delete' permission can delete.
     */
    public function delete(User $user, Ticket $ticket): bool
    {
        return $user->hasPermissionTo('tickets.delete');
    }
}
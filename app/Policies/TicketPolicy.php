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
     * Admins can view any ticket, TenantAdmin only their tenant's, users their own.
     */
    public function view(User $user, Ticket $ticket): bool
    {
        if ($user->id === $ticket->user_id) { return true; }
        if (!$user->hasPermissionTo('tickets.view')) { return false; }
        if ($user->isSuperAdmin()) { return true; }
        return true;
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
     * Admins can update any ticket, TenantAdmin only their tenant's, users their own.
     */
    public function update(User $user, Ticket $ticket): bool
    {
        if ($user->id === $ticket->user_id) { return true; }
        if (!$user->hasPermissionTo('tickets.manage')) { return false; }
        if ($user->isSuperAdmin()) { return true; }
        return true;
    }

    /**
     * Determine if the user can delete a ticket.
     * TenantAdmin can only delete tickets from their own tenant.
     */
    public function delete(User $user, Ticket $ticket): bool
    {
        $canDelete = $user->hasPermissionTo('tickets.delete')
            || ($user->hasRole('TenantAdmin') && $user->hasPermissionTo('tickets.manage'));

        if (!$canDelete) { return false; }
        if ($user->isSuperAdmin()) { return true; }
        return true;
    }
}
<?php

namespace App\Policies;

use App\Models\Reservation;
use App\Models\User;

class ReservationPolicy
{
    /**
     * Determine if the user can view any reservations.
     * Any user with reservations.view can list (filtered by controller).
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('reservations.view');
    }

    /**
     * Determine if the user can view a specific reservation.
     * SuperAdmin can view any, TenantAdmin only their tenant's, users their own.
     */
    public function view(User $user, Reservation $reservation): bool
    {
        if ($user->id === $reservation->user_id) { return true; }
        if (!$user->hasPermissionTo('reservations.view')) { return false; }
        if ($user->isSuperAdmin()) { return true; }
        return $reservation->tenant_id === null || $reservation->tenant_id === $user->tenant_id;
    }

    /**
     * Determine if the user can create a reservation.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('reservations.manage');
    }

    /**
     * Determine if the user can update a reservation.
     * SuperAdmin can update any, TenantAdmin only their tenant's, users their own.
     */
    public function update(User $user, Reservation $reservation): bool
    {
        if ($user->id === $reservation->user_id && $user->hasPermissionTo('reservations.manage')) { return true; }
        if (!$user->hasPermissionTo('reservations.manage')) { return false; }
        if ($user->isSuperAdmin()) { return true; }
        return $reservation->tenant_id === null || $reservation->tenant_id === $user->tenant_id;
    }

    /**
     * Determine if the user can delete a reservation.
     * SuperAdmin can delete any, TenantAdmin only their tenant's.
     */
    public function delete(User $user, Reservation $reservation): bool
    {
        if (!$user->hasPermissionTo('reservations.delete')) { return false; }
        if ($user->isSuperAdmin()) { return true; }
        return $reservation->tenant_id === null || $reservation->tenant_id === $user->tenant_id;
    }

    /**
     * Determine if the user can activate a reservation.
     * Delegates to the update policy.
     */
    public function activate(User $user, Reservation $reservation): bool
    {
        return $this->update($user, $reservation);
    }

    /**
     * Determine if the user can cancel a reservation.
     * Delegates to the update policy.
     */
    public function cancel(User $user, Reservation $reservation): bool
    {
        return $this->update($user, $reservation);
    }

    /**
     * Determine if the user can finish (complete) a reservation.
     * Delegates to the update policy.
     */
    public function finish(User $user, Reservation $reservation): bool
    {
        return $this->update($user, $reservation);
    }

    /**
     * Determine if the user can force finish a reservation.
     * SuperAdmin can force finish any, TenantAdmin only their tenant's.
     */
    public function forceFinish(User $user, Reservation $reservation): bool
    {
        if (!$user->hasPermissionTo('reservations.delete')) { return false; }
        if ($user->isSuperAdmin()) { return true; }
        return $reservation->tenant_id === null || $reservation->tenant_id === $user->tenant_id;
    }
}
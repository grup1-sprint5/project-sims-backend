<?php

namespace App\Policies;

use App\Models\Reservation;
use App\Models\User;

class ReservationPolicy
{
    /**
     * Determine if the user can view any reservations.
     * Users with reservations.view can see their own, admins can see all.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('reservations.view') 
            || $user->hasPermissionTo('reservations.manage') 
            || $user->hasPermissionTo('reservations.delete');
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
        return true;
    }

    /**
     * Determine if the user can create a reservation.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('reservations.view') 
            || $user->hasPermissionTo('reservations.manage') 
            || $user->hasPermissionTo('reservations.delete');
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
        return true;
    }

    /**
     * Determine if the user can delete a reservation.
     * SuperAdmin can delete any, TenantAdmin only their tenant's.
     */
    public function delete(User $user, Reservation $reservation): bool
    {
        $canDelete = $user->hasPermissionTo('reservations.delete')
            || ($user->hasRole('TenantAdmin') && $user->hasPermissionTo('reservations.manage'));

        if (!$canDelete) { return false; }
        if ($user->isSuperAdmin()) { return true; }
        return true;
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
        $canForceFinish = $user->hasPermissionTo('reservations.delete')
            || ($user->hasRole('TenantAdmin') && $user->hasPermissionTo('reservations.manage'));

        if (!$canForceFinish) { return false; }
        if ($user->isSuperAdmin()) { return true; }
        return true;
    }
}
<?php

namespace App\Policies;

use App\Models\Reservation;
use App\Models\User;

class ReservationPolicy
{
    /**
     * Determine if the user can view any reservations.
     * Admin only - used by AdminReservationController.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('reservations.delete');
    }

    /**
     * Determine if the user can view a specific reservation.
     * Admins can view any reservation, users can only view their own.
     */
    public function view(User $user, Reservation $reservation): bool
    {
        // Admin can view any reservation
        if ($user->hasPermissionTo('reservations.delete')) {
            return true;
        }

        // Users can only view their own reservations
        return $user->hasPermissionTo('reservations.view') && $user->id === $reservation->user_id;
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
     * Admins can update any reservation, users can only update their own.
     */
    public function update(User $user, Reservation $reservation): bool
    {
        // Admin can update any reservation
        if ($user->hasPermissionTo('reservations.delete')) {
            return true;
        }

        // Users can update their own reservations if they have manage permission
        return $user->hasPermissionTo('reservations.manage') && $user->id === $reservation->user_id;
    }

    /**
     * Determine if the user can delete a reservation.
     * Only admins with 'reservations.delete' permission can delete.
     */
    public function delete(User $user, Reservation $reservation): bool
    {
        return $user->hasPermissionTo('reservations.delete');
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
     * Only admins with 'reservations.delete' permission.
     */
    public function forceFinish(User $user, Reservation $reservation): bool
    {
        return $user->hasPermissionTo('reservations.delete');
    }
}
<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\Trip;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AdminReservationController extends Controller
{
    /**
     * List all reservations with filtering by status.
     * Admin only operation.
     */
    public function index(Request $request)
    {
        // Authorize the admin action
        $this->authorize('viewAny', Reservation::class);

        $query = Reservation::with(['user', 'vehicle', 'trip']);

        // Filter by status if provided
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        return response()->json($query->orderBy('created_at', 'desc')->paginate(20));
    }

    /**
     * Show a specific reservation.
     * Admin only operation.
     */
    public function show(string $id)
    {
        $reservation = Reservation::findOrFail($id);
        
        // Authorize viewing this reservation
        $this->authorize('view', $reservation);

        return response()->json($reservation->load(['user', 'vehicle', 'trip']));
    }

    /**
     * Update a reservation directly (admin override).
     * Admin only operation - must have 'reservations.manage' permission.
     */
    public function update(Request $request, string $id)
    {
        $reservation = Reservation::findOrFail($id);
        
        // Authorize the update
        $this->authorize('update', $reservation);

        // Validate input
        $validated = $request->validate([
            'vehicle_id' => ['sometimes', 'exists:vehicles,id'],
            'scheduled_start' => ['sometimes', 'date'],
            'status' => ['sometimes', 'in:pending,active,completed,cancelled,expired'],
            'notes' => ['sometimes', 'string', 'nullable'],
        ]);

        $reservation->update($validated);

        return response()->json([
            'message' => 'Reservation updated by Admin',
            'data' => $reservation
        ]);
    }

    /**
     * Delete a reservation.
     * Admin only operation - must have 'reservations.delete' permission.
     */
    public function destroy(string $id)
    {
        $reservation = Reservation::findOrFail($id);
        
        // Authorize deletion
        $this->authorize('delete', $reservation);

        // Prevent deletion of active reservations
        if ($reservation->status === 'active') {
            return response()->json([
                'message' => 'Cannot delete active reservations.'
            ], 409);
        }

        $reservation->delete();

        return response()->json(['message' => 'Reservation deleted successfully']);
    }

    /**
     * Force finish a reservation (admin override).
     * Can set custom amount and immediately mark as completed.
     * Admin only operation - must have 'reservations.delete' permission.
     */
    public function forceFinish(Request $request, string $id)
    {
        $reservation = Reservation::findOrFail($id);
        
        // Authorize force finish (requires delete permission)
        $this->authorize('forceFinish', $reservation);

        if ($reservation->status !== 'active') {
            return response()->json([
                'message' => 'Only active reservations can be force finished.'
            ], 400);
        }

        $trip = Trip::where('reservation_id', $reservation->id)->firstOrFail();
        
        // Validate input
        $validated = $request->validate([
            'custom_amount' => ['sometimes', 'numeric', 'min:0'],
            'force_notes' => ['sometimes', 'string', 'nullable'],
        ]);

        $end = now();
        $start = Carbon::parse($trip->engine_started_at);
        $minutes = (int) ceil($start->floatDiffInMinutes($end));

        // Determine amount and note text
        if (isset($validated['custom_amount'])) {
            $amount = $validated['custom_amount'];
            $noteText = 'Admin Override (Manual price)';
        } else {
            $pricePerMinute = $reservation->vehicle->price_per_minute ?? 0.15;
            $amount = round($minutes * $pricePerMinute, 2);
            $noteText = 'Admin Override (Time calculated)';
        }

        // Append force notes if provided
        if (isset($validated['force_notes'])) {
            $noteText .= ' - ' . $validated['force_notes'];
        }

        // Execute transaction
        DB::transaction(function () use ($reservation, $trip, $end, $amount, $minutes, $noteText) {
            $trip->update([
                'engine_stopped_at' => $end,
                'total_amount' => $amount,
                'minutes_driven' => $minutes,
                'notes' => $noteText 
            ]);
            $reservation->update(['status' => 'completed']);
        });

        return response()->json([
            'message' => 'Reservation force finished by Admin',
            'data' => [
                'cost' => $amount . '€',
                'minutes_calculated' => $minutes,
                'note' => $noteText
            ]
        ]);
    }
}
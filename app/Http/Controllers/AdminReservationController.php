<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\Trip;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdminReservationController extends Controller
{
    /**
     * Revenue summary for the current tenant (paid reservations only).
     */
    public function revenueSummary(Request $request)
    {
        $this->authorize('viewAny', Reservation::class);

        $validated = $request->validate([
            'period' => ['nullable', 'in:today,7d,30d,year,total'],
        ]);

        $period = $validated['period'] ?? 'total';

        if (!Schema::hasTable('reservations')) {
            return response()->json([
                'period' => $period,
                'currency' => 'EUR',
                'gross_revenue' => 0,
                'paid_reservations' => 0,
                'average_ticket' => 0,
                'pending_payments' => 0,
            ]);
        }

        $start = match ($period) {
            'today' => now()->startOfDay(),
            '7d' => now()->subDays(7),
            '30d' => now()->subDays(30),
            'year' => now()->startOfYear(),
            default => null,
        };

        $paidQuery = Reservation::query()->where('payment_status', 'paid');

        if ($start) {
            $paidQuery->whereRaw('COALESCE(paid_at, updated_at, created_at) >= ?', [$start]);
        }

        $paidReservations = (clone $paidQuery)->count();
        $grossRevenue = round((float) (clone $paidQuery)->sum('total_price'), 2);

        $pendingPaymentsQuery = Reservation::query()->where('payment_status', 'unpaid');
        if ($start) {
            $pendingPaymentsQuery->where('created_at', '>=', $start);
        }

        return response()->json([
            'period' => $period,
            'currency' => 'EUR',
            'gross_revenue' => $grossRevenue,
            'paid_reservations' => $paidReservations,
            'average_ticket' => $paidReservations > 0 ? round($grossRevenue / $paidReservations, 2) : 0,
            'pending_payments' => (clone $pendingPaymentsQuery)->count(),
        ]);
    }

    /**
     * List all reservations with filtering by status.
     * Admin only operation.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Reservation::class);

        if (!Schema::hasTable('reservations')) {
            $perPage = (int) $request->integer('per_page', 20);

            return response()->json([
                'current_page' => 1,
                'data' => [],
                'first_page_url' => $request->url() . '?page=1',
                'from' => null,
                'last_page' => 1,
                'last_page_url' => $request->url() . '?page=1',
                'links' => [],
                'next_page_url' => null,
                'path' => $request->url(),
                'per_page' => $perPage,
                'prev_page_url' => null,
                'to' => null,
                'total' => 0,
            ]);
        }

        $query = Reservation::with(['user', 'vehicle', 'trip', 'tenant']);

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

<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\Vehicle;
use App\Models\Trip;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class ReservationController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', Reservation::class);

        $user = Auth::user();

        // Admin o Manager puede ver todas
        if ($user->hasPermissionTo('reservations.manage')) {
            return Reservation::with(['user', 'vehicle', 'trip'])
                ->orderBy('scheduled_start', 'desc')
                ->get();
        }

        return Reservation::where('user_id', $user->id)
            ->with(['vehicle', 'trip'])
            ->orderBy('scheduled_start', 'desc')
            ->get();
    }

    public function store(Request $request)
    {
        $this->authorize('create', Reservation::class); 

        $validated = $request->validate([
            'vehicle_id' => 'required|exists:vehicles,id',
            'scheduled_start' => 'required|date|after_or_equal:now',
        ]);

        return DB::transaction(function () use ($validated, $request) {
            $vehicle = Vehicle::where('id', $validated['vehicle_id'])->lockForUpdate()->firstOrFail();
            
            $requestedStart = Carbon::parse($validated['scheduled_start']);

            $isBusy = Reservation::where('vehicle_id', $vehicle->id)
                ->where(function ($query) {
                    $query->where('status', 'active')
                          ->orWhere(function ($q) {
                              $q->where('status', 'pending')
                                ->where('activation_deadline', '>', now());
                          });
                })
                ->exists();

            if ($isBusy) {
                return response()->json([
                    'message' => 'Vehicle unavailable or currently in use.'
                ], 409);
            }

            $activationDeadline = $requestedStart->copy()->addMinutes(20);

            $reservation = $request->user()->reservations()->create([
                'vehicle_id' => $vehicle->id,
                'scheduled_start' => $requestedStart,
                'activation_deadline' => $activationDeadline,
                'status' => 'pending',
            ]);

            return response()->json([
                'message' => 'Reservation created. You have 20 minutes to activate.',
                'data' => $reservation
            ], 201);
        });
    }

    public function show(Reservation $reservation)
    {
        $this->authorize('view', $reservation);
        return $reservation->load(['vehicle', 'trip']);
    }

    public function activate(Request $request, Reservation $reservation)
    {
        $this->authorize('activate', $reservation);

        if ($reservation->status !== 'pending') {
            return response()->json(['message' => 'Reservation is not pending.'], 400);
        }

        if (now()->greaterThan($reservation->activation_deadline)) {
            $reservation->update(['status' => 'expired']);
            return response()->json(['message' => 'Reservation expired.'], 403);
        }

        $trip = DB::transaction(function () use ($reservation) {
            $reservation->update(['status' => 'active']);
            
            return Trip::create([
                'reservation_id' => $reservation->id,
                'engine_started_at' => now(),
            ]);
        });

        return response()->json([
            'message' => 'Vehicle activated. Engine started.',
            'trip_id' => $trip->id,
            'started_at' => $trip->engine_started_at
        ], 200);
    }

    public function finish(Request $request, Reservation $reservation)
    {
        $this->authorize('finish', $reservation);

        if ($reservation->status !== 'active') {
            return response()->json(['message' => 'Only active reservations can be finished.'], 400);
        }

        $trip = Trip::where('reservation_id', $reservation->id)->firstOrFail();

        $start = Carbon::parse($trip->engine_started_at);
        $end = now();
        $minutes = max(1, $start->diffInMinutes($end)); 
        
        $pricePerMinute = $reservation->vehicle->price_per_minute ?? 0.15;
        $amount = round($minutes * $pricePerMinute, 2);

        DB::transaction(function () use ($reservation, $trip, $end, $amount, $minutes) {
            $trip->update([
                'engine_stopped_at' => $end,
                'total_amount' => $amount,
                'minutes_driven' => $minutes
            ]);
            $reservation->update(['status' => 'completed']);
        });

        return response()->json([
            'message' => 'Trip finished.',
            'minutes' => $minutes,
            'cost' => $amount . '€'
        ]);
    }

    public function cancel(Reservation $reservation)
    {
        $this->authorize('cancel', $reservation);

        if ($reservation->status !== 'pending') {
            return response()->json(['message' => 'Only pending reservations can be cancelled.'], 400);
        }

        $reservation->update(['status' => 'cancelled']);
        return response()->json(['message' => 'Reservation cancelled.']);
    }

    public function forceFinish(Request $request, Reservation $reservation)
    {
        $this->authorize('forceFinish', $reservation);

        if ($reservation->status !== 'active') {
            return response()->json(['message' => 'Only active reservations can be finished.'], 400);
        }

        $trip = Trip::where('reservation_id', $reservation->id)->firstOrFail();
        
        $end = now();
        $start = Carbon::parse($trip->engine_started_at);
        $minutes = (int) ceil($start->floatDiffInMinutes($end));

        if ($request->has('custom_amount')) {
            $amount = $request->custom_amount;
            $noteText = 'Admin Override (Manual price)';
        } else {
             $pricePerMinute = $reservation->vehicle->price_per_minute ?? 0.15;
             $amount = round($minutes * $pricePerMinute, 2);
             $noteText = 'Admin Override (Time calculated)';
        }

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
            'message' => 'Trip finished by Admin.',
            'cost' => $amount . '€',
            'minutes' => $minutes
        ]);
    }
}
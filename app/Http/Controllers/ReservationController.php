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

        // Admin/Manager can see reservations (scoped by tenant via global scope)
        if ($user->hasPermissionTo('reservations.manage')) {
            $query = Reservation::with(['user', 'vehicle', 'trip', 'tenant'])
                ->orderBy('scheduled_start', 'desc');
            return $query->get();
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
            'scheduled_start' => [
                'required',
                'date',
                'after_or_equal:' . now()->subMinutes(5)->toDateTimeString(),
            ],
            'scheduled_end' => 'required|date|after:scheduled_start',
        ]);

        return DB::transaction(function () use ($validated, $request) {
            $vehicle = Vehicle::where('id', $validated['vehicle_id'])->lockForUpdate()->firstOrFail();
            $user = $request->user();
            
            // Verificar tenant
            if ($user->tenant_id !== null && $vehicle->tenant_id !== $user->tenant_id) {
                return response()->json([
                    'message' => 'No pots reservar vehicles d\'altres tenants'
                ], 403);
            }
            
            $requestedStart = Carbon::parse($validated['scheduled_start']);
            $requestedEnd = Carbon::parse($validated['scheduled_end']);

            // Verificar disponibilitat - detectar overlaps estrictes
            $hasConflict = Reservation::where('vehicle_id', $vehicle->id)
                ->whereIn('status', ['pending', 'active'])
                ->where(function ($query) use ($requestedStart, $requestedEnd) {
                    $query->where(function ($q) use ($requestedStart, $requestedEnd) {
                        // Reserves amb scheduled_end definit
                        $q->whereNotNull('scheduled_end')
                          ->where(function ($sq) use ($requestedStart, $requestedEnd) {
                              // Overlap si: start < otherEnd && end > otherStart
                              $sq->where('scheduled_start', '<', $requestedEnd)
                                 ->where('scheduled_end', '>', $requestedStart);
                          });
                    })->orWhere(function ($q) use ($requestedStart) {
                        // Reserves sense scheduled_end (legacy) - marge de 2h
                        $q->whereNull('scheduled_end')
                          ->where('scheduled_start', '>=', $requestedStart->copy()->subHours(2))
                          ->where('scheduled_start', '<=', $requestedStart->copy()->addHours(2));
                    });
                })
                ->exists();

            if ($hasConflict) {
                return response()->json([
                    'message' => 'El vehicle ja està reservat en aquest horari'
                ], 409);
            }

            // Calcular preu
            $priceData = $this->calculateReservationPrice($requestedStart, $requestedEnd);
            
            $activationDeadline = $requestedStart->copy()->addMinutes(20);

            $reservation = $user->reservations()->create([
                'vehicle_id' => $vehicle->id,
                'tenant_id' => $user->tenant_id,
                'scheduled_start' => $requestedStart,
                'scheduled_end' => $requestedEnd,
                'activation_deadline' => $activationDeadline,
                'total_price' => $priceData['final_price'],
                'status' => 'pending',
            ]);

            return response()->json([
                'message' => 'Reserva creada correctament',
                'data' => $reservation->load('vehicle'),
                'price_details' => $priceData
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

    /**
     * Calcular preu estimat sense crear reserva
     */
    public function calculatePrice(Request $request)
    {
        $this->authorize('create', Reservation::class);

        $validated = $request->validate([
            'scheduled_start' => 'required|date',
            'scheduled_end' => 'required|date|after:scheduled_start',
        ]);

        $start = Carbon::parse($validated['scheduled_start']);
        $end = Carbon::parse($validated['scheduled_end']);
        
        $priceData = $this->calculateReservationPrice($start, $end);

        return response()->json($priceData);
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

    /**
     * Calcular preu segons regles de pricing:
     * - 0,10 € per minut
     * - màxim 5 € per hora
     * - màxim 45 € per dia (24h)
     */
    private function calculateReservationPrice(Carbon $start, Carbon $end): array
    {
        $totalMinutes = $start->diffInMinutes($end);
        
        $pricePerMinute = 0.10;
        $basePrice = $totalMinutes * $pricePerMinute;
        
        $fullHours = floor($totalMinutes / 60);
        $remainingMinutes = $totalMinutes % 60;
        
        $maxPricePerHour = 5.00;
        $priceForFullHours = $fullHours * $maxPricePerHour;
        $priceForRemainingMinutes = min($remainingMinutes * $pricePerMinute, $maxPricePerHour);
        
        $priceWithHourlyLimit = $priceForFullHours + $priceForRemainingMinutes;
        
        $fullDays = floor($totalMinutes / 1440);
        $minutesAfterDays = $totalMinutes % 1440;
        $maxPricePerDay = 45.00;
        
        if ($fullDays > 0) {
            $priceForFullDays = $fullDays * $maxPricePerDay;
            
            // Per la part restant després dels dies complets, aplicar topall horari
            $hoursRemaining = floor($minutesAfterDays / 60);
            $minutesRemaining = $minutesAfterDays % 60;
            
            // Preu amb topall horari aplicat a les hores restants
            $priceForRemainingHours = $hoursRemaining * $maxPricePerHour;
            $priceForRemainingMinutes = $minutesRemaining * $pricePerMinute;
            $priceForRemaining = $priceForRemainingHours + $priceForRemainingMinutes;
            
            $finalPrice = $priceForFullDays + $priceForRemaining;
        } else {
            // Si no hi ha dies complets, aplicar el mínim entre preu amb topall horari i topall diari
            $finalPrice = min($priceWithHourlyLimit, $maxPricePerDay);
        }
        
        return [
            'total_minutes' => $totalMinutes,
            'hours' => round($totalMinutes / 60, 2),
            'days' => round($totalMinutes / 1440, 2),
            'base_price' => round($basePrice, 2),
            'final_price' => round($finalPrice, 2),
            'price_per_minute' => $pricePerMinute,
            'max_per_hour' => $maxPricePerHour,
            'max_per_day' => $maxPricePerDay,
        ];
    }
}
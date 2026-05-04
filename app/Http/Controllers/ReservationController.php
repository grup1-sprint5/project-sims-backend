<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\Tenant;
use App\Models\Vehicle;
use App\Models\Trip;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class ReservationController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Reservation::class);

        $user = Auth::user();
        $globalViewRequested = filter_var($request->input('global'), FILTER_VALIDATE_BOOLEAN);

        $this->expireOverduePendingReservations($user->hasPermissionTo('reservations.manage') ? null : $user->id);

        // Admin/Manager can see reservations (scoped by tenant via global scope)
        if ($user->hasPermissionTo('reservations.manage')) {
            // SuperAdmin can explicitly request a global cross-tenant view.
            if ($user->isSuperAdmin() && $globalViewRequested) {
                return $this->indexForSuperAdmin();
            }

            $reservations = Reservation::with(['user', 'vehicle', 'trip'])
                ->orderBy('scheduled_start', 'desc');

            return $this->normalizeReservationCollectionForResponse($reservations->get());
        }

        $reservations = Reservation::where('user_id', $user->id)
            ->with(['vehicle', 'trip'])
            ->orderBy('scheduled_start', 'desc')
            ->get();

        return $this->normalizeReservationCollectionForResponse($reservations);
    }

    private function indexForSuperAdmin()
    {
        $originalTenant = function_exists('tenant') ? tenant() : null;
        $rows = collect();

        $tenants = Tenant::query()
            ->where('active', true)
            ->where('slug', '!=', 'central')
            ->get();

        foreach ($tenants as $tenant) {
            if (!$tenant instanceof Tenant) {
                continue;
            }

            tenancy()->initialize($tenant);

            $tenantReservations = Reservation::with(['user', 'vehicle', 'trip'])
                ->orderBy('scheduled_start', 'desc')
                ->get()
                ->map(function (Reservation $reservation) use ($tenant) {
                    $data = $reservation->toArray();
                    $data = $this->normalizeReservationArrayForResponse($data);
                    $data['tenant_id'] = $tenant->slug;
                    $data['tenant'] = [
                        'id' => $tenant->slug,
                        'name' => $tenant->name,
                    ];
                    return $data;
                });

            $rows = $rows->concat($tenantReservations);
        }

        if ($originalTenant) {
            tenancy()->initialize($originalTenant);
        } else {
            tenancy()->end();
        }

        return $rows->sortByDesc('scheduled_start')->values();
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
            $finalPrice = (float) ($priceData['final_price'] ?? 0);
            
            $activationDeadline = $requestedStart->copy()->addMinutes(20);

            $paymentProvider = 'stripe';
            $paymentStatus = 'unpaid';
            $paidAt = null;

            // If user already has wallet funds, auto-charge and keep booking ready to activate.
            $currentWalletBalance = (float) ($user->wallet_balance ?? 0);
            if ($finalPrice > 0 && $currentWalletBalance >= $finalPrice) {
                $user->update([
                    'wallet_balance' => round($currentWalletBalance - $finalPrice, 2),
                ]);
                $paymentProvider = 'wallet';
                $paymentStatus = 'paid';
                $paidAt = now();
            }

            $reservation = $user->reservations()->create([
                'vehicle_id' => $vehicle->id,
                'scheduled_start' => $requestedStart,
                'scheduled_end' => $requestedEnd,
                'activation_deadline' => $activationDeadline,
                'total_price' => $finalPrice,
                'payment_provider' => $paymentProvider,
                'payment_status' => $paymentStatus,
                'paid_at' => $paidAt,
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

        $this->expireReservationIfOverdue($reservation);

        return $this->normalizeReservationForResponse($reservation->load(['vehicle', 'trip']));
    }

    public function activate(Request $request, Reservation $reservation)
    {
        $this->authorize('activate', $reservation);

        $this->expireReservationIfOverdue($reservation);

        if ($reservation->status !== 'pending') {
            return response()->json(['message' => 'Reservation is not pending.'], 400);
        }

        if (($reservation->total_price ?? 0) > 0 && $reservation->payment_status !== 'paid') {
            return response()->json(['message' => 'Reservation must be paid before activation.'], 402);
        }

        if ($reservation->isPendingAndOverdue()) {
            $reservation->markAsExpired();
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
     * Crea una sessio de Stripe Checkout per pagar una reserva pending.
     */
    public function createStripeCheckoutSession(Request $request, Reservation $reservation)
    {
        $this->authorize('view', $reservation);

        $this->expireReservationIfOverdue($reservation);

        if ($reservation->status !== 'pending') {
            return response()->json(['message' => 'Only pending reservations can be paid.'], 400);
        }

        if ($reservation->payment_status === 'paid') {
            return response()->json(['message' => 'Reservation is already paid.'], 400);
        }

        $validated = $request->validate([
            'success_url' => 'nullable|url',
            'cancel_url' => 'nullable|url',
        ]);

        $secret = (string) config('services.stripe.secret');
        if ($secret === '') {
            return response()->json(['message' => 'Stripe is not configured.'], 500);
        }

        $amountCents = (int) round(((float) $reservation->total_price) * 100);
        if ($amountCents <= 0) {
            return response()->json(['message' => 'Invalid reservation amount.'], 422);
        }

        $tenantId = function_exists('tenancy') && tenancy()->initialized
            ? (string) tenancy()->tenant()->id
            : '';
        $successUrl = $validated['success_url'] ?? config('services.stripe.success_url');
        $cancelUrl = $validated['cancel_url'] ?? config('services.stripe.cancel_url');

        if (!$successUrl || !$cancelUrl) {
            return response()->json(['message' => 'Missing success/cancel URL for Stripe checkout.'], 500);
        }

        if ($tenantId === '') {
            return response()->json(['message' => 'Tenant context missing.'], 500);
        }

        $sessionResponse = Http::asForm()
            ->withBasicAuth($secret, '')
            ->post('https://api.stripe.com/v1/checkout/sessions', [
                'mode' => 'payment',
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
                'client_reference_id' => (string) $reservation->id,
                'metadata[reservation_id]' => (string) $reservation->id,
                'metadata[tenant_id]' => (string) $tenantId,
                'metadata[user_id]' => (string) $reservation->user_id,
                'line_items[0][price_data][currency]' => 'eur',
                'line_items[0][price_data][unit_amount]' => $amountCents,
                'line_items[0][price_data][product_data][name]' => 'Reserva #' . $reservation->id,
                'line_items[0][quantity]' => 1,
            ]);

        if ($sessionResponse->failed()) {
            return response()->json([
                'message' => 'Stripe checkout session could not be created.',
                'stripe_error' => $sessionResponse->json(),
            ], 502);
        }

        $payload = $sessionResponse->json();

        $reservation->update([
            'payment_provider' => 'stripe',
            'payment_status' => 'unpaid',
            'stripe_checkout_session_id' => $payload['id'] ?? null,
        ]);

        return response()->json([
            'message' => 'Stripe checkout session created.',
            'session_id' => $payload['id'] ?? null,
            'checkout_url' => $payload['url'] ?? null,
            'publishable_key' => config('services.stripe.key'),
        ]);
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
     */
    private function calculateReservationPrice(Carbon $start, Carbon $end): array
    {
        $totalMinutes = $start->diffInMinutes($end);
        
        $pricePerMinute = 0.10;
        $basePrice = $totalMinutes * $pricePerMinute;
        
        $fullHours = floor($totalMinutes / 60);
        $remainingMinutes = $totalMinutes % 60;
        
        $maxPricePerHour = 5.00;

        // Per cada hora completa: cobrar el mínim entre 60*ppm i el topall horari
        $hourPrice = min(60 * $pricePerMinute, $maxPricePerHour);
        $priceForFullHours = $fullHours * $hourPrice;
        $priceForRemainingMinutes = min($remainingMinutes * $pricePerMinute, $maxPricePerHour);

        $priceWithHourlyLimit = $priceForFullHours + $priceForRemainingMinutes;

        // Mai cobrar més que el preu base
        $finalPrice = min($basePrice, $priceWithHourlyLimit);
        
        return [
            'total_minutes' => $totalMinutes,
            'hours' => round($totalMinutes / 60, 2),
            'base_price' => round($basePrice, 2),
            'final_price' => round($finalPrice, 2),
            'price_per_minute' => $pricePerMinute,
            'max_per_hour' => $maxPricePerHour,
        ];
    }

    private function expireOverduePendingReservations(?int $userId = null): int
    {
        $now = now();
        $query = Reservation::pendingAndOverdue($now);

        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        return $query->update([
            'status' => 'expired',
            'cancelled_at' => $now,
        ]);
    }

    private function expireReservationIfOverdue(Reservation $reservation): void
    {
        if (!$reservation->isPendingAndOverdue()) {
            return;
        }

        $reservation->markAsExpired();
        $reservation->refresh();
    }

    private function normalizeReservationCollectionForResponse($reservations)
    {
        return $reservations->map(function (Reservation $reservation) {
            return $this->normalizeReservationForResponse($reservation);
        });
    }

    private function normalizeReservationForResponse(Reservation $reservation): Reservation
    {
        if ($reservation->scheduled_end === null) {
            $reservation->setAttribute('scheduled_end', $reservation->activation_deadline ?? $reservation->scheduled_start);
        }

        return $reservation;
    }

    private function normalizeReservationArrayForResponse(array $reservation): array
    {
        if (($reservation['scheduled_end'] ?? null) !== null) {
            return $reservation;
        }

        $reservation['scheduled_end'] = $reservation['activation_deadline'] ?? ($reservation['scheduled_start'] ?? null);

        return $reservation;
    }
}
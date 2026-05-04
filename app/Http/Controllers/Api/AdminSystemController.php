<?php

namespace App\Http\Controllers\Api;

use Stancl\Tenancy\Database\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

class AdminSystemController extends Controller
{
    /**
     * Get all vehicles from all tenants
     */
    public function vehicles(Request $request)
    {
        // Get active tenants via tenancy model so we can initialize correctly
        $tenants = Tenant::where('active', true)->get();

        $allVehicles = collect();

        foreach ($tenants as $tenant) {
            try {
                tenancy()->initialize($tenant);

                $vehicles = DB::table('vehicles')
                    ->select('*', DB::raw("'{$tenant->id}' as tenant_id"))
                    ->get();

                $allVehicles = $allVehicles->concat($vehicles);
            } catch (\Throwable $e) {
                // Skip tenant if initialization or query fails
            } finally {
                try { tenancy()->end(); } catch (\Throwable $_) {}
            }
        }

        $perPage = (int) $request->input('per_page', 15);
        $page = (int) $request->input('page', 1);

        $paginated = $allVehicles->slice(($page - 1) * $perPage, $perPage);

        return response()->json([
            'data' => $paginated->values(),
            'current_page' => $page,
            'total' => $allVehicles->count(),
            'per_page' => $perPage,
            'last_page' => ceil($allVehicles->count() / $perPage),
        ]);
    }

    /**
     * Get all reservations from all tenants
     */
    public function reservations(Request $request)
    {
        // Use Tenant model to initialize tenancy correctly per tenant
        $tenants = Tenant::where('active', true)->get();

        $allReservations = collect();

        foreach ($tenants as $tenant) {
            try {
                tenancy()->initialize($tenant);

                $reservations = DB::table('reservations')
                    ->select('*', DB::raw("'{$tenant->id}' as tenant_id"))
                    ->get();

                $allReservations = $allReservations->concat($reservations);
            } catch (\Throwable $e) {
                // skip failing tenant
            } finally {
                try { tenancy()->end(); } catch (\Throwable $_) {}
            }
        }

        $perPage = (int) $request->input('per_page', 15);
        $page = (int) $request->input('page', 1);

        $paginated = $allReservations->slice(($page - 1) * $perPage, $perPage);

        return response()->json([
            'data' => $paginated->values(),
            'current_page' => $page,
            'total' => $allReservations->count(),
            'per_page' => $perPage,
            'last_page' => ceil($allReservations->count() / $perPage),
        ]);
    }

    /**
     * Get all geofences from all tenants
     */
    public function geofences(Request $request)
    {
        $tenants = Tenant::where('active', true)->get();

        $allGeofences = collect();

        foreach ($tenants as $tenant) {
            try {
                tenancy()->initialize($tenant);

                $geofences = DB::table('geofences')
                    ->select('*', DB::raw("'{$tenant->id}' as tenant_id"))
                    ->get();

                $allGeofences = $allGeofences->concat($geofences);
            } catch (\Throwable $e) {
                // skip failing tenant
            } finally {
                try { tenancy()->end(); } catch (\Throwable $_) {}
            }
        }

        $perPage = (int) $request->input('per_page', 15);
        $page = (int) $request->input('page', 1);

        $paginated = $allGeofences->slice(($page - 1) * $perPage, $perPage);

        return response()->json([
            'data' => $paginated->values(),
            'current_page' => $page,
            'total' => $allGeofences->count(),
            'per_page' => $perPage,
            'last_page' => ceil($allGeofences->count() / $perPage),
        ]);
    }

    /**
     * Get all geofence events from all tenants
     */
    public function geofenceEvents(Request $request)
    {
        $tenants = Tenant::where('active', true)->get();

        $allEvents = collect();

        foreach ($tenants as $tenant) {
            try {
                tenancy()->initialize($tenant);

                $events = DB::table('geofence_events')
                    ->select('*', DB::raw("'{$tenant->id}' as tenant_id"))
                    ->get();

                $allEvents = $allEvents->concat($events);
            } catch (\Throwable $e) {
                // skip failing tenant
            } finally {
                try { tenancy()->end(); } catch (\Throwable $_) {}
            }
        }

        $perPage = (int) $request->input('per_page', 15);
        $page = (int) $request->input('page', 1);

        $paginated = $allEvents->slice(($page - 1) * $perPage, $perPage);

        return response()->json([
            'data' => $paginated->values(),
            'current_page' => $page,
            'total' => $allEvents->count(),
            'per_page' => $perPage,
            'last_page' => ceil($allEvents->count() / $perPage),
        ]);
    }

    /**
     * Get all tenants
     */
    public function tenants(Request $request)
    {
        $centralConnection = (string) (config('tenancy.database.central_connection')
            ?? config('database.default')
            ?? 'pgsql');

        $query = DB::connection($centralConnection)
            ->table('tenants')
            ->select('id', 'name', 'data', 'active', 'created_at', 'updated_at');

        if ($search = $request->input('search')) {
            $query->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($search) . '%']);
        }

        $perPage = (int) $request->input('per_page', 15);
        $tenants = $query->paginate(min($perPage, 100));

        return response()->json($tenants);
    }

    /**
     * Get all tickets from all tenants
     */
    public function tickets(Request $request)
    {
        $tenants = Tenant::where('active', true)->get();

        $allTickets = collect();

        foreach ($tenants as $tenant) {
            try {
                tenancy()->initialize($tenant);

                $tickets = DB::table('tickets')
                    ->select('*', DB::raw("'{$tenant->id}' as tenant_id"))
                    ->get();

                $allTickets = $allTickets->concat($tickets);
            } catch (\Throwable $e) {
                // skip failing tenant
            } finally {
                try { tenancy()->end(); } catch (\Throwable $_) {}
            }
        }

        $perPage = (int) $request->input('per_page', 15);
        $page = (int) $request->input('page', 1);

        $paginated = $allTickets->slice(($page - 1) * $perPage, $perPage);

        return response()->json([
            'data' => $paginated->values(),
            'current_page' => $page,
            'total' => $allTickets->count(),
            'per_page' => $perPage,
            'last_page' => ceil($allTickets->count() / $perPage),
        ]);
    }
}

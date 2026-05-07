<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\Tenant;

class AdminSystemController extends Controller
{
    public function vehicles(Request $request)
    {
        return $this->paginatedTenantRows($request, 'vehicles', function ($query) use ($request) {
            $query->when(Schema::hasColumn('vehicles', 'deleted_at'), fn ($q) => $q->whereNull('deleted_at'));

            if ($search = trim((string) $request->input('search', ''))) {
                $query->where(function ($q) use ($search) {
                    $q->where('license_plate', 'ilike', "%{$search}%")
                        ->orWhere('brand', 'ilike', "%{$search}%")
                        ->orWhere('model', 'ilike', "%{$search}%");
                });
            }

            if ($request->has('active')) {
                $query->where('active', filter_var($request->input('active'), FILTER_VALIDATE_BOOLEAN));
            }
        }, 'created_at');
    }

    public function reservations(Request $request)
    {
        return $this->paginatedTenantRows($request, 'reservations', function ($query) use ($request) {
            $query->when(Schema::hasColumn('reservations', 'deleted_at'), fn ($q) => $q->whereNull('deleted_at'));

            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }
        }, 'created_at');
    }

    public function geofences(Request $request)
    {
        return $this->paginatedTenantRows($request, 'geofences', function ($query) use ($request) {
            $query->when(Schema::hasColumn('geofences', 'deleted_at'), fn ($q) => $q->whereNull('deleted_at'));

            if ($name = trim((string) $request->input('name', ''))) {
                $query->where('name', 'ilike', "%{$name}%");
            }

            if ($request->has('active')) {
                $query->where('active', filter_var($request->input('active'), FILTER_VALIDATE_BOOLEAN));
            }
        }, 'updated_at');
    }

    public function geofenceEvents(Request $request)
    {
        return $this->paginatedTenantRows($request, 'geofence_events', function ($query) use ($request) {
            foreach (['event_type', 'geofence_id', 'vehicle_id'] as $field) {
                if ($request->filled($field)) {
                    $query->where($field, $request->input($field));
                }
            }

            if ($request->filled('from')) {
                $query->where('occurred_at', '>=', $request->input('from'));
            }
            if ($request->filled('to')) {
                $query->where('occurred_at', '<=', $request->input('to'));
            }
        }, 'occurred_at');
    }

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

        return response()->json($query->paginate(min($perPage, 100)));
    }

    public function tickets(Request $request)
    {
        return $this->paginatedTenantRows($request, 'tickets', function ($query) use ($request) {
            $query->when(Schema::hasColumn('tickets', 'deleted_at'), fn ($q) => $q->whereNull('deleted_at'));

            if ($search = trim((string) $request->input('search', ''))) {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'ilike', "%{$search}%")
                        ->orWhere('description', 'ilike', "%{$search}%");
                });
            }

            if ($request->has('active')) {
                $query->where('active', filter_var($request->input('active'), FILTER_VALIDATE_BOOLEAN));
            }
        }, 'created_at');
    }

    private function paginatedTenantRows(Request $request, string $table, callable $applyFilters, string $sortColumn)
    {
        $rows = collect();
        $tenants = Tenant::where('active', true)->where('id', '!=', 'central')->get();

        foreach ($tenants as $tenant) {
            try {
                tenancy()->initialize($tenant);

                if (!Schema::hasTable($table)) {
                    continue;
                }

                $query = DB::table($table);
                $applyFilters($query);

                $tenantRows = $query->get()->map(fn ($row) => $this->withTenant($row, $tenant));
                $rows = $rows->concat($tenantRows);
            } catch (\Throwable) {
                // Keep the global admin list usable even if one tenant schema is broken.
            } finally {
                try {
                    tenancy()->end();
                } catch (\Throwable) {
                }
            }
        }

        $rows = $rows->sortByDesc(fn ($row) => $row->{$sortColumn} ?? null)->values();
        $perPage = max(1, min((int) $request->input('per_page', 15), 100));
        $page = max(1, (int) $request->input('page', 1));
        $paginated = $rows->slice(($page - 1) * $perPage, $perPage)->values();

        return response()->json([
            'data' => $paginated,
            'current_page' => $page,
            'total' => $rows->count(),
            'per_page' => $perPage,
            'last_page' => (int) ceil($rows->count() / $perPage),
        ]);
    }

    private function withTenant(object $row, Tenant $tenant): object
    {
        $row->tenant_id = (string) $tenant->id;
        $row->tenant = [
            'id' => (string) $tenant->id,
            'name' => $tenant->name,
        ];

        return $row;
    }
}

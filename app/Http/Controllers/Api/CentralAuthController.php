<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Stancl\Tenancy\Database\Models\Domain;
use Throwable;

class CentralAuthController extends Controller
{
    /**
     * Central admin (superadmin) login without tenant context.
     * Used when logging into the central dashboard from the main domain.
     */
    public function loginCentralAdmin(Request $request): JsonResponse
    {
        try {
            $centralConnection = (string) (config('tenancy.database.central_connection')
                ?? config('database.default')
                ?? 'pgsql');

            $validated = $request->validate([
                'email' => ['required', 'email'],
                'password' => ['required', 'string'],
            ]);

            // Authenticate user from central database (no tenant context)
            $user = User::on($centralConnection)
                ->where('email', $validated['email'])
                ->first();

            if (!$user || !Hash::check($validated['password'], $user->password)) {
                throw ValidationException::withMessages([
                    'email' => ['Incorrect credentials.'],
                ]);
            }

            if (!$user->active) {
                return response()->json([
                    'message' => 'User inactive.',
                ], 403);
            }

            // Check if user is superadmin or has central admin access
            $isSuperAdmin = $user->roles()
                ->on($centralConnection)
                ->where('name', 'superadmin')
                ->exists();

            if (!$isSuperAdmin) {
                return response()->json([
                    'message' => 'Only superadmin users can access the central dashboard.',
                ], 403);
            }

            // Generate a personal access token for the central admin
            $token = $user->createToken('central-admin-token')->plainTextToken;

            return response()->json([
                'message' => 'Central admin login successful',
                'token' => $token,
                'user' => $user,
            ]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);
            return response()->json([
                'message' => 'Server error: ' . $e->getMessage(),
                'error' => 'server_error',
            ], 500);
        }
    }

    {
        try {
            $centralConnection = (string) (config('tenancy.database.central_connection')
                ?? config('database.default')
                ?? 'pgsql');

            $validated = $request->validate([
                'organization' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email'],
                'password' => ['required', 'string'],
            ]);

            $organizationRaw = strtolower(trim((string) $validated['organization']));
            $organizationSlug = Str::slug($organizationRaw);

            // Use explicit central connection to avoid issues if tenancy was already partially initialized.
            // Support both schemas:
            // - New schema: tenants.id is a string slug
            // - Legacy schema: tenants.id is numeric and slug may be in a dedicated column
            $hasSlugColumn = Schema::connection($centralConnection)->hasColumn('tenants', 'slug');
            $idColumnType = null;
            try {
                $idColumnType = strtolower((string) Schema::connection($centralConnection)->getColumnType('tenants', 'id'));
            } catch (Throwable $e) {
                report($e);
            }

            $tenantQuery = Tenant::on($centralConnection)
                ->whereRaw('LOWER(name) = ?', [$organizationRaw]);

            if ($hasSlugColumn) {
                $tenantQuery->orWhereRaw('LOWER(slug) = ?', [$organizationRaw]);
                if ($organizationSlug !== '' && $organizationSlug !== $organizationRaw) {
                    $tenantQuery->orWhereRaw('LOWER(slug) = ?', [$organizationSlug]);
                }
            }

            $idType = strtolower((string) ($idColumnType ?? ''));
            $idIsNumeric = preg_match('/(tinyint|smallint|mediumint|bigint|integer|int|serial)/', $idType) === 1;
            $idIsStringLike = $idType === ''
                || str_contains($idType, 'char')
                || in_array($idType, ['string', 'text', 'uuid'], true);

            if ($idIsNumeric) {
                if (ctype_digit($organizationRaw)) {
                    $tenantQuery->orWhere('id', (int) $organizationRaw);
                }
            } else {
                // Compare with id as text for string-like/unknown id columns (e.g. varchar, uuid).
                if ($idIsStringLike) {
                    $tenantQuery->orWhereRaw('LOWER(CAST(id AS TEXT)) = ?', [$organizationRaw]);
                    if ($organizationSlug !== '' && $organizationSlug !== $organizationRaw) {
                        $tenantQuery->orWhereRaw('LOWER(CAST(id AS TEXT)) = ?', [$organizationSlug]);
                    }
                }
            }

            $tenant = $tenantQuery->first();

            if (!$tenant) {
                throw ValidationException::withMessages([
                    'organization' => ['Organization not found.'],
                ]);
            }

            if (!$tenant->active) {
                return response()->json([
                    'message' => 'This organization is inactive.',
                ], 403);
            }

            try {
                tenancy()->initialize($tenant);
            } catch (Throwable $e) {
                report($e);
                $message = strtolower($e->getMessage());
                $looksLikeMissingTenantDatabase = (
                    (str_contains($message, 'schema') && str_contains($message, 'does not exist'))
                    || str_contains($message, 'unknown database')
                    || (str_contains($message, 'database') && str_contains($message, 'does not exist'))
                );

                return response()->json([
                    'message' => $looksLikeMissingTenantDatabase
                        ? 'Tenant database is not initialized on the server.'
                        : 'Tenant initialization failed: ' . $e->getMessage(),
                    'error' => $looksLikeMissingTenantDatabase ? 'tenant_database_missing' : 'tenant_initialization_failed',
                ], $looksLikeMissingTenantDatabase ? 409 : 500);
            }

            try {
                // Find user in the tenant database
                $user = User::query()->where('email', $validated['email'])->first();

                if (!$user || !Hash::check($validated['password'], $user->password)) {
                    throw ValidationException::withMessages([
                        'email' => ['Incorrect credentials.'],
                    ]);
                }

                if (!$user->active) {
                    return response()->json([
                        'message' => 'User inactive.',
                    ], 403);
                }

                $exchangeToken = Str::random(96);
                $exchangeTokenHash = hash('sha256', $exchangeToken);

                // Insert into central token table
                DB::connection($centralConnection)->table('login_exchange_tokens')->insert([
                    'token_hash' => $exchangeTokenHash,
                    'tenant_id' => (string) $tenant->id,
                    'user_id' => (int) $user->id,
                    'expires_at' => now()->addMinutes(3),
                    'used_at' => null,
                    'ip_address' => (string) $request->ip(),
                    'user_agent' => substr((string) ($request->userAgent() ?? ''), 0, 255),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $currentHost = $request->getHost();
                $domain = null;

                if (Schema::connection($centralConnection)->hasTable('domains')) {
                    $domainTenantIdType = null;
                    try {
                        $domainTenantIdType = strtolower((string) Schema::connection($centralConnection)->getColumnType('domains', 'tenant_id'));
                    } catch (Throwable $e) {
                        report($e);
                    }

                    $domainQuery = Domain::on($centralConnection)->orderBy('id');
                    $tenantIdValue = $tenant->id;

                    if (in_array($domainTenantIdType, ['integer', 'bigint', 'smallint', 'tinyint'], true)) {
                        if (is_numeric($tenantIdValue)) {
                            $domainQuery->where('tenant_id', (int) $tenantIdValue);
                        } else {
                            $domainQuery = null;
                        }
                    } else {
                        $domainQuery->where('tenant_id', (string) $tenantIdValue);
                    }

                    if ($domainQuery) {
                        $domain = $domainQuery->value('domain');
                    }
                }

                // FORCE Single Domain mode for DigitalOcean App Platform default domains
                // to prevent NXDOMAIN errors with subdomains.
                if (str_contains($currentHost, 'ondigitalocean.app')) {
                    $tenantHost = $currentHost;
                } else {
                    $tenantHost = $domain ?: $currentHost;

                    // Fallback for local development
                    if (!$domain && ($currentHost === 'localhost' || $currentHost === '127.0.0.1')) {
                        $tenantHost = $tenant->id . '.localhost';
                    }
                }

                return response()->json([
                    'message' => 'Central login successful',
                    'exchange_token' => $exchangeToken,
                    'tenant_id' => (string) $tenant->id,
                    'tenant_host' => $tenantHost,
                    'user' => $user,
                ]);
            } finally {
                tenancy()->end();
            }
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);
            return response()->json([
                'message' => 'Server Configuration Error: ' . $e->getMessage(),
                'error' => 'server_error',
            ], 500);
        }
    }
}

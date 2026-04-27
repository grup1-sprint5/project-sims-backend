<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class CentralAuthController extends Controller
{
    /**
     * Authenticate from central app and return tenant token.
     *
     * Organization is optional. If omitted, credentials are matched across active tenants.
     */
    public function login(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'organization' => ['nullable', 'string', 'max:255'],
                'email' => ['required', 'email'],
                'password' => ['required', 'string'],
            ]);

            $organization = strtolower(trim((string) ($validated['organization'] ?? '')));
            [$tenant, $selectionError] = $this->resolveTenantFromRequest(
                $organization,
                $validated['email'],
                $validated['password']
            );

            if ($selectionError) {
                return $selectionError;
            }
            if (!$tenant) {
                throw ValidationException::withMessages([
                    'email' => ['Incorrect credentials.'],
                ]);
            }

            $initialized = false;
            try {
                tenancy()->initialize($tenant);
                $initialized = true;

                $tenantUser = User::query()->where('email', $validated['email'])->first();
                if (!$tenantUser || !Hash::check($validated['password'], $tenantUser->password)) {
                    throw ValidationException::withMessages([
                        'email' => ['Incorrect credentials.'],
                    ]);
                }

                if (!$tenantUser->active) {
                    return response()->json([
                        'message' => 'User inactive.',
                    ], 403);
                }

                $token = $tenantUser->createToken('api-token', ["tenant:{$tenant->id}"])->plainTextToken;

                return response()->json([
                    'message' => 'Login successful',
                    'token' => $token,
                    'tenant_id' => $this->tenantIdentifier($tenant),
                    'user' => $tenantUser,
                ]);
            } finally {
                if ($initialized) {
                    tenancy()->end();
                }
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

    /**
     * @return array{0: ?Tenant, 1: JsonResponse|null}
     */
    private function resolveTenantFromRequest(string $organization, string $email, string $password): array
    {
        if ($organization !== '') {
            $tenant = $this->findTenantByOrganization($organization);

            if (!$tenant) {
                return [
                    null,
                    response()->json([
                        'message' => 'Organization not found.',
                    ], 422),
                ];
            }

            if (!$tenant->active) {
                return [
                    null,
                    response()->json([
                        'message' => 'This organization is inactive.',
                    ], 403),
                ];
            }

            return [$tenant, null];
        }

        $matches = [];
        $tenants = Tenant::query()->where('active', true)->get();

        foreach ($tenants as $tenant) {
            if (!$tenant instanceof Tenant) {
                continue;
            }

            if ($this->tenantHasValidCredentials($tenant, $email, $password)) {
                $matches[] = $tenant;
            }
        }

        if (count($matches) === 0) {
            return [null, null];
        }

        if (count($matches) > 1) {
            return [
                null,
                response()->json([
                    'message' => 'Multiple organizations match this account. Provide organization to continue.',
                    'error' => 'organization_required',
                    'organizations' => array_values(array_map(fn (Tenant $t) => $this->tenantIdentifier($t), $matches)),
                ], 409),
            ];
        }

        return [$matches[0], null];
    }

    private function findTenantByOrganization(string $organization): ?Tenant
    {
        $slug = Str::slug($organization);
        $compact = str_replace('-', '', $slug !== '' ? $slug : $organization);
        $connection = (string) (config('tenancy.database.central_connection')
            ?? config('database.default')
            ?? 'pgsql');

        $hasSlugColumn = false;

        try {
            $hasSlugColumn = Schema::connection($connection)->hasColumn('tenants', 'slug');
        } catch (Throwable $e) {
            report($e);
        }

        return Tenant::query()
            ->where(function ($query) use ($organization, $slug, $compact, $hasSlugColumn) {
                $query->whereRaw('LOWER(name) = ?', [$organization]);

                if ($hasSlugColumn) {
                    $query->orWhereRaw('LOWER(slug) = ?', [$organization]);
                    $query->orWhereRaw("REPLACE(LOWER(slug), '-', '') = ?", [$compact]);
                    if ($slug !== '' && $slug !== $organization) {
                        $query->orWhereRaw('LOWER(slug) = ?', [$slug]);
                    }

                    if (ctype_digit($organization)) {
                        $query->orWhere('id', (int) $organization);
                    }
                } else {
                    $query->orWhereRaw('LOWER(id) = ?', [$organization]);
                    $query->orWhereRaw("REPLACE(LOWER(id), '-', '') = ?", [$compact]);
                    if ($slug !== '' && $slug !== $organization) {
                        $query->orWhereRaw('LOWER(id) = ?', [$slug]);
                    }
                }
            })
            ->first();
    }

    private function tenantHasValidCredentials(Tenant $tenant, string $email, string $password): bool
    {
        return (bool) $this->authenticateWithinTenant($tenant, $email, $password);
    }

    private function authenticateWithinTenant(Tenant $tenant, string $email, string $password): ?User
    {
        $initialized = false;
        try {
            tenancy()->initialize($tenant);
            $initialized = true;
            $user = User::query()->where('email', $email)->first();
            if (!$user || !Hash::check($password, $user->password)) {
                return null;
            }

            return $user;
        } catch (Throwable $e) {
            report($e);
            return null;
        } finally {
            if ($initialized) {
                tenancy()->end();
            }
        }
    }

    private function tenantIdentifier(Tenant $tenant): string
    {
        return (string) ($tenant->getTenantKey() ?: $tenant->id);
    }
}

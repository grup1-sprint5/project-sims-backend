<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AuthExchangeController extends Controller
{
    /**
     * Exchange a short-lived one-time token for a tenant access token.
     */
    public function exchange(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'exchange_token' => ['required', 'string', 'min:32'],
        ]);

        $tenantId = tenant('id');
        if (!$tenantId) {
            return response()->json(['message' => 'Tenant context required.'], 400);
        }

        $tokenHash = hash('sha256', $validated['exchange_token']);

        $row = DB::connection('pgsql')->transaction(function () use ($tokenHash, $tenantId) {
            $candidate = DB::connection('pgsql')
                ->table('login_exchange_tokens')
                ->where('token_hash', $tokenHash)
                ->where('tenant_id', $tenantId)
                ->whereNull('used_at')
                ->where('expires_at', '>', now())
                ->lockForUpdate()
                ->first();

            if (!$candidate) {
                return null;
            }

            DB::connection('pgsql')
                ->table('login_exchange_tokens')
                ->where('id', $candidate->id)
                ->update([
                    'used_at' => now(),
                    'updated_at' => now(),
                ]);

            return $candidate;
        });

        if (!$row) {
            throw ValidationException::withMessages([
                'exchange_token' => ['Invalid or expired exchange token.'],
            ]);
        }

        $user = User::query()->find($row->user_id);

        if (!$user || !$user->active) {
            return response()->json(['message' => 'User inactive or missing.'], 403);
        }

        $token = $user->createToken('api-token', ["tenant:{$tenantId}"])->plainTextToken;

        return response()->json([
            'message' => 'Exchange successful',
            'token' => $token,
            'tenant_id' => (string) $tenantId,
            'user' => $user,
        ]);
    }
}

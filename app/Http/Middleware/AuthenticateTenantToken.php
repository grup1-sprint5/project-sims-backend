<?php

namespace App\Http\Middleware;

use App\Models\PersonalAccessToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateTenantToken
{
    /**
     * Authenticate tenant API requests using Bearer token explicitly.
     * This avoids guard resolution edge cases when tenancy changes DB context.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $plainTextToken = $request->bearerToken();

        if (!$plainTextToken) {
            return $this->unauthenticated();
        }

        $accessToken = PersonalAccessToken::findToken($plainTextToken);

        if (!$accessToken) {
            return $this->unauthenticated();
        }

        $tokenableType = $accessToken->tokenable_type;
        $tokenableId = $accessToken->tokenable_id;

        if (!is_string($tokenableType) || !class_exists($tokenableType)) {
            return $this->unauthenticated();
        }

        $tenantId = function_exists('tenant') && tenant() ? (string) tenant('id') : null;
        $abilities = is_array($accessToken->abilities) ? $accessToken->abilities : [];

        if ($tenantId && !in_array("tenant:{$tenantId}", $abilities, true)) {
            return $this->unauthenticated();
        }

        // Resolve the authenticated user from the tenant DB context.
        // Do not use $accessToken->tokenable because the token model lives on central DB.
        $user = $tokenableType::query()->find($tokenableId);

        if (!$user) {
            return $this->unauthenticated();
        }

        // Attach current token to user so logout can revoke currentAccessToken().
        if (method_exists($user, 'withAccessToken')) {
            $user->withAccessToken($accessToken);
        }

        auth()->setUser($user);
        $request->setUserResolver(fn () => $user);

        // Track usage like Sanctum does.
        $accessToken->forceFill(['last_used_at' => now()])->save();

        return $next($request);
    }

    private function unauthenticated(): Response
    {
        return response()->json([
            'message' => 'Unauthenticated.',
        ], 401);
    }
}

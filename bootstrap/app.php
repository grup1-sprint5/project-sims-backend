<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Stancl\Tenancy\Contracts\TenantCouldNotBeIdentifiedException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // Load tenant routes first (with tenancy middleware)
            if (file_exists(base_path('routes/tenant.php'))) {
                \Illuminate\Support\Facades\Route::group([], base_path('routes/tenant.php'));
            }
            
            // Load central API routes AFTER with /api prefix so they override tenant routes
            if (file_exists(base_path('routes/api.php'))) {
                \Illuminate\Support\Facades\Route::prefix('api')->group(base_path('routes/api.php'));
            }
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'auth.tenant-token' => \App\Http\Middleware\AuthenticateTenantToken::class,
            'tenancy.optional' => \App\Http\Middleware\InitializeTenancyOptional::class,
        ]);

        // Add tenant logging context to all requests
        $middleware->append(\App\Http\Middleware\SetTenantLogContext::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (TenantCouldNotBeIdentifiedException $exception, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => 'Tenant not identified. Provide a valid X-Tenant header.',
                    'error' => 'tenant_not_identified',
                ], 422);
            }
        });
    })->create();

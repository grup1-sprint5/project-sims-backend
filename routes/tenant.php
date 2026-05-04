<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use App\Http\Middleware\InitializeTenancyByDomainOrHeader;
use App\Http\Middleware\CheckTenantActive;

use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AuthExchangeController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\VehicleController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\TicketMessageController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\AdminReservationController;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\Api\GeofenceController;
use App\Http\Controllers\Api\GeofenceAssignmentController;
use App\Http\Controllers\Api\GeofenceEventController;
use App\Http\Controllers\Api\VehiclePositionController;
use App\Http\Controllers\TenantController;
use App\Http\Controllers\TenantDomainController;

/*
|--------------------------------------------------------------------------
| Tenant Routes
|--------------------------------------------------------------------------
|
| Tenancy is initialized via two strategies (in order):
|   1. Request Host matched in the `domains` table (domain/subdomain routing)
|   2. X-Tenant: {slug} request header (API header routing)
|
*/

Route::middleware(['api', InitializeTenancyByDomainOrHeader::class, CheckTenantActive::class])
    ->prefix('api')
    ->group(function () {

        // Keep tenant login on a dedicated path to avoid collision with central /api/login.
        Route::post('/tenant/login', [AuthController::class, 'login'])->name('tenant.login');
        Route::post('/auth/exchange-token', [AuthExchangeController::class, 'exchange'])->name('tenant.exchange');
        Route::post('/users', [UserController::class, 'store']); // Public registration

        Route::middleware('auth.tenant-token')->group(function () {

            Route::post('/logout', [AuthController::class, 'logout']);

            // Note: /user endpoint is defined in routes/api.php to work for both tenant and central users
            
            // Users management (Authenticated only)
            Route::post('/users/{user}/restore', [UserController::class, 'restore']);
            Route::apiResource('users', UserController::class)->except(['store']);

            // Roles & Permissions
            Route::apiResource('roles', RoleController::class);
            Route::get('/permissions', [PermissionController::class, 'index']);

            // Vehicles
            Route::apiResource('vehicles', VehicleController::class);
            Route::get('vehicles-map', [VehicleController::class, 'map']);
            Route::get('vehicles-map-admin', [VehicleController::class, 'adminMap']);

            // Geofencing
            Route::apiResource('geofences', GeofenceController::class);
            Route::post('geofences/{geofence}/assignments', [GeofenceAssignmentController::class, 'store']);
            Route::delete('geofences/{geofence}/assignments/{assignmentId}', [GeofenceAssignmentController::class, 'destroy']);
            Route::get('geofence-events', [GeofenceEventController::class, 'index']);
            Route::post('vehicle-positions', [VehiclePositionController::class, 'store'])->middleware('throttle:120,1');

            // Tickets
            Route::apiResource('tickets', TicketController::class);
            Route::post('tickets/{ticket}/messages', [TicketMessageController::class, 'store']);
            Route::delete('messages/{message}', [TicketMessageController::class, 'destroy']);

            // AI Chat assistant
            Route::post('chat', [ChatController::class, 'send']);

            // Tenants (admins manage their own tenant; SuperAdmin manages all)
            Route::apiResource('tenants', TenantController::class);
            Route::patch('tenants/{tenant}/toggle-active', [TenantController::class, 'toggleActive']);

            // Tenant domains (SuperAdmin assigns custom domains to tenants)
            Route::get('tenants/{tenant}/domains', [TenantDomainController::class, 'index']);
            Route::post('tenants/{tenant}/domains', [TenantDomainController::class, 'store']);
            Route::delete('tenants/{tenant}/domains/{domain}', [TenantDomainController::class, 'destroy']);

            // Reservations – user operations
            Route::get('reservations', [ReservationController::class, 'index']);
            Route::post('reservations', [ReservationController::class, 'store']);
            Route::post('reservations/calculate-price', [ReservationController::class, 'calculatePrice']);
            Route::get('reservations/{reservation}', [ReservationController::class, 'show']);
            Route::post('reservations/{reservation}/activate', [ReservationController::class, 'activate']);
            Route::post('reservations/{reservation}/finish', [ReservationController::class, 'finish']);
            Route::post('reservations/{reservation}/cancel', [ReservationController::class, 'cancel']);
            Route::post('reservations/{reservation}/force-finish', [ReservationController::class, 'forceFinish']);
            Route::post('reservations/{reservation}/checkout-session', [ReservationController::class, 'createStripeCheckoutSession']);

            // Wallet (Stripe top-up + balance)
            Route::get('wallet/balance', [WalletController::class, 'balance']);
            Route::post('wallet/checkout-session', [WalletController::class, 'createStripeCheckoutSession']);
            Route::post('wallet/confirm-session', [WalletController::class, 'confirmStripeCheckoutSession']);

            // Reservations – admin operations
            Route::prefix('admin')->name('admin.')->group(function () {
                Route::get('reservations', [AdminReservationController::class, 'index'])->name('reservations.index');
                Route::get('reservations/revenue-summary', [AdminReservationController::class, 'revenueSummary'])->name('reservations.revenue-summary');
                Route::get('reservations/{id}', [AdminReservationController::class, 'show'])->name('reservations.show');
                Route::put('reservations/{id}', [AdminReservationController::class, 'update'])->name('reservations.update');
                Route::delete('reservations/{id}', [AdminReservationController::class, 'destroy'])->name('reservations.destroy');
                Route::post('reservations/{id}/force-finish', [AdminReservationController::class, 'forceFinish'])->name('reservations.forceFinish');
            });
        });
    });


<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CentralAuthController;
use App\Http\Controllers\StripeWebhookController;

/*
|--------------------------------------------------------------------------
| Central API Routes (no tenant context required)
|--------------------------------------------------------------------------
|
| All business routes have moved to routes/tenant.php and require the
| X-Tenant: {slug} header to initialize tenancy.
|
| Keep here only truly central routes that do not belong to any specific
| tenant schema (e.g. health checks, central admin tools).
|
*/

Route::get('/health', fn () => response()->json(['status' => 'ok']));
Route::post('/central/login', [CentralAuthController::class, 'login']);
Route::post('/login', [CentralAuthController::class, 'loginCentralAdmin']); // Superadmin login without tenant
Route::post('/payments/stripe/webhook', [StripeWebhookController::class, 'handle']);

// Public tenant registration request (no tenant context)
use App\Http\Controllers\TenantRequestController;

Route::post('/register-company', [TenantRequestController::class, 'store']);

// Slug availability check
Route::get('/tenant-slugs/check', [TenantRequestController::class, 'checkSlug']);

// Central user endpoint (accepts both central superadmin and tenant users with valid tokens)
use App\Http\Controllers\Api\AuthController;
Route::middleware(['auth:sanctum'])->get('/user', [AuthController::class, 'user']);

// Central admin data endpoints (for superadmin dashboard - NO tenancy middleware)
use App\Http\Controllers\Api\CentralAdminController;
use App\Http\Controllers\Api\AdminSystemController;
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/users', [CentralAdminController::class, 'users']);
    Route::get('/roles', [CentralAdminController::class, 'roles']);
    Route::get('/permissions', [CentralAdminController::class, 'permissions']);
    
    // System-wide admin routes (all data from all tenants)
    Route::get('/vehicles', [AdminSystemController::class, 'vehicles']);
    Route::get('/reservations', [AdminSystemController::class, 'reservations']);
    Route::get('/bookings', [AdminSystemController::class, 'reservations']); // alias for reservations
    Route::get('/geofences', [AdminSystemController::class, 'geofences']);
    Route::get('/geofence-events', [AdminSystemController::class, 'geofenceEvents']);
    Route::get('/tenants', [AdminSystemController::class, 'tenants']);
    Route::get('/tickets', [AdminSystemController::class, 'tickets']);
});

// Admin endpoints for tenant requests (require auth + superadmin)
Route::middleware(['auth:sanctum'])->group(function () {
	Route::get('/tenant-requests', [TenantRequestController::class, 'index']);
	Route::post('/tenant-requests/{id}/approve', [TenantRequestController::class, 'approve']);
});

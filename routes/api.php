<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CentralAuthController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Middleware\CheckTenantActive;
use App\Http\Middleware\InitializeTenancyByDomainOrHeader;

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
Route::middleware([
    InitializeTenancyByDomainOrHeader::class,
    CheckTenantActive::class,
    'auth.tenant-token',
])->get('/user', [AuthController::class, 'user']);

// Admin endpoints for tenant requests (require auth + superadmin)
Route::middleware(['auth:sanctum'])->group(function () {
	Route::get('/tenant-requests', [TenantRequestController::class, 'index']);
	Route::post('/tenant-requests/{id}/approve', [TenantRequestController::class, 'approve']);
	Route::post('/tenant-requests/{id}/reject', [TenantRequestController::class, 'reject']);
});

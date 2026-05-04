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
Route::post('/payments/stripe/webhook', [StripeWebhookController::class, 'handle']);

// Public tenant registration request (no tenant context)
use App\Http\Controllers\TenantRequestController;

Route::post('/register-company', [TenantRequestController::class, 'store']);

// Admin endpoints for tenant requests (require auth + superadmin)
Route::middleware(['auth:sanctum'])->group(function () {
	Route::get('/tenant-requests', [TenantRequestController::class, 'index']);
	Route::post('/tenant-requests/{id}/approve', [TenantRequestController::class, 'approve']);
});

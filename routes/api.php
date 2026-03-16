<?php

use Illuminate\Support\Facades\Route;

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

<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PermissionController;

use App\Http\Controllers\VehicleController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\TicketMessageController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\AdminReservationController;


Route::post('/login', [AuthController::class, 'login'])->name('login');

Route::middleware('auth:sanctum')->group(function () {
    
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    
    // Users endpoints
    Route::post('/users', [UserController::class, 'store']);
    Route::apiResource('users', UserController::class)->except(['store']);
    
    // Roles endpoints
    Route::apiResource('roles', RoleController::class);
    Route::get('/permissions', [PermissionController::class, 'index']);
    
    // Vehicles endpoints
    Route::apiResource('vehicles', VehicleController::class);
    Route::get('vehicles-map', [VehicleController::class, 'map']);
    Route::get('vehicles-map-admin', [VehicleController::class, 'adminMap']);

    // Tickets endpoints
    Route::apiResource('tickets', TicketController::class);
    Route::post('tickets/{ticket}/messages', [TicketMessageController::class, 'store']);
    Route::delete('messages/{message}', [TicketMessageController::class, 'destroy']);

    // Reservations endpoints (User operations)
    Route::get('reservations', [ReservationController::class, 'index']);
    Route::post('reservations', [ReservationController::class, 'store']);
    Route::get('reservations/{reservation}', [ReservationController::class, 'show']);
    Route::post('reservations/{reservation}/activate', [ReservationController::class, 'activate']);
    Route::post('reservations/{reservation}/finish', [ReservationController::class, 'finish']);
    Route::post('reservations/{reservation}/cancel', [ReservationController::class, 'cancel']);
    Route::post('reservations/{reservation}/force-finish', [ReservationController::class, 'forceFinish']);
    
    // Admin Reservations endpoints (Admin only operations)
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('reservations', [AdminReservationController::class, 'index'])->name('reservations.index');
        Route::get('reservations/{id}', [AdminReservationController::class, 'show'])->name('reservations.show');
        Route::put('reservations/{id}', [AdminReservationController::class, 'update'])->name('reservations.update');
        Route::delete('reservations/{id}', [AdminReservationController::class, 'destroy'])->name('reservations.destroy');
        Route::post('reservations/{id}/force-finish', [AdminReservationController::class, 'forceFinish'])->name('reservations.forceFinish');
    });
});

<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\LineController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ScheduleActualController;
use App\Http\Controllers\ScheduleController;
use Illuminate\Support\Facades\Route;

// Guests only
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
});

// Everything else requires a logged-in user
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/', fn () => redirect()->route('schedules.index'));

    // Pages
    Route::get('/schedules', [ScheduleController::class, 'index'])->name('schedules.index');
    Route::get('/lines', [LineController::class, 'index'])->name('lines.index');
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');

    // JSON endpoints (session + CSRF, called from public/js). Guests get 401 JSON.
    Route::prefix('api')->group(function () {
        Route::get('lines', [LineController::class, 'list']);
        Route::get('orders', [OrderController::class, 'list']);
        Route::get('schedules', [ScheduleController::class, 'list']);

        Route::middleware('throttle:120,1')->group(function () {
            Route::post('lines', [LineController::class, 'store']);
            Route::put('lines/{line}', [LineController::class, 'update']);
            Route::delete('lines/{line}', [LineController::class, 'destroy']);

            Route::post('orders', [OrderController::class, 'store']);
            Route::put('orders/{order}', [OrderController::class, 'update']);
            Route::delete('orders/{order}', [OrderController::class, 'destroy']);

            Route::post('schedules', [ScheduleController::class, 'store']);
            Route::put('schedules/{schedule}', [ScheduleController::class, 'update']);
            Route::delete('schedules/{schedule}', [ScheduleController::class, 'destroy']);

            Route::put('schedules/{schedule}/days/{date}/actual', [ScheduleActualController::class, 'update'])
                ->where('date', '\d{4}-\d{2}-\d{2}');
        });
    });
});

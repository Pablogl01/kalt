<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DietController;
use App\Http\Controllers\MealLogController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ShoppingController;
use App\Http\Controllers\StatsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// CSRF cookie (required for Sanctum SPA)
Route::get('/sanctum/csrf-cookie', function () {
    return response()->noContent();
});

// Public routes
Route::middleware('throttle:login')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
});

// Protected routes — require Sanctum auth cookie
Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Profile
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);

    // Diet plans — generation throttled separately
    Route::middleware('throttle:plan-generation')->group(function () {
        Route::post('/plans/generate', [DietController::class, 'generate']);
    });
    Route::get('/plans/active', [DietController::class, 'active']);
    Route::get('/plans/{weeklyPlan}', [DietController::class, 'show']);
    Route::get('/plans/{weeklyPlan}/status', [DietController::class, 'status']);

    // Meal logs
    Route::apiResource('meal-logs', MealLogController::class);

    // Shopping
    Route::get('/shopping-lists/{shoppingList}', [ShoppingController::class, 'show']);
    Route::patch('/shopping-items/{shoppingItem}', [ShoppingController::class, 'update']);

    // Stats
    Route::get('/stats', [StatsController::class, 'index']);
    Route::post('/weight-logs', [StatsController::class, 'storeWeight']);
});

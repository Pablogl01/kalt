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

    // Foods
    Route::get('/foods', function () {
        return \App\Models\Food::orderBy('nombre')->get();
    });


    // Onboarding
    Route::post('/onboarding/step1', [\App\Http\Controllers\OnboardingController::class, 'step1']);
    Route::post('/onboarding/step2', [\App\Http\Controllers\OnboardingController::class, 'step2']);
    Route::post('/onboarding/step3', [\App\Http\Controllers\OnboardingController::class, 'step3']);

    // Daily Logs
    Route::get('/daily-logs/{fecha}', [\App\Http\Controllers\DailyLogController::class, 'show']);
    Route::patch('/daily-logs/{dailyLog}/training', [\App\Http\Controllers\DailyLogController::class, 'updateTraining']);

    // Diet plans — generation throttled separately
    Route::middleware('throttle:plan-generation')->group(function () {
        Route::post('/plans/generate', [DietController::class, 'generate']);
    });
    Route::get('/plans/active', [DietController::class, 'active']);
    Route::get('/plans/{weeklyPlan}', [DietController::class, 'show']);
    Route::get('/plans/{weeklyPlan}/status', [DietController::class, 'status']);

    // Meal logs
    Route::patch('/meal-logs/{mealLog}/complete', [MealLogController::class, 'complete']);
    Route::patch('/meal-logs/{mealLog}/skip', [MealLogController::class, 'skip']);
    Route::post('/meal-logs/{dailyLog}/extra', [MealLogController::class, 'extra']);
    Route::apiResource('meal-logs', MealLogController::class);

    // Substitutes
    Route::get('/meal-items/{mealItem}/substitutes', [\App\Http\Controllers\SubstituteController::class, 'substitutes']);
    Route::patch('/meal-items/{mealItem}/substitute', [\App\Http\Controllers\SubstituteController::class, 'substitute']);

    // Shopping
    Route::get('/shopping-lists/active', [ShoppingController::class, 'active']);
    Route::post('/shopping-lists/generate', [ShoppingController::class, 'generate']);
    Route::get('/shopping-lists/{shoppingList}', [ShoppingController::class, 'show']);
    Route::patch('/shopping-items/{shoppingItem}/have', [ShoppingController::class, 'have']);
    Route::patch('/shopping-items/{shoppingItem}/dont-want', [ShoppingController::class, 'dontWant']);
    Route::patch('/shopping-items/{shoppingItem}/substitute', [ShoppingController::class, 'substitute']);
    Route::patch('/shopping-items/{shoppingItem}', [ShoppingController::class, 'update']);

    // Stats & Weight Logs
    Route::get('/stats/macros', [StatsController::class, 'macros']);
    Route::get('/stats/adherence', [StatsController::class, 'adherence']);
    Route::get('/stats/training', [StatsController::class, 'training']);
    Route::get('/stats/weight', [StatsController::class, 'weight']);
    Route::get('/weight-logs', [StatsController::class, 'indexWeight']);
    Route::post('/weight-logs', [StatsController::class, 'storeWeight']);
});


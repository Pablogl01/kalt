<?php

use Illuminate\Support\Facades\Route;

// SPA catch-all — all HTML requests go to Vue
Route::get('/{any}', function () {
    return response()->json(['message' => 'KALT API. Use the Vue frontend at http://localhost:5173']);
})->where('any', '.*');

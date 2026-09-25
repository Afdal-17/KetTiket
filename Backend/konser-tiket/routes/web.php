<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

Route::get('/auth/google/redirect', [AuthController::class, 'googleRedirect']);
Route::get('/auth/google/callback', [AuthController::class, 'googleCallback']);
Route::get('/auth/tiktok/redirect', [AuthController::class, 'tiktokRedirect']);
Route::get('/auth/tiktok/callback', [AuthController::class, 'tiktokCallback']);

// The SPA Catch-All Route
// Semua permintaan URL akan diarahkan ke welcome.blade.php
// Routing spesifik akan ditangani oleh DOM JavaScript di app.js
Route::get('/{any}', function () {
    return view('welcome');
})->where('any', '.*');

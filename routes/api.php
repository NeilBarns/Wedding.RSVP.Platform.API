<?php

use App\Http\Controllers\AuthenticationController;
use App\Http\Controllers\WeddingController;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'service' => 'Wedding RSVP Platform API',
    ]);
});

Route::get('/wedding', [WeddingController::class, 'show']);

Route::post('/auth/login', [AuthenticationController::class, 'login'])
    ->middleware('throttle:login');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthenticationController::class, 'logout']);
    Route::get('/auth/me', [AuthenticationController::class, 'me']);

    Route::get('/admin/ping', fn () => response()->json(['status' => 'ok']));
});

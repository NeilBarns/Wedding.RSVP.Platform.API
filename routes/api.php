<?php

use App\Http\Controllers\WeddingController;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'service' => 'Wedding RSVP Platform API',
    ]);
});

Route::get('/wedding', [WeddingController::class, 'show']);

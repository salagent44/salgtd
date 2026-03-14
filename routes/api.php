<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ItemController;
use App\Http\Controllers\Api\SyncController;
use Illuminate\Support\Facades\Route;

// Auth
Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // Sync
    Route::get('/sync/full', [SyncController::class, 'full']);
    Route::post('/sync/pull', [SyncController::class, 'pull']);
    Route::post('/sync/push', [SyncController::class, 'push']);

    // Item actions
    Route::post('/items/{item}/process', [ItemController::class, 'process']);
    Route::post('/items/{item}/move-to-inbox', [ItemController::class, 'moveToInbox']);
});

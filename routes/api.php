<?php

use App\Http\Controllers\Api\V1\BoxController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('auth:sanctum')->group(function (): void {
    Route::post('/boxes', [BoxController::class, 'store']);
    Route::get('/boxes/{box}', [BoxController::class, 'show']);
    Route::post('/boxes/{box}/scans', [BoxController::class, 'scan']);
    Route::post('/boxes/{box}/release', [BoxController::class, 'release']);
    Route::get('/boxes/{box}/history', [BoxController::class, 'history']);
});

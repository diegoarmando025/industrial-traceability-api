<?php

use App\Http\Controllers\Api\V1\BoxController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('auth:sanctum')->group(function (): void {
    Route::post('/boxes', [BoxController::class, 'store'])
        ->middleware('abilities:boxes:create');

    Route::get('/boxes/{box}', [BoxController::class, 'show'])
        ->middleware('abilities:boxes:read');

    Route::post('/boxes/{box}/scans', [BoxController::class, 'scan'])
        ->middleware('abilities:boxes:scan');

    Route::post('/boxes/{box}/release', [BoxController::class, 'release'])
        ->middleware('abilities:boxes:release');

    Route::get('/boxes/{box}/history', [BoxController::class, 'history'])
        ->middleware('abilities:boxes:read');
});

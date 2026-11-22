<?php

use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\BoxController;
use App\Http\Controllers\Web\SessionController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Web\PublicDemoController;
use App\Http\Controllers\Web\PlantValidationRulesController;
use App\Http\Controllers\Web\PlantCustomerProfileController;

Route::redirect('/', '/dashboard');

Route::middleware('throttle:60,1')->group(function (): void {
    Route::get('/demo', [PublicDemoController::class, 'index'])->name('traceability.demo.index');
    Route::get('/demo/boxes/{box}', [PublicDemoController::class, 'show'])->name('traceability.demo.show');
});

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [SessionController::class, 'create'])->name('login');
    Route::post('/login', [SessionController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/plants/validation', [PlantValidationRulesController::class, 'index'])->name('plants.validation.index');
    Route::get('/plants/{plant}/validation', [PlantValidationRulesController::class, 'edit'])->name('plants.validation.edit');
    Route::put('/plants/{plant}/validation', [PlantValidationRulesController::class, 'update'])->name('plants.validation.update');
    Route::get('/plants/{plant}/customers', [PlantCustomerProfileController::class, 'index'])->name('plants.customers.index');
    Route::post('/plants/{plant}/customers', [PlantCustomerProfileController::class, 'store'])->name('plants.customers.store');
    Route::post('/plants/{plant}/customers/{profile}/deactivate', [PlantCustomerProfileController::class, 'deactivate'])->name('plants.customers.deactivate');
    Route::post('/logout', [SessionController::class, 'destroy'])->name('logout');
    Route::get('/boxes', [BoxController::class, 'index'])->name('boxes.index');
    Route::get('/boxes/create', [BoxController::class, 'create'])->name('boxes.create');
    Route::post('/boxes', [BoxController::class, 'store'])->name('boxes.store');
    Route::get('/boxes/{box}', [BoxController::class, 'show'])->name('boxes.show');
    Route::post('/boxes/{box}/scans', [BoxController::class, 'scan'])->name('boxes.scans.store');
    Route::post('/boxes/{box}/release', [BoxController::class, 'release'])->name('boxes.release');
});

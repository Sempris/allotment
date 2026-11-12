<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\MeterController;
use App\Http\Controllers\PlotController;
use App\Http\Controllers\ReadingController;
use App\Http\Controllers\TariffController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:5,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/', DashboardController::class)->name('dashboard');

    Route::resource('plots', PlotController::class)->only(['index', 'create', 'store', 'show']);
    Route::get('/plots/{plot}/meters/create', [MeterController::class, 'create'])->name('meters.create');
    Route::post('/plots/{plot}/meters', [MeterController::class, 'store'])->name('meters.store');

    Route::get('/meters/{meter}/readings/create', [ReadingController::class, 'create'])->name('readings.create');
    Route::post('/meters/{meter}/readings', [ReadingController::class, 'store'])->name('readings.store');
    Route::get('/readings/{reading}/correct', [ReadingController::class, 'editCorrection'])->name('readings.correct');
    Route::post('/readings/{reading}/correct', [ReadingController::class, 'storeCorrection'])->name('readings.correct.store');
    Route::post('/readings/{reading}/review', [ReadingController::class, 'review'])->name('readings.review');

    Route::get('/import', [ImportController::class, 'create'])->name('import.create');
    Route::post('/import', [ImportController::class, 'store'])->name('import.store');

    Route::get('/billing', [BillingController::class, 'index'])->name('billing.index');
    Route::get('/billing/csv', [BillingController::class, 'csv'])->name('billing.csv');

    Route::get('/tariffs', [TariffController::class, 'index'])->name('tariffs.index');
    Route::post('/tariffs', [TariffController::class, 'store'])->name('tariffs.store');
});

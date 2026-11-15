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

// Every group below names the ability it needs (see AppServiceProvider::defineAbilities).
Route::middleware(['auth', 'active'])->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    // Start page: overview for board and treasurer, the own plot for tenants.
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::middleware('can:manage-master-data')->group(function () {
        Route::resource('plots', PlotController::class)->only(['create', 'store', 'edit', 'update', 'destroy']);
        Route::get('/plots/{plot}/meters/create', [MeterController::class, 'create'])->name('meters.create');
        Route::post('/plots/{plot}/meters', [MeterController::class, 'store'])->name('meters.store');
        Route::get('/meters/{meter}/edit', [MeterController::class, 'edit'])->name('meters.edit');
        Route::put('/meters/{meter}', [MeterController::class, 'update'])->name('meters.update');
        Route::delete('/meters/{meter}', [MeterController::class, 'destroy'])->name('meters.destroy');
        Route::post('/meters/{meter}/retire', [MeterController::class, 'retire'])->name('meters.retire');
        Route::post('/meters/{meter}/reactivate', [MeterController::class, 'reactivate'])->name('meters.reactivate');
    });

    Route::middleware('can:record-readings')->group(function () {
        Route::get('/meters/{meter}/readings/create', [ReadingController::class, 'create'])->name('readings.create');
        Route::post('/meters/{meter}/readings', [ReadingController::class, 'store'])->name('readings.store');
        Route::get('/readings/{reading}/correct', [ReadingController::class, 'editCorrection'])->name('readings.correct');
        Route::post('/readings/{reading}/correct', [ReadingController::class, 'storeCorrection'])->name('readings.correct.store');
        Route::post('/readings/{reading}/review', [ReadingController::class, 'review'])->name('readings.review');

        Route::get('/import', [ImportController::class, 'create'])->name('import.create');
        Route::post('/import', [ImportController::class, 'store'])->name('import.store');
    });

    Route::middleware('can:view-club')->group(function () {
        Route::get('/plots', [PlotController::class, 'index'])->name('plots.index');
        Route::get('/billing', [BillingController::class, 'index'])->name('billing.index');
        Route::get('/billing/csv', [BillingController::class, 'csv'])->name('billing.csv');
        Route::get('/tariffs', [TariffController::class, 'index'])->name('tariffs.index');
    });

    Route::get('/plots/{plot}', [PlotController::class, 'show'])->name('plots.show')->can('view-plot', 'plot');

    Route::middleware('can:manage-billing')->group(function () {
        Route::post('/tariffs', [TariffController::class, 'store'])->name('tariffs.store');
        Route::delete('/tariffs/{tariff}', [TariffController::class, 'destroy'])->name('tariffs.destroy');
    });
});

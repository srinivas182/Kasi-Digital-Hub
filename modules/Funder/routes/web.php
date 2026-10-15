<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Funder\Http\Controllers\FunderController;

/* Funder portal (S20): programmes and impact - aggregates only. */
Route::prefix('funder')->name('funder.')->middleware(['auth', 'account.ready'])->group(function (): void {
    Route::get('/', [FunderController::class, 'index'])->name('home');
    Route::post('/programmes', [FunderController::class, 'store'])->name('programmes.store');
    Route::get('/programmes/{programme}', [FunderController::class, 'show'])->name('programme');
    Route::post('/programmes/{programme}/targets', [FunderController::class, 'requestTarget'])->name('targets.request');
    Route::get('/programmes/{programme}/report', [FunderController::class, 'report'])->middleware('throttle:10,1')->name('report');
    Route::get('/programmes/{programme}/export.csv', [FunderController::class, 'csv'])->middleware('throttle:10,1')->name('csv');
    Route::post('/target-changes/{change}', [FunderController::class, 'decideTarget'])->whereNumber('change')->name('targets.decide');
    Route::post('/stories', [FunderController::class, 'story'])->name('stories');
});

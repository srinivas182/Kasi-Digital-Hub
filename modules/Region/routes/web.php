<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Region\Http\Controllers\RegionController;

/* Regional console (S20): city and provincial coordinators. */
Route::prefix('region')->name('region.')->middleware(['auth', 'account.ready'])->group(function (): void {
    Route::get('/', [RegionController::class, 'index'])->name('home');
    Route::post('/hubs/{hub}/message', [RegionController::class, 'message'])->middleware('throttle:20,60')->name('message');
});

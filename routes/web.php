<?php

declare(strict_types=1);

use App\Http\Controllers\ConfirmPinController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\VersionController;
use Illuminate\Support\Facades\Route;

/*
| Platform-level routes only. Every portal registers its own routes from
| modules/<Module>/routes via the ModuleServiceProvider.
*/

Route::get('/version', VersionController::class)->name('version');
Route::post('/locale', LocaleController::class)->middleware('throttle:20,1')->name('locale.update');

// S23: staff confirm their PIN again before sensitive actions.
Route::middleware(['auth'])->group(function (): void {
    Route::get('/confirm-pin', [ConfirmPinController::class, 'show'])->name('pin.confirm');
    Route::post('/confirm-pin', [ConfirmPinController::class, 'confirm'])->middleware('throttle:10,1')->name('pin.confirm.store');
});

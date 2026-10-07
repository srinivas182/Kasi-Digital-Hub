<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Hub\Http\Controllers\HomeController;

Route::middleware(['auth', 'account.ready'])->group(function (): void {
    Route::get('/home', HomeController::class)->name('hub.home');
});

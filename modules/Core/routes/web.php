<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\UiKitController;

$uiKit = config('kasi.ui_kit.enabled');
$uiKitEnabled = $uiKit === null ? ! app()->isProduction() : filter_var($uiKit, FILTER_VALIDATE_BOOL);

if ($uiKitEnabled) {
    Route::get('/ui-kit', [UiKitController::class, 'index'])->name('ui-kit');
    Route::get('/ui-kit/layouts/{layout}', [UiKitController::class, 'layout'])->name('ui-kit.layout');
}

// Offline fallback served by the service worker when there is no connection.
Route::view('/offline', 'offline')->name('offline');

<?php

declare(strict_types=1);

use App\Http\Controllers\VersionController;
use Illuminate\Support\Facades\Route;

/*
| Platform-level routes only. Every portal registers its own routes from
| modules/<Module>/routes via the ModuleServiceProvider.
*/

Route::get('/version', VersionController::class)->name('version');

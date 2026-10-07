<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Site\Http\Controllers\HomeController;

Route::get('/', HomeController::class)->name('site.home');

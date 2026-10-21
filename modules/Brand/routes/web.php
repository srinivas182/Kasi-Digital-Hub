<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Brand\Http\Controllers\BrandController;

/* KasiBrand (S30). Owners and co-owners of a KasiStart business at a hub with KasiBrand; assisted sessions work. */
Route::prefix('brand')->name('brand.')->middleware(['auth', 'account.ready'])->group(function (): void {
    Route::get('/', [BrandController::class, 'home'])->name('home');
    Route::get('/kit', [BrandController::class, 'kit'])->name('kit');
    Route::post('/kit', [BrandController::class, 'saveKit'])->middleware('throttle:30,10')->name('kit.save');
    Route::post('/kit/logo', [BrandController::class, 'logo'])->middleware('throttle:10,10')->name('kit.logo');
    Route::post('/kit/taglines', [BrandController::class, 'taglines'])->middleware('throttle:10,60')->name('kit.taglines');
    Route::get('/designs', [BrandController::class, 'designs'])->name('designs');
    Route::post('/designs/{design}/duplicate', [BrandController::class, 'duplicate'])->whereNumber('design')->name('designs.duplicate');
    Route::get('/make/{template}/{design?}', [BrandController::class, 'edit'])->where('template', '[a-z_]+')->whereNumber('design')->name('design');
    Route::post('/make/{template}', [BrandController::class, 'save'])->where('template', '[a-z_]+')->middleware('throttle:60,10')->name('design.save');
    Route::post('/make/{template}/exported', [BrandController::class, 'exported'])->where('template', '[a-z_]+')->middleware('throttle:60,10')->name('design.exported');
    Route::post('/make/{template}/pdf', [BrandController::class, 'pdf'])->where('template', '[a-z_]+')->middleware('throttle:20,10')->name('design.pdf');
    Route::get('/catalogue', [BrandController::class, 'catalogue'])->name('catalogue');
});

<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Brand\Http\Controllers\BrandController;
use Modules\Brand\Http\Controllers\SiteController;

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

    // S31: website and content calendar.
    Route::get('/site', [SiteController::class, 'site'])->name('site');
    Route::get('/site/check', [SiteController::class, 'check'])->middleware('throttle:60,1')->name('site.check');
    Route::post('/site', [SiteController::class, 'save'])->middleware('throttle:30,10')->name('site.save');
    Route::post('/site/photos', [SiteController::class, 'photo'])->middleware('throttle:20,10')->name('site.photo');
    Route::delete('/site/photos/{photo}', [SiteController::class, 'removePhoto'])->whereNumber('photo')->name('site.photo.remove');
    Route::post('/site/publish', [SiteController::class, 'publish'])->middleware('throttle:20,10')->name('site.publish');
    Route::get('/calendar', [SiteController::class, 'calendar'])->name('calendar');
    Route::post('/calendar/plan', [SiteController::class, 'plan'])->middleware('throttle:6,60')->name('calendar.plan');
    Route::post('/calendar/posts/{post?}', [SiteController::class, 'savePost'])->whereNumber('post')->middleware('throttle:60,10')->name('calendar.save');
    Route::post('/calendar/posts/{post}/mark', [SiteController::class, 'markPost'])->whereNumber('post')->name('calendar.mark');
    Route::get('/sites/{name}/moderate', [SiteController::class, 'moderate'])->middleware('permission:admin.moderation.review')->name('site.moderate');
    Route::post('/sites/{name}/moderate', [SiteController::class, 'takeDown'])->middleware(['permission:admin.moderation.review', 'pin.confirmed'])->name('site.takedown');
});

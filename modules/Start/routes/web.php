<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Start\Http\Controllers\StartController;
use Modules\Start\Http\Controllers\StepsAdminController;

/*
| KasiStart (S16): businesses, formalisation journey, business plan. Adults only; works for the
| signed-in person or the person being helped in a hub help session.
*/
Route::prefix('start')->name('start.')->middleware(['auth', 'account.ready'])->group(function (): void {
    Route::get('/', [StartController::class, 'index'])->name('home');
    Route::post('/businesses', [StartController::class, 'store'])->middleware('throttle:10,1')->name('businesses.store');
    Route::get('/businesses/{business}', [StartController::class, 'show'])->name('business');
    Route::put('/businesses/{business}', [StartController::class, 'update'])->name('business.update');
    Route::get('/businesses/{business}/guide', [StartController::class, 'guide'])->name('guide');
    Route::post('/businesses/{business}/form', [StartController::class, 'chooseForm'])->name('form');
    Route::post('/businesses/{business}/steps/{step}', [StartController::class, 'done'])->name('steps.done');
    Route::delete('/businesses/{business}/steps/{step}', [StartController::class, 'undo'])->name('steps.undo');
    Route::post('/businesses/{business}/members', [StartController::class, 'addMember'])->name('members.store');
    Route::delete('/businesses/{business}/members/{member}', [StartController::class, 'removeMember'])->name('members.destroy');
    Route::get('/businesses/{business}/plan', [StartController::class, 'plan'])->name('plan');
    Route::put('/businesses/{business}/plan', [StartController::class, 'savePlan'])->name('plan.save');
    Route::post('/businesses/{business}/plan/improve', [StartController::class, 'improve'])->middleware('throttle:20,1')->name('plan.improve');
    Route::get('/businesses/{business}/summary', [StartController::class, 'summary'])->middleware('throttle:10,1')->name('summary');
    Route::get('/businesses/{business}/affidavit', [StartController::class, 'affidavit'])->middleware('throttle:10,1')->name('affidavit');

    Route::middleware('permission:start.content')->group(function (): void {
        Route::get('/admin/steps', [StepsAdminController::class, 'index'])->name('admin.steps');
        Route::put('/admin/steps/{step}', [StepsAdminController::class, 'update'])->name('admin.steps.update');
    });
});

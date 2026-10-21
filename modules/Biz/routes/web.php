<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Biz\Http\Controllers\BooksController;
use Modules\Biz\Http\Controllers\MoreController;

/* KasiBiz (S28): books for the person's KasiStart business; only its owners (or a facilitator helping them). */
Route::prefix('biz')->name('biz.')->middleware(['auth', 'account.ready'])->group(function (): void {
    Route::get('/', [BooksController::class, 'index'])->name('index');
    Route::post('/entries', [BooksController::class, 'record'])->middleware('throttle:120,10')->name('entries.store');
    Route::put('/entries/{entry}', [BooksController::class, 'edit'])->name('entries.update');
    Route::delete('/entries/{entry}', [BooksController::class, 'remove'])->name('entries.delete');
    Route::post('/entries/{entry}/hide', [BooksController::class, 'hide'])->name('entries.hide');
    Route::post('/opening', [BooksController::class, 'opening'])->name('opening');
    Route::get('/owed', [BooksController::class, 'owed'])->name('owed');
    Route::post('/contacts', [BooksController::class, 'contact'])->middleware('throttle:60,10')->name('contacts.store');
    Route::post('/owed', [BooksController::class, 'addOwed'])->middleware('throttle:60,10')->name('owed.store');
    Route::post('/owed/{credit}/paid', [BooksController::class, 'paid'])->whereNumber('credit')->name('owed.paid');
    Route::get('/export.csv', [BooksController::class, 'csv'])->name('export.csv');
    Route::get('/summary.pdf', [BooksController::class, 'pdf'])->middleware('throttle:20,10')->name('summary.pdf');

    // S29: stock, compliance calendar, sharing a summary.
    Route::get('/stock', [MoreController::class, 'stock'])->name('stock');
    Route::post('/stock', [MoreController::class, 'addItem'])->middleware('throttle:60,10')->name('stock.store');
    Route::post('/stock/{item}/move', [MoreController::class, 'move'])->whereNumber('item')->middleware('throttle:120,10')->name('stock.move');
    Route::get('/compliance', [MoreController::class, 'compliance'])->name('compliance');
    Route::post('/compliance/profile', [MoreController::class, 'profile'])->name('compliance.profile');
    Route::post('/compliance/{obligation}/done', [MoreController::class, 'done'])->whereNumber('obligation')->name('compliance.done');
    Route::post('/compliance/{obligation}/date', [MoreController::class, 'moveDate'])->whereNumber('obligation')->name('compliance.date');
    Route::get('/share', [MoreController::class, 'share'])->name('share');
    Route::post('/share', [MoreController::class, 'createShare'])->middleware('throttle:10,60')->name('share.store');
    Route::post('/share/{share}/withdraw', [MoreController::class, 'withdraw'])->name('share.withdraw');
});

// S29: a shared summary opens with its 7-day link alone (no account needed); every view is logged.
Route::get('/biz/shared/{token}', [MoreController::class, 'shared'])->where('token', '[A-Za-z0-9]{40}')->middleware('throttle:30,1')->name('biz.shared');

<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Biz\Http\Controllers\BooksController;

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
});

<?php

declare(strict_types=1);

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;
use Modules\Commercial\Http\Controllers\CheckoutController;
use Modules\Commercial\Http\Controllers\ConsoleController;
use Modules\Commercial\Http\Controllers\HubController;

/*
| Commercial and finance (S19, ADR-027).
*/
// Payment provider notifications: no session or CSRF; the signature is checked.
Route::post('/commercial/webhooks/payments', [CheckoutController::class, 'webhook'])->withoutMiddleware([ValidateCsrfToken::class])
    ->middleware('throttle:120,1')->name('commercial.webhook');

Route::prefix('commercial')->name('commercial.')->middleware(['auth', 'account.ready'])->group(function (): void {
    Route::get('/buy/{key}', [CheckoutController::class, 'show'])->name('buy');
    Route::post('/buy/{key}', [CheckoutController::class, 'start'])->middleware('throttle:20,1')->name('buy.start');
    Route::get('/orders/{order}', [CheckoutController::class, 'order'])->name('order');
    Route::get('/orders/{order}/receipt', [CheckoutController::class, 'receipt'])->middleware('throttle:20,1')->name('order.receipt');
    Route::get('/fake-pay/{reference}', [CheckoutController::class, 'fakePage'])->name('fake');
    Route::post('/fake-pay/{reference}', [CheckoutController::class, 'fakePay'])->name('fake.pay');

    Route::get('/hub', [HubController::class, 'dashboard'])->name('hub');
    Route::post('/hub/details', [HubController::class, 'details'])->name('hub.details');
    Route::post('/hub/franchise', [HubController::class, 'franchise'])->name('hub.franchise');
    Route::post('/hub/bank', [HubController::class, 'bank'])->name('hub.bank');
    Route::post('/hub/sell', [HubController::class, 'sell'])->middleware('throttle:120,1')->name('hub.sell');

    Route::middleware('permission:commercial.view')->group(function (): void {
        Route::get('/', [ConsoleController::class, 'overview'])->name('console');
        Route::post('/prices', [ConsoleController::class, 'proposePrice'])->name('prices');
        Route::post('/rules', [ConsoleController::class, 'proposeRule'])->name('rules');
        Route::post('/approvals/{approval}', [ConsoleController::class, 'decide'])->whereNumber('approval')->middleware('pin.confirmed')->name('approvals');
        Route::post('/invoices/{invoice}/paid', [ConsoleController::class, 'invoicePaid'])->middleware('pin.confirmed')->name('invoices.paid');
        Route::post('/orders/{order}/refund', [ConsoleController::class, 'refund'])->middleware('pin.confirmed')->name('orders.refund');
        Route::post('/issues/{issue}', [ConsoleController::class, 'resolveIssue'])->whereNumber('issue')->name('issues');
        Route::get('/ledger.csv', [ConsoleController::class, 'ledger'])->middleware('pin.confirmed')->name('ledger');
    });
});

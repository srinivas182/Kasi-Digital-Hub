<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Partner\Http\Controllers\PartnerController;
use Modules\Partner\Http\Controllers\SupportController;

/*
| S17: "Support for you" for entrepreneurs, and the partner portal (offers and referrals).
*/
Route::middleware(['auth', 'account.ready'])->group(function (): void {
    Route::prefix('support')->name('support.')->group(function (): void {
        Route::get('/', [SupportController::class, 'index'])->name('home');
        Route::get('/offers/{offer}', [SupportController::class, 'show'])->name('offer');
        Route::post('/offers/{offer}/refer', [SupportController::class, 'refer'])->middleware('throttle:10,1')->name('refer');
        Route::get('/referrals', [SupportController::class, 'referrals'])->name('referrals');
        Route::get('/referrals/{referral}', [SupportController::class, 'referral'])->name('referral');
        Route::post('/referrals/{referral}/withdraw', [SupportController::class, 'withdraw'])->name('referral.withdraw');
        Route::post('/referrals/{referral}/messages', [SupportController::class, 'message'])->middleware('throttle:30,1')->name('referral.messages');
        Route::post('/referrals/{referral}/confirm', [SupportController::class, 'confirm'])->name('referral.confirm');
        Route::post('/followups/{followup}', [SupportController::class, 'followup'])->whereNumber('followup')->name('followup');
    });

    Route::prefix('partner')->name('partner.')->group(function (): void {
        Route::get('/', [PartnerController::class, 'dashboard'])->name('home');
        Route::get('/register', [PartnerController::class, 'registerForm'])->name('register');
        Route::post('/register', [PartnerController::class, 'register'])->middleware('throttle:5,10')->name('register.store');
        Route::post('/agreement', [PartnerController::class, 'acceptAgreement'])->name('agreement');
        Route::post('/team', [PartnerController::class, 'addMember'])->name('team.store');
        Route::get('/offers/create', [PartnerController::class, 'offerForm'])->name('offers.create');
        Route::post('/offers', [PartnerController::class, 'saveOffer'])->name('offers.store');
        Route::get('/offers/{offer}/edit', [PartnerController::class, 'offerForm'])->name('offers.edit');
        Route::put('/offers/{offer}', [PartnerController::class, 'saveOffer'])->name('offers.update');
        Route::post('/offers/{offer}/submit', [PartnerController::class, 'submitOffer'])->name('offers.submit');
        Route::post('/offers/{offer}/close', [PartnerController::class, 'closeOffer'])->name('offers.close');
        Route::get('/referrals/{referral}', [PartnerController::class, 'referral'])->name('referral');
        Route::post('/referrals/{referral}/move', [PartnerController::class, 'move'])->name('referral.move');
        Route::post('/referrals/{referral}/messages', [PartnerController::class, 'message'])->middleware('throttle:60,1')->name('referral.messages');
        Route::post('/referrals/{referral}/outcome', [PartnerController::class, 'outcome'])->name('referral.outcome');
        Route::get('/referrals/{referral}/documents/{document}', [PartnerController::class, 'document'])->middleware('throttle:60,1')->name('referral.document');
    });
});

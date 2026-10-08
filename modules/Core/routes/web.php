<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\Account\AccountController;
use Modules\Core\Http\Controllers\Account\ConsentReviewController;
use Modules\Core\Http\Controllers\Account\DocumentController;
use Modules\Core\Http\Controllers\Account\LegalDocumentController;
use Modules\Core\Http\Controllers\Auth\CodeController;
use Modules\Core\Http\Controllers\Auth\GuardianController;
use Modules\Core\Http\Controllers\Auth\NewPinController;
use Modules\Core\Http\Controllers\Auth\SignInController;
use Modules\Core\Http\Controllers\Auth\SignUpController;
use Modules\Core\Http\Controllers\Auth\TwoFactorController;
use Modules\Core\Http\Controllers\Platform\UpdatesController;
use Modules\Core\Http\Controllers\UiKitController;

/*
| Sign-in and sign-up (guests only). Sign-in is phone -> code (new device) -> PIN ->
| authenticator (staff). Throttles here are a first line; OtpService applies the
| per-number, per-device and range limits.
*/
Route::middleware('guest')->group(function (): void {
    Route::get('/login', [SignInController::class, 'showPhone'])->name('login');
    Route::post('/login', [SignInController::class, 'submitPhone'])->middleware('throttle:auth-step')->name('login.phone');
    Route::get('/login/code', [CodeController::class, 'show'])->name('login.code');
    Route::post('/login/code', [CodeController::class, 'verify'])->middleware('throttle:auth-step')->name('login.code.verify');
    Route::post('/login/code/resend', [CodeController::class, 'resend'])->middleware('throttle:auth-sms')->name('login.code.resend');
    Route::get('/login/pin', [SignInController::class, 'showPin'])->name('login.pin');
    Route::post('/login/pin', [SignInController::class, 'submitPin'])->middleware('throttle:auth-step')->name('login.pin.submit');
    Route::post('/login/forgot', [SignInController::class, 'forgotPin'])->middleware('throttle:auth-sms')->name('login.forgot');
    Route::get('/login/new-pin', [NewPinController::class, 'show'])->name('login.new-pin');
    Route::post('/login/new-pin', [NewPinController::class, 'store'])->name('login.new-pin.store');

    Route::get('/signup', [SignUpController::class, 'show'])->name('signup');
    Route::post('/signup', [SignUpController::class, 'store'])->middleware('throttle:auth-signup')->name('signup.store');
    Route::get('/signup/declined', [SignUpController::class, 'declined'])->name('signup.declined');
    Route::get('/signup/guardian', [GuardianController::class, 'show'])->name('signup.guardian');
    Route::post('/signup/guardian', [GuardianController::class, 'store'])->middleware('throttle:auth-sms')->name('signup.guardian.store');
    Route::post('/signup/guardian/code', [GuardianController::class, 'verify'])->middleware('throttle:auth-step')->name('signup.guardian.verify');

    Route::get('/two-factor/setup', [TwoFactorController::class, 'setup'])->name('two-factor.setup');
    Route::post('/two-factor/setup', [TwoFactorController::class, 'confirm'])->middleware('throttle:auth-signup')->name('two-factor.confirm');
    Route::get('/two-factor/recovery-codes', [TwoFactorController::class, 'recoveryCodes'])->name('two-factor.recovery-codes');
    Route::post('/two-factor/finish', [TwoFactorController::class, 'finish'])->name('two-factor.finish');
    Route::get('/two-factor/challenge', [TwoFactorController::class, 'challenge'])->name('two-factor.challenge');
    Route::post('/two-factor/challenge', [TwoFactorController::class, 'verify'])->middleware('throttle:auth-signup')->name('two-factor.verify');
});

Route::post('/logout', [SignInController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'account.ready'])->group(function (): void {
    Route::get('/account', [AccountController::class, 'show'])->name('account');
    Route::put('/account/profile', [AccountController::class, 'updateProfile'])->name('account.profile');
    Route::put('/account/pin', [AccountController::class, 'updatePin'])->middleware('throttle:auth-signup')->name('account.pin');
    Route::post('/account/phone', [AccountController::class, 'requestPhoneChange'])->middleware('throttle:auth-sms')->name('account.phone');
    Route::post('/account/phone/verify', [AccountController::class, 'confirmPhoneChange'])->middleware('throttle:auth-signup')->name('account.phone.verify');
    Route::post('/account/phone/cancel', [AccountController::class, 'cancelPhoneChange'])->name('account.phone.cancel');
    Route::delete('/account/devices/{device}', [AccountController::class, 'revokeDevice'])->name('account.devices.revoke');
    Route::post('/account/devices/sign-out-others', [AccountController::class, 'revokeOtherDevices'])->name('account.devices.revoke-others');
    Route::put('/account/consents', [AccountController::class, 'updateConsents'])->name('account.consents');
    Route::post('/account/deletion', [AccountController::class, 'requestDeletion'])->name('account.deletion');
    Route::put('/account/notifications', [AccountController::class, 'updateNotifications'])->name('account.notifications');

    Route::post('/account/documents', [DocumentController::class, 'store'])->middleware('throttle:20,1')->name('documents.store');
    Route::delete('/account/documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');

    Route::get('/home/updates', [UpdatesController::class, 'index'])->name('hub.updates');
    Route::get('/home/updates/{update}', [UpdatesController::class, 'open'])->name('hub.updates.open');
    Route::post('/home/updates/read', [UpdatesController::class, 'markAllRead'])->name('hub.updates.read');
});

// Signed, short-lived document links (permission checked again on open).
Route::get('/documents/{document}/download', [DocumentController::class, 'download'])->middleware(['auth', 'throttle:60,1'])->name('documents.download');

Route::middleware('auth')->group(function (): void {
    Route::get('/consents/review', [ConsentReviewController::class, 'show'])->name('consents.review');
    Route::post('/consents/review', [ConsentReviewController::class, 'store'])->name('consents.review.store');
});

Route::get('/account/email/verify/{id}/{hash}', [AccountController::class, 'verifyEmail'])->middleware('throttle:auth-signup')->name('account.email.verify');
Route::get('/legal/{key}', LegalDocumentController::class)->whereIn('key', ['terms', 'privacy'])->name('legal');

$uiKit = config('kasi.ui_kit.enabled');
$uiKitEnabled = $uiKit === null ? ! app()->isProduction() : filter_var($uiKit, FILTER_VALIDATE_BOOL);

if ($uiKitEnabled) {
    Route::get('/ui-kit', [UiKitController::class, 'index'])->name('ui-kit');
    Route::get('/ui-kit/layouts/{layout}', [UiKitController::class, 'layout'])->name('ui-kit.layout');
}

// Offline fallback served by the service worker when there is no connection.
Route::view('/offline', 'offline')->name('offline');

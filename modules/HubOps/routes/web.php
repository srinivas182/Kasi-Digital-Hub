<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\HubOps\Http\Controllers\AssistController;
use Modules\HubOps\Http\Controllers\DashboardController;
use Modules\HubOps\Http\Controllers\DeskController;
use Modules\HubOps\Http\Controllers\DoorController;
use Modules\HubOps\Http\Controllers\EventsController;
use Modules\HubOps\Http\Controllers\RegisterController;
use Modules\HubOps\Http\Controllers\SettingsController;
use Modules\HubOps\Http\Controllers\VisitorEventsController;

/*
| Door screen (no sign-in: opened with a secret link) and scanning its QR code.
*/
Route::middleware('throttle:60,1')->group(function (): void {
    Route::get('/kiosk/{hub:slug}/{token}', [DoorController::class, 'door'])->name('kiosk.door');
    Route::get('/kiosk/{hub:slug}/{token}/code', [DoorController::class, 'code'])->name('kiosk.code');
    Route::get('/check-in/{hub:slug}', [DoorController::class, 'scan'])->name('checkin.scan');
});

/*
| Everyone signed in: confirm a check-in, browse and join events.
*/
Route::middleware(['auth', 'account.ready'])->group(function (): void {
    Route::get('/check-in', [DoorController::class, 'confirm'])->name('checkin.confirm');
    Route::post('/check-in', [DoorController::class, 'store'])->name('checkin.store');

    Route::get('/events', [VisitorEventsController::class, 'index'])->name('events.index');
    Route::get('/events/{event}', [VisitorEventsController::class, 'show'])->name('events.show');
    Route::post('/events/{event}/register', [VisitorEventsController::class, 'register'])->middleware('throttle:20,1')->name('events.register');
    Route::delete('/events/{event}/register', [VisitorEventsController::class, 'cancel'])->name('events.cancel');
    Route::get('/events/{event}/attend', [VisitorEventsController::class, 'attend'])->middleware('signed')->name('events.attend');
});

/*
| Hub staff (KasiHub Ops portal). Hub staff see their own hub; coordinators and national staff choose.
*/
Route::prefix('hub-ops')->name('hubops.')->middleware(['auth', 'account.ready', 'portal:HubOps'])->group(function (): void {
    Route::get('/', [DashboardController::class, 'index'])->middleware('permission:hubops.dashboard')->name('dashboard');
    Route::get('/export', [DashboardController::class, 'export'])->middleware(['permission:hubops.dashboard', 'throttle:10,1'])->name('export');
    Route::get('/compare', [DashboardController::class, 'compare'])->middleware('permission:hubops.compare')->name('compare');

    Route::middleware('permission:hubops.checkin')->group(function (): void {
        Route::get('/check-in', [DeskController::class, 'checkIn'])->name('checkin');
        Route::post('/check-in', [DeskController::class, 'store'])->name('checkin.store');
    });
    Route::get('/members', [DeskController::class, 'members'])->middleware('permission:hubops.members')->name('members');

    Route::middleware('permission:hubops.assist')->group(function (): void {
        Route::post('/assist/{person}', [DeskController::class, 'startAssist'])->name('assist.start');
        Route::get('/assist', [AssistController::class, 'show'])->name('assist.show');
        Route::put('/assist/profile', [AssistController::class, 'updateProfile'])->name('assist.profile');
        Route::post('/assist/document', [AssistController::class, 'uploadDocument'])->name('assist.document');
        Route::post('/assist/end', [AssistController::class, 'end'])->name('assist.end');

        Route::get('/register', [RegisterController::class, 'phone'])->name('register');
        Route::post('/register/code', [RegisterController::class, 'sendCode'])->middleware('throttle:10,1')->name('register.code');
        Route::get('/register/details', [RegisterController::class, 'details'])->name('register.details');
        Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:10,1')->name('register.store');
    });

    Route::middleware('permission:hubops.events')->group(function (): void {
        Route::get('/events', [EventsController::class, 'index'])->name('events.index');
        Route::get('/events/create', [EventsController::class, 'create'])->name('events.create');
        Route::post('/events', [EventsController::class, 'store'])->name('events.store');
        Route::get('/events/{event}', [EventsController::class, 'show'])->name('events.show');
        Route::get('/events/{event}/edit', [EventsController::class, 'edit'])->name('events.edit');
        Route::put('/events/{event}', [EventsController::class, 'update'])->name('events.update');
        Route::post('/events/{event}/registrations', [EventsController::class, 'register'])->name('events.registrations.store');
        Route::delete('/events/{event}/registrations/{registration}', [EventsController::class, 'cancelRegistration'])->name('events.registrations.destroy');
        Route::post('/events/{event}/registrations/{registration}/attend', [EventsController::class, 'attend'])->name('events.attend');
    });
    Route::post('/events/{event}/cancel', [EventsController::class, 'cancel'])->middleware('permission:hubops.events.cancel')->name('events.cancel');

    Route::middleware('permission:hubops.settings')->group(function (): void {
        Route::get('/settings', [SettingsController::class, 'show'])->name('settings');
        Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
        Route::post('/settings/kiosk', [SettingsController::class, 'kiosk'])->name('settings.kiosk');
        Route::post('/settings/facilitators', [SettingsController::class, 'appoint'])->name('settings.facilitators.store');
        Route::delete('/settings/facilitators/{assignment}', [SettingsController::class, 'remove'])->name('settings.facilitators.destroy');
    });
});

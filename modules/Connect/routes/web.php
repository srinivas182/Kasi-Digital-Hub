<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Connect\Http\Controllers\ConnectController;
use Modules\Connect\Http\Controllers\CoordinatorController;

/*
| KasiConnect (S18): mentoring with safeguarding.
*/
Route::prefix('connect')->name('connect.')->middleware(['auth', 'account.ready'])->group(function (): void {
    Route::get('/', [ConnectController::class, 'home'])->name('home');
    Route::get('/find', [ConnectController::class, 'find'])->name('find');
    Route::post('/mentors/{mentor}/request', [ConnectController::class, 'request'])->middleware('throttle:10,60')->name('request');
    Route::post('/requests/{id}/withdraw', [ConnectController::class, 'withdrawRequest'])->name('request.withdraw');
    Route::post('/requests/{id}/answer', [ConnectController::class, 'answer'])->name('request.answer');
    Route::get('/mentorships/{mentorship}', [ConnectController::class, 'show'])->name('mentorship');
    Route::post('/mentorships/{mentorship}/agreement', [ConnectController::class, 'propose'])->name('mentorship.propose');
    Route::post('/mentorships/{mentorship}/agree', [ConnectController::class, 'agree'])->name('mentorship.agree');
    Route::post('/mentorships/{mentorship}/sessions', [ConnectController::class, 'schedule'])->name('mentorship.schedule');
    Route::post('/mentorships/{mentorship}/messages', [ConnectController::class, 'message'])->middleware('throttle:30,1')->name('mentorship.message');
    Route::post('/mentorships/{mentorship}/end', [ConnectController::class, 'end'])->name('mentorship.end');
    Route::post('/mentorships/{mentorship}/renew', [ConnectController::class, 'renew'])->name('mentorship.renew');
    Route::post('/sessions/{session}/record', [ConnectController::class, 'record'])->whereNumber('session')->name('session.record');
    Route::post('/sessions/{session}/helpful', [ConnectController::class, 'helpful'])->whereNumber('session')->name('session.helpful');
    Route::post('/concerns', [ConnectController::class, 'concern'])->middleware('throttle:10,60')->name('concern');
    Route::get('/mentor', [ConnectController::class, 'mentorProfile'])->name('mentor');
    Route::post('/mentor', [ConnectController::class, 'saveMentor'])->name('mentor.save');
    Route::post('/mentor/group-sessions', [ConnectController::class, 'groupSession'])->name('mentor.group');
    Route::get('/mentor/hours', [ConnectController::class, 'hours'])->middleware('throttle:10,1')->name('mentor.hours');

    Route::middleware('permission:connect.coordinate')->group(function (): void {
        Route::get('/coordinator', [CoordinatorController::class, 'mentors'])->name('coordinator');
        Route::post('/coordinator/{mentor}/check', [CoordinatorController::class, 'check'])->name('coordinator.check');
        Route::post('/coordinator/{mentor}/decide', [CoordinatorController::class, 'decide'])->name('coordinator.decide');
    });
    Route::middleware('permission:connect.safeguard')->group(function (): void {
        Route::get('/safeguarding', [CoordinatorController::class, 'concerns'])->name('safeguarding');
        Route::get('/safeguarding/{concern}', [CoordinatorController::class, 'concerns'])->name('safeguarding.concern');
        Route::post('/safeguarding/{concern}', [CoordinatorController::class, 'act'])->name('safeguarding.act');
    });
});

<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Admin\Http\Controllers\AiController;
use Modules\Admin\Http\Controllers\AuditController;
use Modules\Admin\Http\Controllers\DashboardController;
use Modules\Admin\Http\Controllers\DemoController;
use Modules\Admin\Http\Controllers\EnquiriesController;
use Modules\Admin\Http\Controllers\HubsController;
use Modules\Admin\Http\Controllers\InboxController;
use Modules\Admin\Http\Controllers\ModerationController;
use Modules\Admin\Http\Controllers\OrganisationsController;
use Modules\Admin\Http\Controllers\PeopleController;
use Modules\Admin\Http\Controllers\SupportController;
use Modules\Admin\Http\Controllers\SystemController;
use Modules\Admin\Http\Controllers\VerificationController;

/*
| National admin console. Every route needs Admin portal access plus a fine-grained
| permission (see modules/Admin/module.json). Every change is audited.
*/
Route::prefix('admin')->name('admin.')->middleware(['auth', 'account.ready', 'portal:Admin'])->group(function (): void {
    Route::get('/', DashboardController::class)->middleware('permission:admin.dashboard.view')->name('dashboard');

    // Review inbox and system status (S21).
    Route::get('/inbox', [InboxController::class, 'index'])->middleware('permission:admin.moderation.review')->name('inbox');
    Route::post('/inbox/approve', [InboxController::class, 'bulkApprove'])->middleware('permission:admin.moderation.review')->name('inbox.approve');
    Route::get('/system', [SystemController::class, 'index'])->middleware('permission:admin.system.view')->name('system');
    Route::post('/system/failed/{id}/retry', [SystemController::class, 'retry'])->middleware('permission:admin.system.view')->name('system.retry');

    // Support (S21): queue, tickets, safe person summaries and account help.
    Route::middleware('permission:admin.support.handle')->group(function (): void {
        Route::get('/support', [SupportController::class, 'queue'])->name('support.queue');
        Route::get('/support/{ticket}', [SupportController::class, 'ticket'])->name('support.ticket');
        Route::post('/support/{ticket}/reply', [SupportController::class, 'reply'])->name('support.reply');
        Route::get('/support/people/{person}', [SupportController::class, 'person'])->name('support.person');
        Route::post('/support/people/{person}/code', [SupportController::class, 'code'])->middleware('throttle:10,60')->middleware('pin.confirmed')->name('support.code');
        Route::post('/support/people/{person}/act', [SupportController::class, 'act'])->middleware('throttle:20,60')->middleware('pin.confirmed')->name('support.act');
    });

    Route::middleware('permission:admin.people.view')->group(function (): void {
        Route::get('/people', [PeopleController::class, 'index'])->name('people.index');
        Route::get('/people/{person}', [PeopleController::class, 'show'])->name('people.show');
    });
    Route::post('/people/{person}/roles', [PeopleController::class, 'assignRole'])->middleware('permission:admin.roles.assign')->middleware('pin.confirmed')->name('people.roles.assign');
    Route::delete('/people/{person}/roles/{assignment}', [PeopleController::class, 'revokeRole'])->middleware('permission:admin.roles.assign')->name('people.roles.revoke');
    Route::middleware('permission:admin.people.suspend')->group(function (): void {
        Route::post('/people/{person}/suspend', [PeopleController::class, 'suspend'])->middleware('pin.confirmed')->name('people.suspend');
        Route::post('/people/{person}/reactivate', [PeopleController::class, 'reactivate'])->middleware('pin.confirmed')->name('people.reactivate');
        Route::post('/people/{person}/sign-out', [PeopleController::class, 'signOutEverywhere'])->middleware('pin.confirmed')->name('people.sign-out');
    });

    Route::get('/hubs', [HubsController::class, 'index'])->middleware('permission:admin.hubs.view')->name('hubs.index');
    Route::middleware('permission:admin.hubs.manage')->group(function (): void {
        Route::get('/hubs/create', [HubsController::class, 'create'])->name('hubs.create');
        Route::post('/hubs', [HubsController::class, 'store'])->name('hubs.store');
        Route::put('/hubs/{hub}', [HubsController::class, 'update'])->name('hubs.update');
        Route::post('/hubs/{hub}/modules', [HubsController::class, 'toggleModule'])->name('hubs.modules');
    });
    Route::get('/hubs/{hub}', [HubsController::class, 'edit'])->middleware('permission:admin.hubs.view')->name('hubs.edit');

    Route::get('/organisations', [OrganisationsController::class, 'index'])->middleware('permission:admin.organisations.view')->name('organisations.index');
    Route::middleware('permission:admin.organisations.manage')->group(function (): void {
        Route::get('/organisations/create', [OrganisationsController::class, 'create'])->name('organisations.create');
        Route::post('/organisations', [OrganisationsController::class, 'store'])->name('organisations.store');
        Route::put('/organisations/{organisation}', [OrganisationsController::class, 'update'])->name('organisations.update');
        Route::post('/organisations/{organisation}/members', [OrganisationsController::class, 'addMember'])->name('organisations.members.add');
        Route::delete('/organisations/{organisation}/members/{member}', [OrganisationsController::class, 'removeMember'])->name('organisations.members.remove');
    });
    Route::middleware('permission:admin.organisations.verify')->group(function (): void {
        Route::post('/organisations/{organisation}/verify', [OrganisationsController::class, 'verify'])->name('organisations.verify');
        Route::post('/organisations/{organisation}/reject', [OrganisationsController::class, 'reject'])->name('organisations.reject');
        Route::post('/organisations/{organisation}/checklist', [OrganisationsController::class, 'checklist'])->name('organisations.checklist');
    });
    Route::get('/organisations/{organisation}', [OrganisationsController::class, 'show'])->middleware('permission:admin.organisations.view')->name('organisations.show');

    Route::middleware('permission:admin.documents.verify')->group(function (): void {
        Route::get('/verification', [VerificationController::class, 'index'])->name('verification.index');
        Route::post('/verification/{document}/verify', [VerificationController::class, 'verify'])->name('verification.verify');
        Route::post('/verification/{document}/reject', [VerificationController::class, 'reject'])->name('verification.reject');
    });

    Route::middleware('permission:admin.enquiries.handle')->group(function (): void {
        Route::get('/enquiries', [EnquiriesController::class, 'index'])->name('enquiries.index');
        Route::put('/enquiries/{enquiry}', [EnquiriesController::class, 'update'])->name('enquiries.update');
    });

    Route::get('/ai', [AiController::class, 'index'])->middleware('permission:admin.ai.view')->name('ai.index');
    Route::put('/ai', [AiController::class, 'update'])->middleware('permission:admin.ai.manage')->name('ai.update');
    Route::middleware('permission:admin.moderation.review')->group(function (): void {
        Route::get('/moderation', [ModerationController::class, 'index'])->name('moderation.index');
        Route::post('/moderation/{flag}', [ModerationController::class, 'decide'])->name('moderation.decide');
    });

    Route::get('/audit', [AuditController::class, 'index'])->middleware('permission:admin.audit.view')->name('audit.index');
    Route::get('/audit/export', [AuditController::class, 'export'])->middleware(['permission:admin.audit.export', 'throttle:5,10'])->middleware('pin.confirmed')->name('audit.export');
});

// People's own help requests (S21).
Route::prefix('help/requests')->name('help.')->middleware(['auth', 'account.ready'])->group(function (): void {
    Route::get('/', [SupportController::class, 'mine'])->name('requests');
    Route::post('/', [SupportController::class, 'open'])->middleware('throttle:5,60')->name('requests.open');
    Route::get('/{ticket}', [SupportController::class, 'showMine'])->name('request');
    Route::post('/{ticket}', [SupportController::class, 'replyMine'])->middleware('throttle:20,60')->name('request.reply');
});

// Demo tools (S21): only when demo mode is on and the app is not production (checked in the controller too).
Route::prefix('demo')->name('demo.')->middleware(['auth'])->group(function (): void {
    Route::get('/', [DemoController::class, 'stories'])->name('stories');
    Route::post('/switch/{user}', [DemoController::class, 'switch'])->middleware('throttle:60,1')->name('switch');
    Route::post('/reset', [DemoController::class, 'reset'])->middleware('throttle:3,10')->middleware('pin.confirmed')->name('reset');
});

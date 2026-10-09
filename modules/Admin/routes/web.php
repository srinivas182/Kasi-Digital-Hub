<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Admin\Http\Controllers\AiController;
use Modules\Admin\Http\Controllers\AuditController;
use Modules\Admin\Http\Controllers\DashboardController;
use Modules\Admin\Http\Controllers\EnquiriesController;
use Modules\Admin\Http\Controllers\HubsController;
use Modules\Admin\Http\Controllers\ModerationController;
use Modules\Admin\Http\Controllers\OrganisationsController;
use Modules\Admin\Http\Controllers\PeopleController;
use Modules\Admin\Http\Controllers\VerificationController;

/*
| National admin console. Every route needs Admin portal access plus a fine-grained
| permission (see modules/Admin/module.json). Every change is audited.
*/
Route::prefix('admin')->name('admin.')->middleware(['auth', 'account.ready', 'portal:Admin'])->group(function (): void {
    Route::get('/', DashboardController::class)->middleware('permission:admin.dashboard.view')->name('dashboard');

    Route::middleware('permission:admin.people.view')->group(function (): void {
        Route::get('/people', [PeopleController::class, 'index'])->name('people.index');
        Route::get('/people/{person}', [PeopleController::class, 'show'])->name('people.show');
    });
    Route::post('/people/{person}/roles', [PeopleController::class, 'assignRole'])->middleware('permission:admin.roles.assign')->name('people.roles.assign');
    Route::delete('/people/{person}/roles/{assignment}', [PeopleController::class, 'revokeRole'])->middleware('permission:admin.roles.assign')->name('people.roles.revoke');
    Route::middleware('permission:admin.people.suspend')->group(function (): void {
        Route::post('/people/{person}/suspend', [PeopleController::class, 'suspend'])->name('people.suspend');
        Route::post('/people/{person}/reactivate', [PeopleController::class, 'reactivate'])->name('people.reactivate');
        Route::post('/people/{person}/sign-out', [PeopleController::class, 'signOutEverywhere'])->name('people.sign-out');
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
    Route::get('/audit/export', [AuditController::class, 'export'])->middleware(['permission:admin.audit.export', 'throttle:5,10'])->name('audit.export');
});

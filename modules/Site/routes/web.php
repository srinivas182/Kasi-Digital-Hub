<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Site\Http\Controllers\EnquiryController;
use Modules\Site\Http\Controllers\HelpController;
use Modules\Site\Http\Controllers\HomeController;
use Modules\Site\Http\Controllers\HubDirectoryController;
use Modules\Site\Http\Controllers\PageController;
use Modules\Site\Http\Controllers\SeoController;
use Modules\Site\Http\Middleware\CountPageView;

/*
| Public website. Page views are counted per page per day without cookies or personal data.
*/
Route::middleware(CountPageView::class)->group(function (): void {
    Route::get('/', HomeController::class)->name('site.home');
    Route::get('/hubs', [HubDirectoryController::class, 'index'])->name('site.hubs');
    Route::get('/hubs/{slug}', [HubDirectoryController::class, 'show'])->where('slug', '[a-z0-9-]+')->name('site.hub');
    Route::get('/employers', [PageController::class, 'employers'])->name('site.employers');
    Route::get('/funders', [PageController::class, 'funders'])->name('site.funders');
    Route::get('/about', [PageController::class, 'about'])->name('site.about');
    Route::get('/help', [PageController::class, 'help'])->name('site.help');
    Route::get('/help/{slug}', [HelpController::class, 'article'])->where('slug', '^(?!requests)[a-z0-9-]+$')->name('site.help.article');
    Route::post('/help/{slug}/feedback', [HelpController::class, 'feedback'])->where('slug', '[a-z0-9-]+')->middleware('throttle:30,1')->name('site.help.feedback');
    Route::get('/contact', [PageController::class, 'contact'])->name('site.contact');
});

Route::post('/enquiries/{kind}', [EnquiryController::class, 'store'])
    ->whereIn('kind', ['contact', 'employer', 'funder'])
    ->middleware('throttle:site-enquiries')
    ->name('site.enquiries.store');

Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('site.sitemap');
Route::get('/robots.txt', [SeoController::class, 'robots'])->name('site.robots');

// Help centre content team (S21): edit, then a second person reviews and publishes.
Route::prefix('admin/help')->name('admin.help.')->middleware(['auth', 'account.ready', 'permission:admin.help.manage'])->group(function (): void {
    Route::get('/', [HelpController::class, 'manage'])->name('index');
    Route::post('/', [HelpController::class, 'save'])->name('save');
    Route::post('/{article}/review', [HelpController::class, 'review'])->whereNumber('article')->name('review');
});

<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Learn\Http\Controllers\AuthorController;
use Modules\Learn\Http\Controllers\CatalogueController;
use Modules\Learn\Http\Controllers\ProviderController;
use Modules\Learn\Http\Controllers\ReviewController;

/*
| KasiLearn (S13): catalogue (public and signed in), providers, authoring, KasiHub review.
*/
Route::prefix('learn')->name('learn.')->group(function (): void {
    // Public catalogue: course pages are shareable; signed-in people see more (saved, suggestions).
    Route::get('/courses', [CatalogueController::class, 'index'])->middleware('throttle:120,1')->name('courses');
    Route::get('/courses/{slug}', [CatalogueController::class, 'show'])->middleware('throttle:120,1')->name('courses.show');
    Route::get('/courses/{slug}/preview/{lesson}', [CatalogueController::class, 'preview'])->middleware('throttle:120,1')->name('courses.preview');
    Route::get('/media/{media}/{version?}', [CatalogueController::class, 'media'])->whereIn('version', ['low', 'standard', 'audio', 'thumb'])->middleware('throttle:300,1')->name('media');

    Route::middleware(['auth', 'account.ready'])->group(function (): void {
        Route::redirect('/', '/learn/courses')->name('home');
        Route::post('/courses/{slug}/save', [CatalogueController::class, 'save'])->name('courses.save');
        Route::delete('/courses/{slug}/save', [CatalogueController::class, 'unsave'])->name('courses.unsave');

        Route::get('/provider', [ProviderController::class, 'dashboard'])->name('provider');
        Route::get('/provider/register', [ProviderController::class, 'registerForm'])->name('provider.register');
        Route::post('/provider/register', [ProviderController::class, 'register'])->middleware('throttle:5,10')->name('provider.register.store');
        Route::post('/provider/team', [ProviderController::class, 'addMember'])->name('provider.team.store');
        Route::delete('/provider/team/{member}/{role}', [ProviderController::class, 'removeMember'])->name('provider.team.destroy');
        Route::post('/provider/accreditations', [ProviderController::class, 'claimAccreditation'])->name('provider.accreditations.store');

        Route::redirect('/author', '/learn/provider')->name('author');
        Route::post('/author/courses', [AuthorController::class, 'store'])->name('author.courses.store');
        Route::get('/author/courses/{course}', [AuthorController::class, 'show'])->name('author.course');
        Route::put('/author/courses/{course}', [AuthorController::class, 'update'])->name('author.course.update');
        Route::post('/author/courses/{course}/modules', [AuthorController::class, 'addModule'])->name('author.modules.store');
        Route::put('/author/courses/{course}/modules/{module}', [AuthorController::class, 'updateModule'])->name('author.modules.update');
        Route::delete('/author/courses/{course}/modules/{module}', [AuthorController::class, 'deleteModule'])->name('author.modules.destroy');
        Route::post('/author/courses/{course}/lessons', [AuthorController::class, 'addLesson'])->name('author.lessons.store');
        Route::get('/author/courses/{course}/lessons/{lesson}', [AuthorController::class, 'lesson'])->name('author.lesson');
        Route::put('/author/courses/{course}/lessons/{lesson}', [AuthorController::class, 'updateLesson'])->name('author.lesson.update');
        Route::delete('/author/courses/{course}/lessons/{lesson}', [AuthorController::class, 'deleteLesson'])->name('author.lesson.destroy');
        Route::post('/author/courses/{course}/media', [AuthorController::class, 'upload'])->middleware('throttle:30,1')->name('author.media');
        Route::post('/author/courses/{course}/assist', [AuthorController::class, 'assist'])->middleware('throttle:20,1')->name('author.assist');
        Route::post('/author/courses/{course}/submit', [AuthorController::class, 'submit'])->name('author.submit');
        Route::post('/author/courses/{course}/decide', [AuthorController::class, 'decide'])->name('author.decide');

        Route::middleware('permission:learn.review')->group(function (): void {
            Route::get('/review', [ReviewController::class, 'index'])->name('review');
            Route::get('/review/courses/{course}', [ReviewController::class, 'show'])->name('review.course');
            Route::post('/review/courses/{course}', [ReviewController::class, 'decide'])->name('review.decide');
            Route::post('/review/accreditations/{accreditation}', [ReviewController::class, 'accreditation'])->name('review.accreditation');
        });
    });
});

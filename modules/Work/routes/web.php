<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Work\Http\Controllers\CvController;
use Modules\Work\Http\Controllers\ExperienceController;
use Modules\Work\Http\Controllers\ProfileController;

/*
| KasiWork for job seekers (S9). Adults only; works for the signed-in person or, during a
| facilitator help session at the hub, for the person being helped (see WorkSubject).
*/
Route::prefix('work')->name('work.')->middleware(['auth', 'account.ready'])->group(function (): void {
    Route::redirect('/', '/work/profile')->name('home');
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile');
    Route::put('/profile/about', [ProfileController::class, 'about'])->name('profile.about');
    Route::put('/profile/looking-for', [ProfileController::class, 'lookingFor'])->name('profile.looking_for');
    Route::put('/profile/skills', [ProfileController::class, 'skills'])->name('profile.skills');

    Route::post('/experience', [ExperienceController::class, 'store'])->name('experience.store');
    Route::put('/experience/{experience}', [ExperienceController::class, 'update'])->name('experience.update');
    Route::delete('/experience/{experience}', [ExperienceController::class, 'destroy'])->name('experience.destroy');
    Route::post('/experience/{experience}/suggest', [ExperienceController::class, 'suggest'])->middleware('throttle:20,1')->name('experience.suggest');
    Route::put('/experience/{experience}/bullets', [ExperienceController::class, 'bullets'])->name('experience.bullets');

    Route::post('/education', [ExperienceController::class, 'storeEducation'])->name('education.store');
    Route::put('/education/{education}', [ExperienceController::class, 'updateEducation'])->name('education.update');
    Route::delete('/education/{education}', [ExperienceController::class, 'destroyEducation'])->name('education.destroy');

    Route::get('/cv', [CvController::class, 'index'])->name('cv');
    Route::post('/cv/summary/suggest', [CvController::class, 'suggestSummary'])->middleware('throttle:20,1')->name('cv.summary.suggest');
    Route::put('/cv/summary', [CvController::class, 'acceptSummary'])->name('cv.summary');
    Route::post('/cv', [CvController::class, 'store'])->middleware('throttle:10,1')->name('cv.store');
    Route::get('/cv/{cv}/download', [CvController::class, 'download'])->name('cv.download');
    Route::post('/cv/{cv}/share', [CvController::class, 'share'])->middleware('throttle:20,1')->name('cv.share');
    Route::delete('/cv/{cv}/share/{link}', [CvController::class, 'stopSharing'])->name('cv.share.stop');
    Route::delete('/cv/{cv}', [CvController::class, 'destroy'])->name('cv.destroy');

    Route::post('/voice', [CvController::class, 'voice'])->middleware('throttle:10,1')->name('voice');
});

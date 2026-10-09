<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Work\Http\Controllers\ApplicationsController;
use Modules\Work\Http\Controllers\CandidatesController;
use Modules\Work\Http\Controllers\CvController;
use Modules\Work\Http\Controllers\EmployerController;
use Modules\Work\Http\Controllers\ExperienceController;
use Modules\Work\Http\Controllers\InsightsController;
use Modules\Work\Http\Controllers\JobsController;
use Modules\Work\Http\Controllers\ListingController;
use Modules\Work\Http\Controllers\MatchesController;
use Modules\Work\Http\Controllers\PipelineController;
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

    // Job board (S10)
    Route::get('/jobs', [JobsController::class, 'index'])->name('jobs');
    Route::get('/jobs/{listing}', [JobsController::class, 'show'])->name('jobs.show');
    Route::post('/jobs/{listing}/save', [JobsController::class, 'save'])->name('jobs.save');
    Route::delete('/jobs/{listing}/save', [JobsController::class, 'unsave'])->name('jobs.unsave');

    // Matching (S11)
    Route::get('/matches', [MatchesController::class, 'index'])->name('matches');
    Route::post('/invitations/{invitation}', [MatchesController::class, 'answer'])->name('invitations.answer');
    Route::post('/hidden', [MatchesController::class, 'hide'])->name('hidden.store');
    Route::delete('/hidden/{organisation}', [MatchesController::class, 'unhide'])->name('hidden.destroy');
    Route::post('/jobs/{listing}/hide-employer', [MatchesController::class, 'hideEmployerOf'])->name('jobs.hide_employer');
    Route::get('/employer/listings/{listing}/candidates', [CandidatesController::class, 'index'])->name('employer.candidates');
    Route::get('/employer/listings/{listing}/candidates/{person}', [CandidatesController::class, 'show'])->middleware('throttle:120,1')->name('employer.candidates.show');
    Route::post('/employer/listings/{listing}/candidates/{person}/invite', [CandidatesController::class, 'invite'])->middleware('throttle:30,1')->name('employer.candidates.invite');
    Route::get('/insights', InsightsController::class)->middleware('permission:work.insights')->name('insights');

    // Hiring (S12)
    Route::get('/jobs/{listing}/apply', [ApplicationsController::class, 'create'])->name('apply');
    Route::post('/jobs/{listing}/apply', [ApplicationsController::class, 'store'])->middleware('throttle:20,1')->name('apply.store');
    Route::get('/applications', [ApplicationsController::class, 'index'])->name('applications');
    Route::get('/applications/{application}', [ApplicationsController::class, 'show'])->name('applications.show');
    Route::post('/applications/{application}/withdraw', [ApplicationsController::class, 'withdraw'])->name('applications.withdraw');
    Route::post('/applications/{application}/messages', [ApplicationsController::class, 'message'])->middleware('throttle:30,1')->name('applications.messages');
    Route::post('/applications/{application}/hire', [ApplicationsController::class, 'confirmHire'])->name('applications.hire');
    Route::post('/interviews/{interview}', [ApplicationsController::class, 'answerInterview'])->name('interviews.answer');
    Route::post('/retention/{check}', [ApplicationsController::class, 'retention'])->whereNumber('check')->name('retention.answer');
    Route::post('/messages/{message}/report', [ApplicationsController::class, 'report'])->name('messages.report');
    Route::get('/employer/listings/{listing}/applicants', [PipelineController::class, 'index'])->name('employer.applicants');
    Route::post('/employer/listings/{listing}/applicants/move', [PipelineController::class, 'move'])->name('employer.applicants.move');
    Route::get('/employer/listings/{listing}/applicants/{application}', [PipelineController::class, 'show'])->name('employer.applicants.show');
    Route::get('/employer/listings/{listing}/applicants/{application}/cv', [PipelineController::class, 'cv'])->name('employer.applicants.cv');
    Route::post('/employer/listings/{listing}/applicants/{application}/notes', [PipelineController::class, 'note'])->name('employer.applicants.notes');
    Route::post('/employer/listings/{listing}/applicants/{application}/interviews', [PipelineController::class, 'interview'])->name('employer.applicants.interviews');
    Route::post('/employer/listings/{listing}/applicants/{application}/messages', [PipelineController::class, 'message'])->middleware('throttle:60,1')->name('employer.applicants.messages');

    // Employers (S10)
    Route::get('/employer', [EmployerController::class, 'dashboard'])->name('employer');
    Route::get('/employer/register', [EmployerController::class, 'registerForm'])->name('employer.register');
    Route::post('/employer/register', [EmployerController::class, 'register'])->middleware('throttle:5,10')->name('employer.register.store');
    Route::put('/employer/profile', [EmployerController::class, 'updateProfile'])->name('employer.profile');
    Route::post('/employer/team', [EmployerController::class, 'addRecruiter'])->name('employer.team.store');
    Route::delete('/employer/team/{member}', [EmployerController::class, 'removeRecruiter'])->name('employer.team.destroy');
    Route::get('/employer/listings/create', [ListingController::class, 'create'])->name('employer.listings.create');
    Route::post('/employer/listings', [ListingController::class, 'store'])->name('employer.listings.store');
    Route::post('/employer/listings/write', [ListingController::class, 'write'])->middleware('throttle:20,1')->name('employer.listings.write');
    Route::get('/employer/occupations', [ListingController::class, 'occupations'])->name('employer.occupations');
    Route::get('/employer/listings/{listing}/edit', [ListingController::class, 'edit'])->name('employer.listings.edit');
    Route::put('/employer/listings/{listing}', [ListingController::class, 'update'])->name('employer.listings.update');
    Route::post('/employer/listings/{listing}/publish', [ListingController::class, 'publish'])->name('employer.listings.publish');
    Route::post('/employer/listings/{listing}/close', [ListingController::class, 'close'])->name('employer.listings.close');
    Route::post('/employer/listings/{listing}/renew', [ListingController::class, 'renew'])->name('employer.listings.renew');
});

// Public job pages: shareable and findable by search engines; no employer contact details.
Route::get('/jobs/{listing}', [JobsController::class, 'public'])->middleware('throttle:120,1')->name('jobs.public');
Route::post('/jobs/{listing}/take-down', [JobsController::class, 'takeDown'])->middleware(['auth', 'account.ready'])->name('jobs.take_down');

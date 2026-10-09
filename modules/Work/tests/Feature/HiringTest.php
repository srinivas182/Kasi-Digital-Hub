<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Access\Scope;
use Modules\Core\Ai\Models\ModerationFlag;
use Modules\Core\Identity\Models\User;
use Modules\Core\Platform\Models\PlatformEventRecord;
use Modules\Core\Platform\Models\Update;
use Modules\Core\Structure\Models\Organisation;
use Modules\Core\Tests\Structure;
use Modules\HubOps\Tests\Staff;
use Modules\Work\Models\Application;
use Modules\Work\Models\JobListing;
use Modules\Work\Services\Hiring;
use Modules\Work\Services\Listings;
use Modules\Work\Services\SeekerProfile;
use Tests\TestCase;

beforeEach(function (): void {
    Structure::seed($this);
    Storage::fake('documents');
    $this->employer = Organisation::query()->create(['type' => 'employer', 'name' => 'Giyani Bakery', 'verification_status' => 'verified', 'verified_at' => now()]);
    $this->owner = Structure::personWith('employer_admin', Scope::organisation($this->employer));
    $this->listing = JobListing::query()->create([
        'organisation_id' => $this->employer->id, 'created_by' => $this->owner->id, 'title' => 'Cashier', 'type' => 'part_time', 'positions' => 1,
        'municipality_id' => Structure::city('LIM331')->id, 'pay_min_cents' => 3200, 'pay_period' => 'hour', 'experience' => 'none',
        'description' => 'Serve customers.', 'closes_on' => now()->addDays(10)->toDateString(), 'status' => 'live', 'published_at' => now(),
    ]);
    $this->listing->questions()->create(['question' => 'Can you work weekends?', 'kind' => 'yes_no', 'position' => 0]);
    $this->person = User::factory()->create(['first_name' => 'Thandi', 'last_name' => 'Mabasa']);
    app(SeekerProfile::class)->save($this->person, ['headline' => 'Friendly cashier', 'summary' => 'Good with people', 'drivers_licence' => 'none']);
});

function applyAs(TestCase $test, User $person, JobListing $listing): Application
{
    Staff::signIn($test, $person);
    $test->post("/work/jobs/{$listing->id}/apply", ['answers' => ['Yes'], 'message' => 'I live nearby.', 'share' => true])->assertSessionHasNoErrors();

    return Application::query()->where('user_id', $person->id)->sole();
}

it('applies with a CV made on the spot, screening answers and a sharing confirmation', function (): void {
    Staff::signIn($this, $this->person);
    $this->get("/work/jobs/{$this->listing->id}/apply")->assertInertia(fn ($page) => $page->component('Work/Applications/Apply')->has('questions', 1));
    $this->post("/work/jobs/{$this->listing->id}/apply", ['answers' => ['Yes']])->assertSessionHasErrors('share');

    $application = applyAs($this, $this->person, $this->listing);

    expect($application->stage)->toBe('new')->and($application->cv_id)->not->toBeNull()
        ->and($application->answers)->toBe([['question' => 'Can you work weekends?', 'answer' => 'Yes']])
        ->and(PlatformEventRecord::query()->where('name', 'work.application.submitted')->exists())->toBeTrue()
        ->and(Update::query()->where('user_id', $this->owner->id)->where('title', 'New application: Cashier')->exists())->toBeTrue();

    $this->post("/work/jobs/{$this->listing->id}/apply", ['answers' => ['Yes'], 'share' => true])->assertSessionHasErrors('apply'); // once only
    $this->get("/work/jobs/{$this->listing->id}/apply")->assertRedirect("/work/applications/{$application->id}");
});

it('limits applications to 20 a day', function (): void {
    foreach (range(1, 20) as $i) {
        $l = $this->listing->replicate();
        $l->save();
        Application::query()->create(['listing_id' => $l->id, 'user_id' => $this->person->id, 'stage' => 'new', 'reference' => 'T'.$i]);
    }

    Staff::signIn($this, $this->person);
    $this->post("/work/jobs/{$this->listing->id}/apply", ['answers' => ['Yes'], 'share' => true])->assertSessionHasErrors('apply');
});

it('moves applicants through the pipeline, tells them, and keeps a timeline', function (): void {
    $application = applyAs($this, $this->person, $this->listing);
    Staff::signIn($this, $this->owner);

    $this->get("/work/employer/listings/{$this->listing->id}/applicants")->assertInertia(fn ($page) => $page->component('Work/Employer/Pipeline')
        ->where('applications.0.name', 'Thandi Mabasa')->where('applications.0.phone', fn ($p) => $p !== null));
    $this->post("/work/employer/listings/{$this->listing->id}/applicants/move", ['ids' => [$application->id], 'stage' => 'shortlisted'])->assertSessionHasNoErrors();

    expect($application->refresh()->stage)->toBe('shortlisted')
        ->and(Update::query()->where('user_id', $this->person->id)->where('title', "You're on the shortlist: Cashier")->exists())->toBeTrue();

    Staff::signIn($this, $this->person);
    $this->get("/work/applications/{$application->id}")->assertInertia(fn ($page) => $page->where('application.stage', 'shortlisted')
        ->where('timeline', fn ($t) => collect($t)->pluck('kind')->all() === ['submitted', 'stage']));
});

it('keeps private rejection reasons from the applicant, with a kind message', function (): void {
    $application = applyAs($this, $this->person, $this->listing);
    Staff::signIn($this, $this->owner);
    $this->post("/work/employer/listings/{$this->listing->id}/applicants/move", ['ids' => [$application->id], 'stage' => 'unsuccessful', 'reason' => 'distance', 'message' => 'We hope to see you again.']);

    expect(Update::query()->where('user_id', $this->person->id)->where('title', 'Update on your application: Cashier')->value('body'))->toContain('We hope to see you again.')->not->toContain('distance');

    Staff::signIn($this, $this->person);
    $this->get("/work/applications/{$application->id}")->assertInertia(fn ($page) => $page->where('timeline', fn ($t) => ! str_contains(json_encode($t), 'distance')));
});

it('hides names, phone and CV with blind shortlisting until the interview stage', function (): void {
    $this->listing->forceFill(['blind_shortlisting' => true])->save();
    $application = applyAs($this, $this->person, $this->listing);
    Staff::signIn($this, $this->owner);

    $this->get("/work/employer/listings/{$this->listing->id}/applicants/{$application->id}")->assertInertia(fn ($page) => $page
        ->where('application.name', 'Applicant '.$application->reference)->where('application.phone', null)->where('application.cvUrl', null));
    $this->get("/work/employer/listings/{$this->listing->id}/applicants/{$application->id}/cv")->assertNotFound();

    $this->post("/work/employer/listings/{$this->listing->id}/applicants/move", ['ids' => [$application->id], 'stage' => 'interview']);
    $this->get("/work/employer/listings/{$this->listing->id}/applicants/{$application->id}")->assertInertia(fn ($page) => $page->where('application.name', 'Thandi Mabasa'));
    $this->get("/work/employer/listings/{$this->listing->id}/applicants/{$application->id}/cv")->assertOk();
});

it('keeps applicants private to the employer team', function (): void {
    $application = applyAs($this, $this->person, $this->listing);
    $other = Organisation::query()->create(['type' => 'employer', 'name' => 'Other', 'verification_status' => 'verified']);
    Staff::signIn($this, Structure::personWith('employer_admin', Scope::organisation($other)));

    $this->get("/work/employer/listings/{$this->listing->id}/applicants")->assertNotFound();
    $this->get("/work/employer/listings/{$this->listing->id}/applicants/{$application->id}/cv")->assertNotFound();
    Staff::signIn($this, User::factory()->create());
    $this->get("/work/applications/{$application->id}")->assertNotFound();
});

it('schedules interviews the applicant confirms, with a reminder the day before', function (): void {
    $this->travelTo(CarbonImmutable::parse(now('Africa/Johannesburg')->toDateString().' 08:00', 'Africa/Johannesburg'));
    $application = applyAs($this, $this->person, $this->listing);
    Staff::signIn($this, $this->owner);
    $when = now('Africa/Johannesburg')->addDay()->setTime(10, 0)->format('Y-m-d\TH:i');
    $this->post("/work/employer/listings/{$this->listing->id}/applicants/{$application->id}/interviews", ['starts_at' => $when, 'mode' => 'hub', 'place' => 'Tsutsumani Digital Hub'])->assertSessionHasNoErrors();

    expect($application->refresh()->stage)->toBe('interview');
    $interview = DB::table('work_interviews')->value('id');

    Staff::signIn($this, $this->person);
    $this->post("/work/interviews/{$interview}", ['answer' => 'confirmed'])->assertSessionHasNoErrors();
    expect(Update::query()->where('user_id', $this->owner->id)->where('title', 'Interview confirmed - Cashier')->exists())->toBeTrue();

    app(Hiring::class)->housekeeping(); // 26 hours before the interview

    expect(Update::query()->where('user_id', $this->person->id)->where('title', 'Interview tomorrow: Cashier')->exists())->toBeTrue();
});

it('holds scam-like messages for review and lets either side report', function (): void {
    $application = applyAs($this, $this->person, $this->listing);
    Staff::signIn($this, $this->owner);

    $this->post("/work/employer/listings/{$this->listing->id}/applicants/{$application->id}/messages", ['body' => 'Please come on Friday at 9.'])->assertSessionHas('status', __('work.messages.sent'));
    $this->post("/work/employer/listings/{$this->listing->id}/applicants/{$application->id}/messages", ['body' => 'Pay a registration fee of R200 before you start.'])->assertSessionHas('status', __('work.messages.held'));

    Staff::signIn($this, $this->person);
    $this->get("/work/applications/{$application->id}")->assertInertia(fn ($page) => $page->has('messages', 1));
    $message = DB::table('work_messages')->where('held', false)->value('id');
    $this->post("/work/messages/{$message}/report")->assertSessionHasNoErrors();
    expect(ModerationFlag::query()->where('subject_type', 'work_message')->count())->toBe(2);
});

it('confirms hires on both sides and checks retention at 30 and 90 days', function (): void {
    $application = applyAs($this, $this->person, $this->listing);
    Staff::signIn($this, $this->owner);
    $this->post("/work/employer/listings/{$this->listing->id}/applicants/move", ['ids' => [$application->id], 'stage' => 'hired']);
    expect(PlatformEventRecord::query()->where('name', 'work.hire.confirmed')->exists())->toBeFalse();

    Staff::signIn($this, $this->person);
    $this->post("/work/applications/{$application->id}/hire", ['started' => true])->assertSessionHasNoErrors();
    expect(PlatformEventRecord::query()->where('name', 'work.hire.confirmed')->exists())->toBeTrue()
        ->and(DB::table('work_retention_checks')->where('application_id', $application->id)->count())->toBe(2);

    $this->travel(31)->days();
    app(Hiring::class)->housekeeping();
    $check = DB::table('work_retention_checks')->whereNotNull('sent_at')->sole();
    Staff::signIn($this, $this->person);
    $this->post("/work/retention/{$check->id}", ['still_working' => true])->assertSessionHasNoErrors();
    expect(PlatformEventRecord::query()->where('name', 'work.retention.checked')->exists())->toBeTrue();
});

it('never ghosts: open applicants hear an outcome 7 days after the advert closes', function (): void {
    $application = applyAs($this, $this->person, $this->listing);
    app(Listings::class)->close($this->listing, 'filled', $this->owner);

    app(Hiring::class)->housekeeping();
    expect($application->refresh()->stage)->toBe('new');

    $this->travel(8)->days();
    app(Hiring::class)->housekeeping();
    expect($application->refresh()->stage)->toBe('unsuccessful')
        ->and(Update::query()->where('user_id', $this->person->id)->where('title', 'Update on your application: Cashier')->exists())->toBeTrue();
});

it('tells people with saved jobs once that applying is open', function (): void {
    DB::table('work_saved_jobs')->insert(['user_id' => $this->person->id, 'listing_id' => $this->listing->id, 'created_at' => now()]);

    app(Hiring::class)->housekeeping();
    app(Hiring::class)->housekeeping();

    expect(Update::query()->where('user_id', $this->person->id)->where('title', 'You can now apply for jobs you saved')->count())->toBe(1);
});

it('anonymises applications 12 months after the advert closed', function (): void {
    $application = applyAs($this, $this->person, $this->listing);
    app(Listings::class)->close($this->listing, 'closed', $this->owner);

    $this->travel(13)->months();
    app(Hiring::class)->housekeeping();

    $application->refresh();
    expect($application->user_id)->toBeNull()->and($application->answers)->toBeNull()->and($application->anonymised_at)->not->toBeNull();
});

it('lets people withdraw', function (): void {
    $application = applyAs($this, $this->person, $this->listing);

    $this->post("/work/applications/{$application->id}/withdraw")->assertSessionHasNoErrors();

    expect($application->refresh()->stage)->toBe('withdrawn');
});

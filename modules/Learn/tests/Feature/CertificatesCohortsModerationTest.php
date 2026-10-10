<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Modules\Core\Access\Scope;
use Modules\Core\Documents\Generation\FakePdfRenderer;
use Modules\Core\Documents\Models\Document;
use Modules\Core\Identity\Models\User;
use Modules\Core\Identity\Services\ConsentService;
use Modules\Core\Platform\Models\Update;
use Modules\Core\Structure\Models\Organisation;
use Modules\Core\Tests\Structure;
use Modules\HubOps\Models\HubEvent;
use Modules\HubOps\Services\EventBook;
use Modules\HubOps\Tests\Staff;
use Modules\Learn\Models\Accreditation;
use Modules\Learn\Models\Assignment;
use Modules\Learn\Models\Certificate;
use Modules\Learn\Models\Cohort;
use Modules\Learn\Models\Course;
use Modules\Learn\Models\CourseModule;
use Modules\Learn\Models\Enrolment;
use Modules\Learn\Models\Lesson;
use Modules\Learn\Models\Submission;
use Modules\Learn\Services\CourseWorkflow;
use Modules\Learn\Services\Learning;
use Modules\Work\Services\CvComposer;
use Tests\TestCase;

beforeEach(function (): void {
    Structure::seed($this);
    Storage::fake('documents');
    $this->provider = Organisation::query()->create(['type' => 'training_provider', 'name' => 'Skills Co', 'verification_status' => 'verified']);
    DB::table('learn_provider_settings')->insert(['organisation_id' => $this->provider->id, 'signatory_name' => 'Dr H. Moolman', 'signatory_title' => 'Director']);
    $this->admin = Structure::personWith('provider_admin', Scope::organisation($this->provider));
    $this->assessor = Structure::personWith('assessor_moderator', Scope::organisation($this->provider));
    $this->moderator = Structure::personWith('assessor_moderator', Scope::organisation($this->provider));
    $this->learner = User::factory()->create(['first_name' => 'Thandi', 'last_name' => 'Mabasa']);
    app(ConsentService::class)->record($this->learner, ['platform' => true, 'learning_records' => true]);
});

function courseWithTask(TestCase $t, array $attrs = []): Course
{
    $course = Course::query()->create(['organisation_id' => $t->provider->id, 'slug' => 'c'.Str::random(5), 'title' => 'Email basics', 'summary' => 'x',
        'outcomes' => ['You will be able to send an email'], 'topic' => 'digital_skills', 'min_age' => 16, 'hours' => 3, ...$attrs]);
    $m = CourseModule::query()->create(['course_id' => $course->id, 'title' => 'M']);
    $task = Lesson::query()->create(['course_id' => $course->id, 'module_id' => $m->id, 'title' => 'Task', 'kind' => 'assignment']);
    Assignment::query()->create(['lesson_id' => $task->id, 'instructions' => 'Send an email.', 'rubric' => ['Subject'], 'evidence' => ['text']]);
    app(CourseWorkflow::class)->publish($course, $t->admin);

    return $course->refresh();
}

function completeTask(TestCase $t, Course $course, ?User $learner = null): Submission
{
    $learner ??= $t->learner;
    $enrolment = app(Learning::class)->enrol($learner, $course);
    $lessonId = array_key_first(app(Learning::class)->lessons($enrolment->load('version')));
    $submission = app(Learning::class)->submit($enrolment->load('course'), $lessonId, 'My answer', null, $learner);
    app(Learning::class)->assess($submission->load('enrolment.course', 'enrolment.version'), $t->assessor, true, ['Subject'], 'Well done.');

    return $submission->refresh();
}

it('issues a verifiable certificate of completion and shows it on the CV', function (): void {
    $course = courseWithTask($this);
    completeTask($this, $course);

    $certificate = Certificate::query()->sole();
    expect($certificate->kind)->toBe('completion')->and($certificate->document->type)->toBe('learn_certificate')
        ->and(end(FakePdfRenderer::$rendered))->toContain('Certificate of completion')->toContain('Thandi Mabasa')->toContain('Dr H. Moolman')
        ->and(Update::query()->where('user_id', $this->learner->id)->where('title', 'Your certificate is ready: Email basics')->exists())->toBeTrue();

    Staff::signIn($this, $this->learner);
    $this->get('/learn/certificates')->assertInertia(fn ($page) => $page->where('certificates.0.kind', 'completion'));
    $this->get("/verify/{$certificate->document->verification_code}")->assertInertia(fn ($page) => $page->where('result.status', 'valid'));

    expect(app(CvComposer::class)->content($this->learner)['certificates'])->toHaveCount(1);
    $this->post("/learn/certificates/{$certificate->id}/cv", ['show' => false]);
    expect(app(CvComposer::class)->content($this->learner)['certificates'])->toHaveCount(0);
});

it('issues a statement of results for accredited courses only after the ID is verified', function (): void {
    $acc = Accreditation::query()->create(['organisation_id' => $this->provider->id, 'body' => 'QCTO', 'number' => 'Q1', 'status' => 'verified']);
    $course = courseWithTask($this, ['accreditation_id' => $acc->id, 'nqf_level' => 3, 'credits' => 12]);
    completeTask($this, $course);

    expect(Certificate::query()->count())->toBe(0)
        ->and(Update::query()->where('user_id', $this->learner->id)->where('title', 'like', 'Verify your ID%')->exists())->toBeTrue();

    Document::query()->create(['user_id' => $this->learner->id, 'type' => 'id_document', 'disk' => 'documents', 'path' => 'id.pdf', 'original_name' => 'id.pdf',
        'mime_type' => 'application/pdf', 'size_bytes' => 10, 'sha256' => str_repeat('c', 64), 'status' => 'verified']);
    $this->artisan('kasi:learn:daily')->assertSuccessful();

    expect(Certificate::query()->sole()->kind)->toBe('statement')
        ->and(end(FakePdfRenderer::$rendered))->toContain('Statement of results')->toContain('NQF level 3')->toContain('not by KasiHub');
});

it('lets the provider revoke a certificate with a reason the learner sees', function (): void {
    completeTask($this, courseWithTask($this));
    $certificate = Certificate::query()->sole();

    Staff::signIn($this, $this->admin);
    $this->post("/learn/certificates/{$certificate->id}/revoke", ['reason' => 'Plagiarised assignment'])->assertSessionHasNoErrors();

    expect($certificate->refresh()->revoked_at)->not->toBeNull();
    $this->get("/verify/{$certificate->document->verification_code}")->assertInertia(fn ($page) => $page->where('result.status', 'revoked'));
});

it('samples moderation: a new assessor\'s first assessments, never by themselves', function (): void {
    completeTask($this, courseWithTask($this));
    $moderation = DB::table('learn_moderations')->sole();
    expect($moderation->reason)->toBe('new_assessor');

    Staff::signIn($this, $this->assessor);
    $this->post("/learn/moderation/{$moderation->id}", ['agree' => true, 'notes' => 'Looks fine to me'])->assertSessionHasErrors('moderation');

    Staff::signIn($this, $this->moderator);
    $this->get('/learn/moderation')->assertInertia(fn ($page) => $page->has('items', 1));
    $this->post("/learn/moderation/{$moderation->id}", ['agree' => true, 'notes' => 'Evidence supports the decision.'])->assertSessionHasNoErrors();
    expect(DB::table('learn_moderations')->value('status'))->toBe('agreed');
    $this->get('/learn/moderation/report')->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
});

it('withdraws the certificate when moderation overturns a competent decision, and re-issues after re-assessment', function (): void {
    $submission = completeTask($this, courseWithTask($this));
    $first = Certificate::query()->sole();

    Staff::signIn($this, $this->moderator);
    $this->post('/learn/moderation/'.DB::table('learn_moderations')->value('id'), ['agree' => false, 'notes' => 'The email has no subject line.']);

    expect($first->refresh()->revoked_at)->not->toBeNull()->and($submission->refresh()->status)->toBe('submitted')
        ->and(Enrolment::query()->sole()->completed_at)->toBeNull()
        ->and(Update::query()->where('user_id', $this->learner->id)->where('title', 'like', 'Your work is being looked at again%')->exists())->toBeTrue();

    Staff::signIn($this, $this->assessor);
    $this->post("/learn/assess/{$submission->id}", ['competent' => true, 'criteria' => ['Subject'], 'feedback' => 'Subject line confirmed in the screenshot.']);
    expect(Certificate::query()->whereNull('revoked_at')->count())->toBe(1);
});

it('runs a cohort at a hub: approval, joining by code, waiting list, sessions and attendance for blended courses', function (): void {
    $course = courseWithTask($this, ['delivery' => 'blended', 'attendance_percent' => 50]);
    $hub = Structure::hub('LP-GIY-TSU');
    $manager = Structure::personWith('hub_manager', Scope::hub($hub));

    Staff::signIn($this, $this->admin);
    $this->post('/learn/cohorts', ['course_id' => $course->id, 'name' => 'Giyani October', 'hub_id' => $hub->id, 'starts_on' => now()->toDateString(), 'ends_on' => now()->addMonth()->toDateString(), 'capacity' => 1])
        ->assertSessionHasNoErrors();
    $cohort = Cohort::query()->sole();
    expect($cohort->status)->toBe('pending_hub');

    Staff::signIn($this, $this->learner);
    $this->post('/learn/join', ['code' => $cohort->code])->assertSessionHasErrors('code'); // not approved yet

    Staff::signIn($this, $manager);
    $this->get('/learn/cohorts/approvals')->assertInertia(fn ($page) => $page->has('cohorts', 1));
    $this->post("/learn/cohorts/{$cohort->id}/decide", ['approve' => true]);

    Staff::signIn($this, $this->learner);
    $this->post('/learn/join', ['code' => strtolower($cohort->code)])->assertRedirect('/learn/my');
    $second = User::factory()->create();
    app(ConsentService::class)->record($second, ['platform' => true, 'learning_records' => true]);
    Staff::signIn($this, $second);
    $this->post('/learn/join', ['code' => $cohort->code]);
    expect(DB::table('learn_cohort_members')->where('user_id', $second->id)->value('status'))->toBe('waiting');

    Staff::signIn($this, $this->admin);
    $this->post("/learn/cohorts/{$cohort->id}/sessions", ['title' => 'Session 1', 'starts_at' => now('Africa/Johannesburg')->addDay()->format('Y-m-d\TH:i'), 'minutes' => 60])->assertSessionHasNoErrors();
    $eventId = DB::table('learn_cohort_sessions')->value('event_id');
    expect(DB::table('hub_event_registrations')->where('event_id', $eventId)->where('user_id', $this->learner->id)->exists())->toBeTrue();

    // The learner finishes the work but has not attended the session that has now taken place.
    completeTask($this, $course);
    $this->travel(2)->days();
    $enrolment = Enrolment::query()->where('user_id', $this->learner->id)->sole();
    app(Learning::class)->recalculate($enrolment->load('course', 'version'));
    expect($enrolment->refresh()->completed_at)->toBeNull();

    app(EventBook::class)->attend(HubEvent::query()->findOrFail($eventId), $this->learner, $manager);
    expect($enrolment->refresh()->completed_at)->not->toBeNull();

    Staff::signIn($this, $this->admin);
    $this->get("/learn/cohorts/{$cohort->id}")->assertInertia(fn ($page) => $page->where('members.0.attendance', 100)->where('sessions.0.attended', 1));
    $this->post("/learn/cohorts/{$cohort->id}/nudge", ['users' => [$this->learner->id], 'message' => 'See you on Friday!'])->assertSessionHasNoErrors();
});

it('keeps cohort dashboards to the provider and the hub team', function (): void {
    $course = courseWithTask($this);
    Staff::signIn($this, $this->admin);
    $this->post('/learn/cohorts', ['course_id' => $course->id, 'name' => 'Online', 'starts_on' => now()->toDateString(), 'ends_on' => now()->addMonth()->toDateString(), 'capacity' => 10]);
    $cohort = Cohort::query()->sole();
    expect($cohort->status)->toBe('open');

    Staff::signIn($this, User::factory()->create());
    $this->get("/learn/cohorts/{$cohort->id}")->assertNotFound();
});

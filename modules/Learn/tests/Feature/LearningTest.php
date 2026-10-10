<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Access\Scope;
use Modules\Core\Identity\Models\AuditLog;
use Modules\Core\Identity\Models\User;
use Modules\Core\Identity\Services\ConsentService;
use Modules\Core\Platform\Models\PlatformEventRecord;
use Modules\Core\Platform\Models\Update;
use Modules\Core\Structure\Models\Organisation;
use Modules\Core\Tests\Structure;
use Modules\HubOps\Tests\Staff;
use Modules\Learn\Models\Assignment;
use Modules\Learn\Models\Course;
use Modules\Learn\Models\CourseModule;
use Modules\Learn\Models\Enrolment;
use Modules\Learn\Models\Lesson;
use Modules\Learn\Models\Quiz;
use Modules\Learn\Models\Submission;
use Modules\Learn\Services\CourseChecks;
use Modules\Learn\Services\CourseWorkflow;
use Tests\TestCase;

beforeEach(function (): void {
    Structure::seed($this);
    Storage::fake('documents');
    $this->provider = Organisation::query()->create(['type' => 'training_provider', 'name' => 'Skills Co', 'verification_status' => 'verified']);
    $this->admin = Structure::personWith('provider_admin', Scope::organisation($this->provider));
    $this->assessor = Structure::personWith('assessor_moderator', Scope::organisation($this->provider));

    $this->course = Course::query()->create(['organisation_id' => $this->provider->id, 'slug' => 'email', 'title' => 'Email basics', 'summary' => 'x', 'outcomes' => ['y'],
        'topic' => 'digital_skills', 'min_age' => 16]);
    $module = CourseModule::query()->create(['course_id' => $this->course->id, 'title' => 'Module 1']);
    $p = static fn (string $t): array => ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $t]]]]];
    $this->read = Lesson::query()->create(['course_id' => $this->course->id, 'module_id' => $module->id, 'title' => 'Read', 'kind' => 'text', 'position' => 0, 'content' => $p('Open the app.')]);
    $this->practice = Lesson::query()->create(['course_id' => $this->course->id, 'module_id' => $module->id, 'title' => 'Practice', 'kind' => 'quiz', 'position' => 1]);
    $this->graded = Lesson::query()->create(['course_id' => $this->course->id, 'module_id' => $module->id, 'title' => 'Test', 'kind' => 'quiz', 'position' => 2]);
    $this->task = Lesson::query()->create(['course_id' => $this->course->id, 'module_id' => $module->id, 'title' => 'Task', 'kind' => 'assignment', 'position' => 3]);

    $question = fn (string $prompt) => ['kind' => 'single', 'prompt' => $prompt, 'position' => 0, 'explanation' => 'Because.',
        'options' => [['text' => 'Right', 'correct' => true, 'feedback' => null], ['text' => 'Wrong', 'correct' => false, 'feedback' => null]]];
    Quiz::query()->create(['lesson_id' => $this->practice->id, 'graded' => false])->questions()->create($question('Practice question'));
    $graded = Quiz::query()->create(['lesson_id' => $this->graded->id, 'graded' => true, 'pass_mark' => 50, 'max_attempts' => 2]);
    $graded->questions()->create($question('Q1'));
    $graded->questions()->create([...$question('Q2'), 'position' => 1]);
    Assignment::query()->create(['lesson_id' => $this->task->id, 'instructions' => 'Send an email to your facilitator.', 'rubric' => ['Has a subject', 'Polite greeting'], 'evidence' => ['text', 'photo']]);

    app(CourseWorkflow::class)->publish($this->course, $this->admin);
    $this->learner = User::factory()->create();
    app(ConsentService::class)->record($this->learner, ['platform' => true, 'learning_records' => true]);
});

function enrolled(TestCase $test): Enrolment
{
    Staff::signIn($test, $test->learner);
    $test->post('/learn/courses/email/enrol')->assertSessionHasNoErrors();

    return Enrolment::query()->sole();
}

it('enrols into the current version and needs the learning records consent', function (): void {
    $other = User::factory()->create();
    app(ConsentService::class)->record($other, ['platform' => true, 'learning_records' => false]);
    Staff::signIn($this, $other);
    $this->post('/learn/courses/email/enrol')->assertSessionHasErrors('consent');
    $this->post('/learn/courses/email/enrol', ['consent' => true])->assertSessionHasNoErrors();

    $enrolment = Enrolment::query()->sole();
    expect($enrolment->version_id)->toBe($this->course->refresh()->current_version_id)
        ->and(PlatformEventRecord::query()->where('name', 'learn.enrolled')->exists())->toBeTrue();
});

it('keeps 16-17-year-olds to 16+ courses', function (): void {
    $this->course->forceFill(['min_age' => 18])->save();
    $minor = User::factory()->create(['age_band' => 'minor', 'date_of_birth' => now()->subYears(17)]);
    app(ConsentService::class)->record($minor, ['platform' => true, 'learning_records' => true]);
    Staff::signIn($this, $minor);

    $this->post('/learn/courses/email/enrol')->assertSessionHasErrors('enrol');
});

it('never sends graded quiz answers to the phone, but marks them on the server', function (): void {
    $enrolment = enrolled($this);

    $this->get("/learn/my/{$enrolment->id}/lessons/{$this->graded->id}")->assertInertia(fn ($page) => $page
        ->where('lesson.quiz.questions.0.options.0', ['text' => 'Right'])->missing('lesson.quiz.questions.0.explanation'));
    $this->get("/learn/my/{$enrolment->id}/lessons/{$this->practice->id}")->assertInertia(fn ($page) => $page->where('lesson.quiz.questions.0.options.0.correct', true));
    $offline = collect($this->getJson("/learn/my/{$enrolment->id}/offline")->json('modules.0.lessons'))->keyBy('id');
    expect($offline[$this->graded->id]['quiz']['questions'][0]['options'][0])->toBe(['text' => 'Right']) // graded: no answers
        ->and($offline[$this->practice->id]['quiz']['questions'][0]['options'][0]['correct'])->toBeTrue(); // practice: marked offline

    $q = DB::table('learn_questions')->where('quiz_id', Quiz::query()->where('lesson_id', $this->graded->id)->value('id'))->orderBy('position')->pluck('id');
    $this->postJson("/learn/my/{$enrolment->id}/quiz/{$this->graded->id}", ['answers' => [$q[0] => [1], $q[1] => [1]]])->assertJson(['ok' => true, 'score' => 0, 'passed' => false, 'attemptsLeft' => 1]);
    $this->postJson("/learn/my/{$enrolment->id}/quiz/{$this->graded->id}", ['answers' => [$q[0] => [0], $q[1] => [1]]])->assertJson(['ok' => true, 'score' => 50, 'passed' => true]);
    expect(PlatformEventRecord::query()->where('name', 'learn.quiz.passed')->exists())->toBeTrue();
});

it('makes people wait 24 hours after using all attempts', function (): void {
    $enrolment = enrolled($this);
    foreach ([1, 2] as $_) {
        $this->postJson("/learn/my/{$enrolment->id}/quiz/{$this->graded->id}", ['answers' => []]);
    }
    $this->postJson("/learn/my/{$enrolment->id}/quiz/{$this->graded->id}", ['answers' => []])->assertStatus(422);

    $this->travel(25)->hours();
    Staff::signIn($this, $this->learner);
    $this->postJson("/learn/my/{$enrolment->id}/quiz/{$this->graded->id}", ['answers' => []])->assertJson(['ok' => true]);
});

it('records progress idempotently, including offline batches, and cannot complete graded work by itself', function (): void {
    $enrolment = enrolled($this);
    $events = [
        ['lesson' => $this->read->id, 'type' => 'completed'],
        ['lesson' => $this->read->id, 'type' => 'completed'],
        ['lesson' => $this->practice->id, 'type' => 'practice', 'value' => 80],
        ['lesson' => $this->practice->id, 'type' => 'practice', 'value' => 40],
        ['lesson' => $this->graded->id, 'type' => 'completed'],
        ['lesson' => 'NOTINTHISVERSION0000000000', 'type' => 'completed'],
    ];

    $this->postJson("/learn/my/{$enrolment->id}/progress", ['events' => $events])->assertJson(['ok' => true]);
    $this->postJson("/learn/my/{$enrolment->id}/progress", ['events' => $events])->assertJson(['ok' => true]); // synced twice

    expect(DB::table('learn_progress')->where('enrolment_id', $enrolment->id)->whereNotNull('completed_at')->pluck('lesson_id')->sort()->values()->all())
        ->toEqualCanonicalizing([$this->read->id, $this->practice->id])
        ->and((int) DB::table('learn_progress')->where('lesson_id', $this->practice->id)->value('practice_score'))->toBe(80)
        ->and($enrolment->refresh()->progress)->toBe(33); // 1 of 3 required (practice quiz is optional)
});

it('takes assignments to an assessor, with resubmission, and completes the course', function (): void {
    $enrolment = enrolled($this);
    $this->postJson("/learn/my/{$enrolment->id}/progress", ['events' => [['lesson' => $this->read->id, 'type' => 'completed']]]);
    $q = DB::table('learn_questions')->where('quiz_id', Quiz::query()->where('lesson_id', $this->graded->id)->value('id'))->orderBy('position')->pluck('id');
    $this->postJson("/learn/my/{$enrolment->id}/quiz/{$this->graded->id}", ['answers' => [$q[0] => [0], $q[1] => [0]]]);

    $this->post("/learn/my/{$enrolment->id}/assignment/{$this->task->id}", ['text' => 'Dear Rhulani, please find my CV.', 'file' => UploadedFile::fake()->image('email.jpg')])->assertSessionHasNoErrors();
    $this->post("/learn/my/{$enrolment->id}/assignment/{$this->task->id}", ['text' => 'again'])->assertSessionHasErrors('submission'); // waiting
    expect(Update::query()->where('user_id', $this->assessor->id)->exists())->toBeTrue();

    Staff::signIn($this, $this->assessor);
    $submission = Submission::query()->sole();
    $this->get('/learn/assess')->assertInertia(fn ($page) => $page->has('waiting', 1));
    $this->post("/learn/assess/{$submission->id}", ['competent' => false, 'criteria' => ['Has a subject'], 'feedback' => 'Add a polite greeting.'])->assertRedirect('/learn/assess');

    Staff::signIn($this, $this->learner);
    $this->post("/learn/my/{$enrolment->id}/assignment/{$this->task->id}", ['text' => 'Good morning Rhulani, please find my CV.'])->assertSessionHasNoErrors();
    Staff::signIn($this, $this->assessor);
    $second = Submission::query()->where('attempt', 2)->sole();
    $this->post("/learn/assess/{$second->id}", ['competent' => true, 'criteria' => ['Has a subject', 'Polite greeting'], 'feedback' => 'Well done.']);

    $enrolment->refresh();
    expect($enrolment->status)->toBe('completed')->and($enrolment->progress)->toBe(100)
        ->and(PlatformEventRecord::query()->where('name', 'learn.course.completed')->exists())->toBeTrue()
        ->and(AuditLog::query()->where('event', 'learn.assessed')->count())->toBe(2);
});

it('keeps assessors to their own provider', function (): void {
    $enrolment = enrolled($this);
    $this->post("/learn/my/{$enrolment->id}/assignment/{$this->task->id}", ['text' => 'My answer']);
    $other = Organisation::query()->create(['type' => 'training_provider', 'name' => 'Other', 'verification_status' => 'verified']);
    Staff::signIn($this, Structure::personWith('assessor_moderator', Scope::organisation($other)));

    $this->get('/learn/assess/'.Submission::query()->value('id'))->assertNotFound();
    Staff::signIn($this, User::factory()->create());
    $this->get('/learn/assess')->assertForbidden();
    $this->get("/learn/my/{$enrolment->id}")->assertNotFound();
});

it('keeps learners on their version and lets them switch, keeping progress', function (): void {
    $enrolment = enrolled($this);
    $this->postJson("/learn/my/{$enrolment->id}/progress", ['events' => [['lesson' => $this->read->id, 'type' => 'completed']]]);
    $old = $enrolment->version_id;

    $this->read->update(['title' => 'Read (updated)']);
    app(CourseWorkflow::class)->publish($this->course->refresh(), $this->admin);
    expect($enrolment->refresh()->version_id)->toBe($old)
        ->and(Update::query()->where('user_id', $this->learner->id)->where('title', 'like', 'An updated version%')->exists())->toBeTrue();

    $this->post("/learn/my/{$enrolment->id}/switch");
    expect($enrolment->refresh()->version_id)->not->toBe($old)->and(DB::table('learn_progress')->where('enrolment_id', $enrolment->id)->whereNotNull('completed_at')->count())->toBe(1);
});

it('tells people who saved a course once that they can enrol', function (): void {
    DB::table('learn_saved_courses')->insert(['user_id' => $this->learner->id, 'course_id' => $this->course->id, 'created_at' => now()]);

    $this->artisan('kasi:learn:daily')->assertSuccessful();
    $this->artisan('kasi:learn:daily')->assertSuccessful();

    expect(Update::query()->where('user_id', $this->learner->id)->where('title', 'like', 'You can now enrol%')->count())->toBe(1);
});

it('shows continue learning on the hub home', function (): void {
    $enrolment = enrolled($this);
    $this->get("/learn/my/{$enrolment->id}/lessons/{$this->read->id}");

    $this->get('/home')->assertInertia(fn ($page) => $page->where('steps', fn ($steps) => (collect($steps)->firstWhere('key', 'learn_continue')['href'] ?? null) === "/learn/my/{$enrolment->id}/lessons/{$this->read->id}"));
});

it('blocks submitting a course with an empty quiz or assignment', function (): void {
    $c = Course::query()->create(['organisation_id' => $this->provider->id, 'slug' => 'q', 'title' => 'Q', 'summary' => 'x', 'outcomes' => ['y'], 'topic' => 'other']);
    $m = CourseModule::query()->create(['course_id' => $c->id, 'title' => 'M']);
    Lesson::query()->create(['course_id' => $c->id, 'module_id' => $m->id, 'title' => 'Empty quiz', 'kind' => 'quiz']);

    expect(collect(app(CourseChecks::class)->problems($c))->pluck('code'))->toContain('no_questions');
});

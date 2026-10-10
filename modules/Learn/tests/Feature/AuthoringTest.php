<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Admin\Tests\Console;
use Modules\Core\Access\Scope;
use Modules\Core\Ai\Drivers\FakeAiDriver;
use Modules\Core\Identity\Models\User;
use Modules\Core\Platform\Models\Update;
use Modules\Core\Structure\Models\Organisation;
use Modules\Core\Tests\Structure;
use Modules\HubOps\Tests\Staff;
use Modules\Learn\Models\Accreditation;
use Modules\Learn\Models\Course;
use Modules\Learn\Models\CourseModule;
use Modules\Learn\Models\CourseVersion;
use Modules\Learn\Models\Lesson;
use Modules\Learn\Models\Media;
use Modules\Learn\Services\CourseWorkflow;

beforeEach(function (): void {
    Structure::seed($this);
    Storage::fake('learn');
    Storage::fake('documents');
    $this->provider = Organisation::query()->create(['type' => 'training_provider', 'name' => 'Skills Co', 'verification_status' => 'verified']);
    $this->admin = Structure::personWith('provider_admin', Scope::organisation($this->provider));
    $this->author = Structure::personWith('course_author', Scope::organisation($this->provider));
});

function course(array $overrides = []): array
{
    return ['title' => 'Email basics', 'summary' => 'Send and read email on your phone.', 'outcomes' => ['You will be able to send an email'],
        'topic' => 'digital_skills', 'level' => 'beginner', 'language' => 'English', 'min_age' => 18, 'delivery' => 'self_paced', 'licence' => 'cc_by', ...$overrides];
}

function textLesson(Course $c, string $title = 'Your first email'): Lesson
{
    $moduleId = $c->modules()->value('id') ?? CourseModule::query()->create(['course_id' => $c->id, 'title' => 'Module 1'])->id;

    return Lesson::query()->create(['course_id' => $c->id, 'module_id' => $moduleId, 'title' => $title, 'kind' => 'text', 'position' => 0,
        'content' => ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Open the email app and tap compose.']]]]]]);
}

it('lets authors create a course with a first module', function (): void {
    Staff::signIn($this, $this->author);
    $this->post('/learn/author/courses', course())->assertSessionHasNoErrors();

    $c = Course::query()->sole();
    expect($c->status)->toBe('draft')->and($c->modules()->count())->toBe(1)->and($c->organisation_id)->toBe($this->provider->id);
    $this->get("/learn/author/courses/{$c->id}")->assertInertia(fn ($page) => $page->component('Learn/Author/Course')->where('problems.0.code', 'no_lessons'));
});

it('keeps other providers and learners out of authoring', function (): void {
    Staff::signIn($this, $this->author);
    $this->post('/learn/author/courses', course());
    $c = Course::query()->sole();

    $other = Organisation::query()->create(['type' => 'training_provider', 'name' => 'Other', 'verification_status' => 'verified']);
    Staff::signIn($this, Structure::personWith('course_author', Scope::organisation($other)));
    $this->get("/learn/author/courses/{$c->id}")->assertNotFound();
    Staff::signIn($this, User::factory()->create());
    $this->post('/learn/author/courses', course())->assertForbidden();
});

it('blocks submission until accessibility problems are fixed', function (): void {
    Staff::signIn($this, $this->author);
    $this->post('/learn/author/courses', course());
    $c = Course::query()->sole();
    $video = Lesson::query()->create(['course_id' => $c->id, 'module_id' => $c->modules()->value('id'), 'title' => 'Watch', 'kind' => 'video', 'position' => 1]);

    $this->post("/learn/author/courses/{$c->id}/submit")->assertSessionHasErrors('course');
    $this->get("/learn/author/courses/{$c->id}")->assertInertia(fn ($page) => $page->where('problems', fn ($p) => collect($p)->pluck('code')->contains('transcript')));

    $video->delete();
    textLesson($c);
    $this->post("/learn/author/courses/{$c->id}/submit")->assertSessionHasNoErrors();
    expect($c->refresh()->status)->toBe('submitted')
        ->and(Update::query()->where('user_id', $this->admin->id)->where('title', 'Course waiting for your approval: Email basics')->exists())->toBeTrue();
});

it('sends a provider\'s first course to KasiHub review, then publishes a frozen version', function (): void {
    Staff::signIn($this, $this->author);
    $this->post('/learn/author/courses', course());
    $c = Course::query()->sole();
    textLesson($c);
    $this->post("/learn/author/courses/{$c->id}/submit");

    Staff::signIn($this, $this->author);
    $this->post("/learn/author/courses/{$c->id}/decide", ['decision' => 'approve'])->assertForbidden(); // only admins approve

    Staff::signIn($this, $this->admin);
    $this->post("/learn/author/courses/{$c->id}/decide", ['decision' => 'approve'])->assertSessionHasNoErrors();
    expect($c->refresh()->status)->toBe('in_review');

    Console::as($this, 'content_reviewer');
    $this->get('/learn/review')->assertInertia(fn ($page) => $page->has('courses', 1));
    $this->post("/learn/review/courses/{$c->id}", ['decision' => 'approve'])->assertSessionHasNoErrors();

    $c->refresh();
    expect($c->status)->toBe('published')->and($c->currentVersion->number)->toBe(1)
        ->and($c->currentVersion->snapshot['modules'][0]['lessons'][0]['html'])->toContain('Open the email app');

    // Editing a published course prepares version 2; learners keep version 1 until it is published.
    Staff::signIn($this, $this->author);
    $lesson = Lesson::query()->sole();
    $this->put("/learn/author/courses/{$c->id}/lessons/{$lesson->id}", ['title' => 'Your first email', 'content' => ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'New text']]]]]]);
    expect($c->refresh()->changed_since_publish)->toBeTrue()->and($c->currentVersion->snapshot['modules'][0]['lessons'][0]['html'])->toContain('Open the email app');
});

it('publishes later courses after provider approval, unless they are for 16-17-year-olds', function (): void {
    $first = Course::query()->create([...course(), 'organisation_id' => $this->provider->id, 'slug' => 'first']);
    CourseVersion::query()->create(['course_id' => $first->id, 'number' => 1, 'snapshot' => [], 'published_at' => now()]);
    $workflow = app(CourseWorkflow::class);

    $adults = Course::query()->create([...course(), 'organisation_id' => $this->provider->id, 'slug' => 'adults']);
    $teens = Course::query()->create([...course(['min_age' => 16]), 'organisation_id' => $this->provider->id, 'slug' => 'teens']);

    expect($workflow->needsKasiHubReview($adults))->toBeFalse()->and($workflow->needsKasiHubReview($teens))->toBeTrue();
});

it('sends courses back with comments', function (): void {
    Staff::signIn($this, $this->author);
    $this->post('/learn/author/courses', course());
    $c = Course::query()->sole();
    textLesson($c);
    $this->post("/learn/author/courses/{$c->id}/submit");

    Staff::signIn($this, $this->admin);
    $this->post("/learn/author/courses/{$c->id}/decide", ['decision' => 'changes', 'comment' => 'Add a lesson on attachments.']);

    expect($c->refresh()->status)->toBe('draft')->and($c->status_reason)->toBe('Add a lesson on attachments.')
        ->and(Update::query()->where('user_id', $this->author->id)->where('title', 'Changes asked for: Email basics')->exists())->toBeTrue();
});

it('uploads pictures re-encoded as WebP and queues videos for low-data versions', function (): void {
    Staff::signIn($this, $this->author);
    $this->post('/learn/author/courses', course());
    $c = Course::query()->sole();

    $this->post("/learn/author/courses/{$c->id}/media", ['kind' => 'image', 'file' => UploadedFile::fake()->image('photo.jpg', 2400, 1600), 'alt' => 'A phone'])
        ->assertJson(['ok' => true]);
    $image = Media::query()->where('kind', 'image')->sole();
    expect($image->mime_type)->toBe('image/webp')->and(getimagesizefromstring(Storage::disk('learn')->get($image->original_path))[0])->toBe(1280);

    $this->post("/learn/author/courses/{$c->id}/media", ['kind' => 'video', 'file' => UploadedFile::fake()->createWithContent('clip.mp4', str_repeat('v', 4000))->mimeType('video/mp4')])
        ->assertJson(['ok' => true]);
    $video = Media::query()->where('kind', 'video')->sole();
    expect($video->status)->toBe('ready')->and(array_keys($video->renditions))->toEqualCanonicalizing(['low', 'standard', 'audio']); // fake converter ran; MySQL re-orders JSON keys

    $this->post("/learn/author/courses/{$c->id}/media", ['kind' => 'image', 'file' => UploadedFile::fake()->createWithContent('x.php', '<?php echo 1;')])
        ->assertStatus(422);
});

it('drafts a lesson with AI help, marked as AI-drafted until edited', function (): void {
    FakeAiDriver::respondWith((string) json_encode(['blocks' => [
        ['type' => 'paragraph', 'text' => 'Email lets you send messages.'], ['type' => 'heading', 'text' => 'Key points'], ['type' => 'bullets', 'items' => ['Tap compose', 'Add a subject']],
    ]]));
    Staff::signIn($this, $this->author);
    $this->post('/learn/author/courses', course());
    $c = Course::query()->sole();

    $this->postJson("/learn/author/courses/{$c->id}/assist", ['action' => 'draft', 'title' => 'Email', 'text' => 'how to send an email with a subject'])
        ->assertJson(['ok' => true])->assertJsonPath('doc.content.2.type', 'bulletList');
});

it('shows published courses in the catalogue, by age, with public pages', function (): void {
    $adults = Course::query()->create([...course(['title' => 'Adults course']), 'organisation_id' => $this->provider->id, 'slug' => 'adults']);
    $teens = Course::query()->create([...course(['title' => 'Teens course', 'min_age' => 16]), 'organisation_id' => $this->provider->id, 'slug' => 'teens']);
    foreach ([$adults, $teens] as $c) {
        textLesson($c)->update(['preview' => true]);
        app(CourseWorkflow::class)->publish($c, $this->admin);
    }

    $this->get('/learn/courses/teens')->assertOk()->assertInertia(fn ($page) => $page->component('Learn/Catalogue/Public')->where('seo.jsonLd.0.@type', 'Course'));
    Staff::signIn($this, User::factory()->create(['age_band' => 'minor', 'date_of_birth' => now()->subYears(16)]));
    $this->get('/learn/courses')->assertInertia(fn ($page) => $page->has('courses', 1)->where('courses.0.title', 'Teens course'));
    $this->get('/learn/courses/adults')->assertNotFound();

    Staff::signIn($this, User::factory()->create());
    $this->get('/learn/courses')->assertInertia(fn ($page) => $page->has('courses', 2));
    $lesson = Lesson::query()->where('course_id', $adults->id)->value('id');
    $this->get("/learn/courses/adults/preview/{$lesson}")->assertOk();
    $this->post('/learn/courses/adults/save')->assertSessionHasNoErrors();
    $this->get('/learn/courses?saved=1')->assertInertia(fn ($page) => $page->has('courses', 1));
});

it('lets KasiHub reviewers unpublish with a reason the provider sees', function (): void {
    $c = Course::query()->create([...course(), 'organisation_id' => $this->provider->id, 'slug' => 'x']);
    textLesson($c);
    app(CourseWorkflow::class)->publish($c, $this->admin);

    Console::as($this, 'content_reviewer');
    $this->post("/learn/review/courses/{$c->id}", ['decision' => 'unpublish', 'comment' => 'Contains a misleading claim'])->assertSessionHasNoErrors();

    expect($c->refresh()->status)->toBe('unpublished')
        ->and(Update::query()->where('user_id', $this->admin->id)->where('title', 'Course taken out of the catalogue: Email basics')->exists())->toBeTrue();
    Staff::signIn($this, User::factory()->create());
    $this->get('/learn/courses/x')->assertNotFound();
});

it('only shows accreditation after KasiHub verifies it', function (): void {
    Staff::signIn($this, $this->admin);
    $this->post('/learn/provider/accreditations', ['body' => 'QCTO', 'number' => 'QCTO/SDP/1234', 'evidence' => UploadedFile::fake()->createWithContent('a.pdf', '%PDF-1.4 x')])
        ->assertSessionHasNoErrors();
    $accreditation = Accreditation::query()->sole();
    $c = Course::query()->create([...course(), 'organisation_id' => $this->provider->id, 'slug' => 'acc', 'accreditation_id' => $accreditation->id, 'nqf_level' => 3]);
    textLesson($c);
    app(CourseWorkflow::class)->publish($c, $this->admin);

    Staff::signIn($this, User::factory()->create());
    $this->get('/learn/courses/acc')->assertInertia(fn ($page) => $page->where('course.accredited', false)->where('course.nqfLevel', null));

    Console::as($this, 'content_reviewer');
    $this->post("/learn/review/accreditations/{$accreditation->id}", ['decision' => 'verified']);
    app(CourseWorkflow::class)->publish($c->refresh(), $this->admin);
    Staff::signIn($this, User::factory()->create());
    $this->get('/learn/courses/acc')->assertInertia(fn ($page) => $page->where('course.accredited', true)->where('course.accreditedBy', 'QCTO')->where('course.nqfLevel', 3));
});

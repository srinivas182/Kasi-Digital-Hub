<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Modules\Core\Ai\Drivers\FakeAiDriver;
use Modules\Core\Ai\Models\AiFeatureSetting;
use Modules\Core\Ai\Models\AiRequestLog;
use Modules\Core\Ai\Models\ModerationFlag;
use Modules\Core\Documents\Generation\GeneratedDocument;
use Modules\Core\Identity\Models\User;
use Modules\Core\Search\SearchService;
use Modules\Core\Tests\Structure;
use Modules\HubOps\Models\EventRegistration;
use Modules\HubOps\Models\HubEvent;
use Modules\HubOps\Services\EventBook;
use Modules\HubOps\Tests\Staff;

beforeEach(function (): void {
    Structure::seed($this);
    Storage::fake('documents');
    $this->travelTo(now()->setTimezone('Africa/Johannesburg')->setTime(10, 0)->utc());
    $this->hub = Structure::hub('LP-GIY-TSU');
});

it('writes an event description from staff notes, recorded against the hub', function (): void {
    $facilitator = Staff::as($this, 'hub_facilitator');
    FakeAiDriver::respondWith('{"description": "Join our CV workshop on Saturday. Bring your ID."}');

    $this->postJson('/hub-ops/events/write', ['type' => 'workshop', 'title' => 'CV workshop', 'notes' => 'cv workshop sat 9am bring ID, call 0724183390'])
        ->assertOk()->assertJson(['ok' => true, 'description' => 'Join our CV workshop on Saturday. Bring your ID.']);

    $log = AiRequestLog::query()->sole();
    expect($log->feature)->toBe('hubops.event_description')->and($log->actor_id)->toBe($facilitator->id)->and($log->hub_id)->toBe($this->hub->id)
        ->and(FakeAiDriver::sent()[0]->user)->not->toContain('0724183390');
});

it('tells staff to write it themselves when AI is switched off', function (): void {
    Staff::as($this, 'hub_facilitator');
    AiFeatureSetting::query()->create(['feature' => 'hubops.event_description', 'enabled' => false]);

    $this->postJson('/hub-ops/events/write', ['type' => 'workshop', 'title' => 'CV workshop', 'notes' => 'cv workshop saturday morning'])
        ->assertOk()->assertJson(['ok' => false])->assertJsonPath('message', __('ai.fallback.disabled'));
    $this->get('/hub-ops/events/create')->assertInertia(fn ($page) => $page->where('aiEnabled', false));
});

it('sends doubtful public event text to the review queue', function (): void {
    $manager = Structure::personWith('hub_manager');
    app(EventBook::class)->schedule($this->hub, [
        'type' => 'job_day', 'title' => 'Mine jobs', 'description' => 'Pay a R200 registration fee at the door to get a job.',
        'starts_at' => now()->addDays(2), 'ends_at' => now()->addDays(2)->addHours(2), 'capacity' => 50, 'audience' => 'public',
    ], $manager);

    expect(ModerationFlag::query()->where('subject_type', 'hub_event')->value('reasons'))->toContain('Asks applicants to pay');
});

it('makes upcoming events searchable for the right people', function (): void {
    $manager = Structure::personWith('hub_manager');
    app(EventBook::class)->schedule($this->hub, ['type' => 'class', 'title' => 'Coding club', 'starts_at' => now()->addDays(2), 'ends_at' => now()->addDays(2)->addHours(2), 'capacity' => 10, 'audience' => 'members'], $manager);

    $adult = app(SearchService::class)->search('Coding club', User::factory()->create());
    $minor = app(SearchService::class)->search('Coding club', User::factory()->create(['age_band' => 'minor', 'date_of_birth' => now()->subYears(17)]));

    expect($adult)->toHaveCount(1)->and($adult[0]['url'])->toStartWith('/events/')->and($minor)->toBe([]);
});

it('issues one verifiable certificate to people who attended', function (): void {
    $event = HubEvent::query()->create([
        'hub_id' => $this->hub->id, 'type' => 'workshop', 'title' => 'CV workshop', 'starts_at' => now()->subHours(3), 'ends_at' => now()->subHour(),
        'capacity' => 10, 'audience' => 'public', 'status' => 'scheduled',
    ]);
    $person = Staff::signIn($this, User::factory()->create(['first_name' => 'Lwazi', 'last_name' => 'Chauke']));

    $this->get("/events/{$event->id}/certificate")->assertNotFound(); // did not attend

    $registration = EventRegistration::query()->create(['event_id' => $event->id, 'user_id' => $person->id, 'status' => 'registered', 'attended_at' => now()->subHours(2)]);
    $this->get("/events/{$event->id}/certificate")->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $this->get("/events/{$event->id}/certificate")->assertOk();

    $doc = GeneratedDocument::query()->sole();
    expect($doc->subject_id)->toBe($event->id)->and($doc->type)->toBe('attendance_certificate');
    $this->get("/verify/{$doc->verification_code}")->assertInertia(fn ($page) => $page->where('result.status', 'valid')->where('result.holder', 'Lwazi Chauke'));
});

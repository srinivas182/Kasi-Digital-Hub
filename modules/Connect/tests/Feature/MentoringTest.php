<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Modules\Connect\Models\Mentor;
use Modules\Connect\Models\Mentorship;
use Modules\Connect\Services\Mentoring;
use Modules\Core\Access\Scope;
use Modules\Core\Documents\Generation\FakePdfRenderer;
use Modules\Core\Documents\Models\Document;
use Modules\Core\Identity\Drivers\LogSmsSender;
use Modules\Core\Identity\Models\AuditLog;
use Modules\Core\Identity\Models\User;
use Modules\Core\Platform\Models\PlatformEventRecord;
use Modules\Core\Platform\Models\Update;
use Modules\Core\Tests\Structure;
use Modules\HubOps\Tests\Staff;
use Tests\TestCase;

beforeEach(function (): void {
    Structure::seed($this);
    $this->hub = Structure::hub('LP-GIY-TSU');
    $this->coordinator = Structure::personWith('mentor_coordinator', Scope::national());
    $this->officer = Structure::personWith('safeguarding_officer', Scope::national());
    $this->mentorUser = User::factory()->create(['first_name' => 'Palesa']);
    $this->mentee = User::factory()->create(['first_name' => 'Nomsa', 'home_hub_id' => $this->hub->id]);
});

function applyAsMentor(TestCase $t, array $o = []): Mentor
{
    Staff::signIn($t, $t->mentorUser);
    $t->post('/connect/mentor', ['headline' => 'Catering business owner', 'expertise' => ['catering', 'pricing'], 'goals' => ['business', 'job'], 'languages' => ['English'],
        'formats' => ['phone', 'hub'], 'hub_id' => $t->hub->id, 'capacity' => 1, ...$o])->assertSessionHasNoErrors();

    return Mentor::query()->findOrFail($t->mentorUser->id);
}

function approved(TestCase $t, bool $police = false): Mentor
{
    $mentor = applyAsMentor($t);
    Document::query()->create(['user_id' => $t->mentorUser->id, 'type' => 'id_document', 'disk' => 'documents', 'path' => 'id.pdf', 'original_name' => 'id.pdf',
        'mime_type' => 'application/pdf', 'size_bytes' => 1, 'sha256' => str_repeat('d', 64), 'status' => 'verified']);
    Staff::signIn($t, $t->coordinator);
    foreach ([...Mentor::CHECKS, ...($police ? ['police_clearance'] : [])] as $check) {
        $t->post("/connect/coordinator/{$mentor->user_id}/check", ['check' => $check, 'done' => true, 'note' => 'ok'])->assertSessionHasNoErrors();
    }
    $t->post("/connect/coordinator/{$mentor->user_id}/decide", ['action' => 'approve'])->assertSessionHasNoErrors();

    return $mentor->refresh();
}

function activeMentorship(TestCase $t, ?User $mentee = null): Mentorship
{
    $mentee ??= $t->mentee;
    $mentor = Mentor::query()->with('user')->findOrFail($t->mentorUser->id);
    $id = app(Mentoring::class)->request($mentee, $mentor, 'business', 'Help with pricing');
    $m = app(Mentoring::class)->answer($id, $t->mentorUser, true);
    app(Mentoring::class)->propose($m, $t->mentorUser, ['goals' => ['Price properly'], 'per_month' => 2, 'months' => 3, 'format' => $mentee->isMinor() ? 'hub' : 'phone']);
    app(Mentoring::class)->agree($m->refresh(), $mentee);

    return $m->refresh();
}

it('vets mentors: every check before approval, the ID check only once the ID is verified', function (): void {
    $mentor = applyAsMentor($this);
    Staff::signIn($this, $this->coordinator);
    $this->post("/connect/coordinator/{$mentor->user_id}/check", ['check' => 'id_verified', 'done' => true])->assertSessionHasErrors('mentor');
    $this->post("/connect/coordinator/{$mentor->user_id}/decide", ['action' => 'approve'])->assertSessionHasErrors('mentor');

    $mentor = approved($this);
    expect($mentor->status)->toBe('approved')->and($mentor->minors_ok)->toBeFalse()
        ->and(PlatformEventRecord::query()->where('name', 'connect.mentor.approved')->exists())->toBeTrue();

    Staff::signIn($this, User::factory()->create());
    $this->get('/connect/coordinator')->assertForbidden();
    $this->get('/connect/safeguarding')->assertForbidden();
});

it('suggests mentors with reasons and lets the young person choose', function (): void {
    approved($this);
    Staff::signIn($this, $this->mentee);
    $this->get('/connect/find?goal=business&interests=catering%20and%20pricing&languages=English&format=phone')
        ->assertInertia(fn ($page) => $page->has('suggestions', 1)->where('suggestions.0.reasons', fn ($r) => collect($r)->pluck('key')->contains('interests')));
    $this->get('/connect/find?goal=confidence')->assertInertia(fn ($page) => $page->has('suggestions', 0)); // not a goal they help with

    $this->post("/connect/mentors/{$this->mentorUser->id}/request", ['goal' => 'business', 'message' => 'I sell food'])->assertSessionHasNoErrors();
    expect(Update::query()->where('user_id', $this->mentorUser->id)->exists())->toBeTrue();
});

it('agrees a mentorship, records sessions with private notes kept private, and counts hours', function (): void {
    approved($this);
    $m = activeMentorship($this);
    expect($m->status)->toBe('active')->and(PlatformEventRecord::query()->where('name', 'connect.mentorship.started')->exists())->toBeTrue();

    $session = app(Mentoring::class)->schedule($m, $this->mentorUser, now()->addDay()->toImmutable(), 60, 'phone', null, null, null);
    app(Mentoring::class)->record($session, $this->mentorUser, 'We priced braids.', ['List costs'], 'She seemed tired - check in next time.');
    expect(Mentor::query()->find($this->mentorUser->id)->minutes_mentored)->toBe(60);

    Staff::signIn($this, $this->mentee);
    $this->get("/connect/mentorships/{$m->id}")->assertInertia(fn ($page) => $page->where('sessions.0.sharedNotes', 'We priced braids.')->where('sessions.0.privateNotes', null));
    $this->post("/connect/sessions/{$session}/helpful", ['helpful' => true]);
    expect((bool) DB::table('connect_sessions')->value('helpful'))->toBeTrue();
});

it('blocks contact details in messages before two sessions, then reminds', function (): void {
    approved($this);
    $m = activeMentorship($this);
    Staff::signIn($this, $this->mentee);

    $this->post("/connect/mentorships/{$m->id}/messages", ['body' => 'WhatsApp me on 072 123 4567'])->assertSessionHasErrors('message');
    $this->post("/connect/mentorships/{$m->id}/messages", ['body' => 'Thank you for the session!'])->assertSessionHasNoErrors();
    expect(DB::table('connect_messages')->count())->toBe(1);

    foreach ([1, 2] as $_) {
        $s = app(Mentoring::class)->schedule($m, $this->mentorUser, now()->addDay()->toImmutable(), 30, 'phone', null, null, null);
        app(Mentoring::class)->record($s, $this->mentorUser, 'Notes', [], null);
    }
    $this->post("/connect/mentorships/{$m->id}/messages", ['body' => 'My email is nomsa@example.com'])->assertSessionHas('status', __('connect.messages.contact_reminder'));
});

it('keeps under-18s to hub mentoring with cleared mentors, no messages, and tells the guardian', function (): void {
    $minor = User::factory()->create(['age_band' => 'minor', 'date_of_birth' => now()->subYears(17), 'home_hub_id' => $this->hub->id]);
    $minor->guardianConsents()->create(['guardian_name' => 'Mrs Mabunda', 'guardian_phone' => '+27725550001', 'relationship' => 'mother', 'verified_at' => now()]);

    approved($this); // no police clearance
    Staff::signIn($this, $minor);
    $this->get('/connect/find?goal=business')->assertInertia(fn ($page) => $page->has('suggestions', 0));
    $this->post("/connect/mentors/{$this->mentorUser->id}/request", ['goal' => 'business'])->assertSessionHasErrors('request');

    Staff::signIn($this, $this->coordinator);
    $this->post("/connect/coordinator/{$this->mentorUser->id}/check", ['check' => 'police_clearance', 'done' => true]);
    $m = activeMentorship($this, $minor);

    expect(fn () => app(Mentoring::class)->schedule($m, $this->mentorUser, now()->addDay()->toImmutable(), 60, 'phone', null, null, null))->toThrow(DomainException::class)
        ->and(fn () => app(Mentoring::class)->message($m, $this->mentorUser, 'Hello'))->toThrow(DomainException::class);
    $session = app(Mentoring::class)->schedule($m, $this->mentorUser, now()->addDay()->toImmutable(), 60, 'hub', null, $this->hub->id, 'Room 1');
    expect(DB::table('connect_sessions')->where('id', $session)->value('event_id'))->not->toBeNull()
        ->and(LogSmsSender::lastMessage('+27725550001'))->toContain('Palesa');
    expect(AuditLog::query()->where('event', 'connect.guardian_informed')->exists())->toBeTrue();
});

it('pauses the mentorship at once on an urgent concern and lets officers pause the mentor everywhere', function (): void {
    approved($this);
    $m = activeMentorship($this);
    Staff::signIn($this, $this->mentee);
    $this->post('/connect/concerns', ['about' => $this->mentorUser->id, 'mentorship_id' => $m->id, 'kind' => 'inappropriate', 'urgent' => true, 'body' => 'He asked to meet alone at night.']);

    expect($m->refresh()->status)->toBe('paused')
        ->and(Update::query()->where('user_id', $this->officer->id)->where('title', 'URGENT safeguarding concern')->exists())->toBeTrue()
        ->and(Update::query()->where('user_id', $this->coordinator->id)->where('title', 'like', '%safeguarding%')->exists())->toBeFalse(); // only officers are alerted

    Staff::signIn($this, $this->officer);
    $concern = DB::table('connect_concerns')->value('id');
    $this->get('/connect/safeguarding')->assertInertia(fn ($page) => $page->where('concerns.0.urgent', true));
    $this->post("/connect/safeguarding/{$concern}", ['actions' => 'Called both; mentor paused pending review.', 'pause_mentor' => true]);
    expect(Mentor::query()->find($this->mentorUser->id)->status)->toBe('paused');

    Staff::signIn($this, $this->mentee);
    $this->get('/connect/find?goal=business')->assertInertia(fn ($page) => $page->has('suggestions', 0));
});

it('ends with goals met, renews once, and keeps mentorships private to the two people', function (): void {
    approved($this);
    $m = activeMentorship($this);
    app(Mentoring::class)->renew($m, $this->mentorUser);
    expect(fn () => app(Mentoring::class)->renew($m->refresh(), $this->mentorUser))->toThrow(DomainException::class);

    Staff::signIn($this, User::factory()->create());
    $this->get("/connect/mentorships/{$m->id}")->assertNotFound();

    Staff::signIn($this, $this->mentee);
    $this->post("/connect/mentorships/{$m->id}/end", ['reason' => 'Goals reached', 'goals_met' => [true], 'feedback' => 'Very helpful']);
    expect($m->refresh()->status)->toBe('ended')->and(PlatformEventRecord::query()->where('name', 'connect.mentorship.ended')->exists())->toBeTrue();
});

it('issues a verifiable certificate of mentoring hours', function (): void {
    approved($this);
    Mentor::query()->whereKey($this->mentorUser->id)->update(['minutes_mentored' => 300]);
    Staff::signIn($this, $this->mentorUser);
    $this->get('/connect/mentor/hours')->assertOk();
    expect(end(FakePdfRenderer::$rendered))->toContain('5 hours of mentoring');
});

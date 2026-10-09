<?php

declare(strict_types=1);

use Modules\Core\Identity\Models\AuditLog;
use Modules\Core\Identity\Models\User;
use Modules\Core\Tests\Structure;
use Modules\HubOps\Models\HubVisit;
use Modules\HubOps\Services\CheckInCodes;
use Modules\HubOps\Tests\Staff;

beforeEach(function (): void {
    Structure::seed($this);
    $this->travelTo(now()->setTimezone('Africa/Johannesburg')->setTime(10, 0, 5)->utc());
    $this->hub = Structure::hub('LP-GIY-TSU');
});

it('opens the door screen only with the secret link', function (): void {
    $token = app(CheckInCodes::class)->issueKioskToken($this->hub);

    $this->get("/kiosk/{$this->hub->slug}/{$token}")->assertOk()->assertInertia(fn ($page) => $page->component('HubOps/Door')->has('qr'));
    $this->get("/kiosk/{$this->hub->slug}/wrong-token")->assertNotFound();
    $this->getJson("/kiosk/{$this->hub->slug}/{$token}/code")->assertOk()->assertJsonStructure(['qr', 'url', 'secondsLeft'])->assertHeader('Cache-Control', 'no-store, private');
});

it('stops an old door link working when a new one is made', function (): void {
    $old = app(CheckInCodes::class)->issueKioskToken($this->hub);
    app(CheckInCodes::class)->issueKioskToken($this->hub->refresh());

    $this->get("/kiosk/{$this->hub->slug}/{$old}")->assertNotFound();
});

it('checks a signed-in person in by scanning the door code', function (): void {
    $person = Staff::signIn($this, User::factory()->create());
    $code = app(CheckInCodes::class)->current($this->hub);

    $this->get("/check-in/{$this->hub->slug}?c={$code}")->assertRedirect('/check-in');
    $this->get('/check-in')->assertInertia(fn ($page) => $page->component('HubOps/CheckIn/Confirm')->where('alreadyToday', false));
    $this->post('/check-in', ['purpose' => 'jobs'])->assertRedirect('/home');

    $visit = HubVisit::query()->sole();
    expect($visit->user_id)->toBe($person->id)->and($visit->method)->toBe('qr')->and($visit->purpose)->toBe('jobs');
});

it('keeps the scan while the person signs in', function (): void {
    $code = app(CheckInCodes::class)->current($this->hub);

    $this->get("/check-in/{$this->hub->slug}?c={$code}")->assertRedirect('/check-in');
    $this->get('/check-in')->assertRedirect('/login');

    $this->travel(10)->minutes();
    Staff::signIn($this, User::factory()->create());
    $this->get('/check-in')->assertInertia(fn ($page) => $page->component('HubOps/CheckIn/Confirm'));
});

it('accepts the code just before it changed, but not older codes', function (): void {
    $codes = app(CheckInCodes::class);
    $code = $codes->current($this->hub);

    $this->travel(CheckInCodes::WINDOW_SECONDS)->seconds();
    expect($codes->valid($this->hub, $code))->toBeTrue();

    $this->travel(CheckInCodes::WINDOW_SECONDS)->seconds();
    expect($codes->valid($this->hub, $code))->toBeFalse();
    $this->get("/check-in/{$this->hub->slug}?c={$code}")->assertInertia(fn ($page) => $page->component('HubOps/CheckIn/Expired'));
});

it('does not accept one hub\'s code at another hub', function (): void {
    $code = app(CheckInCodes::class)->current($this->hub);

    expect(app(CheckInCodes::class)->valid(Structure::hub('GP-JHB-SOW'), $code))->toBeFalse();
});

it('counts one visit per person per hub per day', function (): void {
    Staff::signIn($this, User::factory()->create());

    foreach (range(1, 2) as $i) {
        $this->get("/check-in/{$this->hub->slug}?c=".app(CheckInCodes::class)->current($this->hub));
        $this->post('/check-in', ['purpose' => 'learning']);
    }

    expect(HubVisit::query()->count())->toBe(1);
});

it('checks people in at the front desk, including walk-ins without an account', function (): void {
    $facilitator = Staff::as($this, 'hub_facilitator');
    $person = User::factory()->create(['first_name' => 'Ntsako', 'phone' => '+27724183390']);

    $this->get('/hub-ops/check-in?q=Ntsa')->assertInertia(fn ($page) => $page->where('results.0.phone', '072 *** 3390')->where('results.0.here', false));
    $this->post('/hub-ops/check-in', ['person_id' => $person->id, 'purpose' => 'printing'])->assertSessionHasNoErrors();
    $this->post('/hub-ops/check-in', ['purpose' => 'computer'])->assertSessionHasNoErrors();

    expect(HubVisit::query()->where('user_id', $person->id)->value('checked_in_by'))->toBe($facilitator->id)
        ->and(HubVisit::query()->whereNull('user_id')->value('method'))->toBe('walk_in');
});

it('only lets staff help people who are at the hub today, and audits what they do', function (): void {
    $facilitator = Staff::as($this, 'hub_facilitator');
    $person = User::factory()->create();

    $this->post("/hub-ops/assist/{$person->id}")->assertSessionHasErrors('assist');

    $this->post('/hub-ops/check-in', ['person_id' => $person->id, 'purpose' => 'jobs']);
    $this->post("/hub-ops/assist/{$person->id}")->assertRedirect('/hub-ops/assist');
    $this->get('/hub-ops/assist')->assertInertia(fn ($page) => $page->where('person.id', $person->id)->where('assist.name', $person->fullName()));

    $this->put('/hub-ops/assist/profile', ['place_name' => 'Ka-Dzumeri'])->assertSessionHasErrors('present');
    $this->put('/hub-ops/assist/profile', ['place_name' => 'Ka-Dzumeri', 'present' => true])->assertRedirect()->assertSessionHasNoErrors();

    expect($person->refresh()->place_name)->toBe('Ka-Dzumeri')
        ->and(AuditLog::query()->where('event', 'profile.updated_assisted')->value('actor_id'))->toBe($facilitator->id);

    // Help sessions end after 30 minutes (staff are also signed out after 30 idle minutes).
    $this->travel(31)->minutes();
    $this->put('/hub-ops/assist/profile', ['place_name' => 'Elsewhere', 'present' => true]);
    expect($person->refresh()->place_name)->toBe('Ka-Dzumeri');
});

it('lists members with follow-up filters', function (): void {
    Staff::as($this, 'hub_facilitator');
    $active = User::factory()->create(['home_hub_id' => $this->hub->id]);
    User::factory()->create(['home_hub_id' => $this->hub->id]);
    HubVisit::query()->create(['hub_id' => $this->hub->id, 'user_id' => $active->id, 'visit_date' => now('Africa/Johannesburg')->subDays(3)->toDateString(), 'purpose' => 'jobs', 'method' => 'qr']);

    $this->get('/hub-ops/members')->assertInertia(fn ($page) => $page->has('members.data', 2));
    $this->get('/hub-ops/members?filter=inactive')->assertInertia(fn ($page) => $page->has('members.data', 1));
    $this->get('/hub-ops/members?filter=no_id')->assertInertia(fn ($page) => $page->has('members.data', 2));
});

it('anonymises visits older than 24 months', function (): void {
    $person = User::factory()->create();
    HubVisit::query()->create(['hub_id' => $this->hub->id, 'user_id' => $person->id, 'visit_date' => now()->subMonths(25)->toDateString(), 'purpose' => 'jobs', 'method' => 'qr']);
    HubVisit::query()->create(['hub_id' => $this->hub->id, 'user_id' => $person->id, 'visit_date' => now()->subMonths(2)->toDateString(), 'purpose' => 'jobs', 'method' => 'qr']);

    $this->artisan('kasi:hub-ops:anonymise-visits')->assertSuccessful();

    expect(HubVisit::query()->count())->toBe(2)->and(HubVisit::query()->whereNull('user_id')->count())->toBe(1);
});

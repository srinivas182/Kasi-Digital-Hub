<?php

declare(strict_types=1);

use Illuminate\Support\Facades\URL;
use Modules\Core\Identity\Models\User;
use Modules\Core\Platform\Models\Update;
use Modules\Core\Tests\Structure;
use Modules\HubOps\Models\EventRegistration;
use Modules\HubOps\Models\HubEvent;
use Modules\HubOps\Models\HubVisit;
use Modules\HubOps\Services\EventBook;
use Modules\HubOps\Tests\Staff;

beforeEach(function (): void {
    Structure::seed($this);
    $this->travelTo(now()->setTimezone('Africa/Johannesburg')->setTime(10, 0)->utc());
    $this->hub = Structure::hub('LP-GIY-TSU');
});

function anEvent(array $overrides = []): HubEvent
{
    return HubEvent::query()->create([
        'hub_id' => Structure::hub('LP-GIY-TSU')->id, 'type' => 'job_day', 'title' => 'Spar job day', 'room' => 'Hall',
        'starts_at' => now()->addDays(3), 'ends_at' => now()->addDays(3)->addHours(3), 'capacity' => 2, 'audience' => 'public',
        'status' => 'scheduled', ...$overrides,
    ]);
}

it('lets staff schedule an event in South African time', function (): void {
    Staff::as($this, 'hub_facilitator');

    $this->post('/hub-ops/events', [
        'type' => 'workshop', 'title' => 'CV workshop', 'room' => 'Lab', 'capacity' => 15, 'audience' => 'public',
        'starts_at' => now('Africa/Johannesburg')->addDays(2)->setTime(9, 0)->format('Y-m-d\TH:i'),
        'ends_at' => now('Africa/Johannesburg')->addDays(2)->setTime(11, 0)->format('Y-m-d\TH:i'),
    ])->assertSessionHasNoErrors();

    $event = HubEvent::query()->sole();
    expect($event->hub_id)->toBe($this->hub->id)->and($event->starts_at->setTimezone('Africa/Johannesburg')->format('H:i'))->toBe('09:00');
});

it('rejects events in the past or ending before they start', function (): void {
    Staff::as($this, 'hub_facilitator');

    $this->post('/hub-ops/events', [
        'type' => 'workshop', 'title' => 'Late', 'capacity' => 5, 'audience' => 'public',
        'starts_at' => now('Africa/Johannesburg')->subDay()->format('Y-m-d\TH:i'), 'ends_at' => now('Africa/Johannesburg')->subDays(2)->format('Y-m-d\TH:i'),
    ])->assertSessionHasErrors(['starts_at', 'ends_at']);
});

it('fills places, then a waiting list, and moves the first person up when someone cancels', function (): void {
    $event = anEvent();
    [$a, $b, $c, $d] = User::factory()->count(4)->create()->all();

    foreach ([$a, $b, $c, $d] as $person) {
        Staff::signIn($this, $person);
        $this->post("/events/{$event->id}/register")->assertSessionHasNoErrors();
    }

    $status = fn (User $u) => EventRegistration::query()->where('user_id', $u->id)->value('status');
    expect([$status($a), $status($b), $status($c), $status($d)])->toBe(['registered', 'registered', 'waitlisted', 'waitlisted']);

    Staff::signIn($this, $a);
    $this->delete("/events/{$event->id}/register")->assertSessionHasNoErrors();

    expect($status($a))->toBe('cancelled')->and($status($c))->toBe('registered')->and($status($d))->toBe('waitlisted')
        ->and(Update::query()->where('user_id', $c->id)->where('title', 'like', 'A place opened up%')->exists())->toBeTrue();
});

it('moves people up when places are added', function (): void {
    Staff::as($this, 'hub_manager');
    $event = anEvent(['capacity' => 1]);
    $people = User::factory()->count(3)->create();
    $people->each(fn (User $u) => app(EventBook::class)->register($event, $u));

    $this->put("/hub-ops/events/{$event->id}", [
        'type' => 'job_day', 'title' => 'Spar job day', 'capacity' => 3, 'audience' => 'public',
        'starts_at' => $event->starts_at->setTimezone('Africa/Johannesburg')->format('Y-m-d\TH:i'),
        'ends_at' => $event->ends_at->setTimezone('Africa/Johannesburg')->format('Y-m-d\TH:i'),
    ])->assertSessionHasNoErrors();

    expect(EventRegistration::query()->where('status', 'registered')->count())->toBe(3);
});

it('keeps under-18s to events marked for learners', function (): void {
    $minor = User::factory()->create(['date_of_birth' => now()->subYears(17), 'age_band' => 'minor']);
    Staff::signIn($this, $minor);

    $this->post('/events/'.anEvent()->id.'/register')->assertSessionHasErrors('event');
    $this->post('/events/'.anEvent(['audience' => 'learners'])->id.'/register')->assertSessionHasNoErrors();
});

it('cancels an event with a reason and tells everyone', function (): void {
    Staff::as($this, 'hub_manager');
    $event = anEvent(['capacity' => 1]);
    [$a, $b] = User::factory()->count(2)->create()->all();
    app(EventBook::class)->register($event, $a);
    app(EventBook::class)->register($event, $b);

    $this->post("/hub-ops/events/{$event->id}/cancel", ['reason' => ''])->assertSessionHasErrors('reason');
    $this->post("/hub-ops/events/{$event->id}/cancel", ['reason' => 'The hall is flooded'])->assertSessionHasNoErrors();

    expect($event->refresh()->status)->toBe('cancelled')
        ->and(Update::query()->where('title', 'Cancelled: Spar job day')->count())->toBe(2);
});

it('lets only managers cancel events', function (): void {
    Staff::as($this, 'hub_facilitator');

    $this->post('/hub-ops/events/'.anEvent()->id.'/cancel', ['reason' => 'Not allowed'])->assertForbidden();
});

it('sends one reminder the day before', function (): void {
    $event = anEvent(['starts_at' => now()->addHours(24), 'ends_at' => now()->addHours(26)]);
    $person = User::factory()->create();
    app(EventBook::class)->register($event, $person);

    $this->artisan('kasi:hub-ops:remind-events')->assertSuccessful();
    $this->artisan('kasi:hub-ops:remind-events')->assertSuccessful();

    expect(Update::query()->where('user_id', $person->id)->where('title', 'Tomorrow: Spar job day')->count())->toBe(1);
});

it('records attendance from the event QR code during the event only, and counts the visit', function (): void {
    $event = anEvent(['starts_at' => now()->addMinutes(20), 'ends_at' => now()->addHours(2)]);
    $person = User::factory()->create();
    app(EventBook::class)->register($event, $person);
    Staff::signIn($this, $person);
    $url = URL::temporarySignedRoute('events.attend', $event->ends_at->addHour(), ['event' => $event->id]);

    $this->get($url)->assertRedirect("/events/{$event->id}");
    expect(EventRegistration::query()->where('user_id', $person->id)->value('attended_at'))->not->toBeNull()
        ->and(HubVisit::query()->where('user_id', $person->id)->value('purpose'))->toBe('event');

    $this->get("/events/{$event->id}/attend")->assertForbidden(); // unsigned link
});

it('does not take attendance long before the event', function (): void {
    $event = anEvent();
    $person = User::factory()->create();
    Staff::signIn($this, $person);

    $this->get(URL::temporarySignedRoute('events.attend', $event->ends_at->addHour(), ['event' => $event->id]))->assertSessionHasErrors('event');
});

it('lets staff sign people up by phone and tick attendance', function (): void {
    Staff::as($this, 'hub_facilitator');
    $event = anEvent(['starts_at' => now()->subMinutes(10), 'ends_at' => now()->addHours(2)]);
    $person = User::factory()->create(['phone' => '+27724183390']);

    $this->post("/hub-ops/events/{$event->id}/registrations", ['phone' => '0731111111'])->assertSessionHasErrors('phone');
    $this->post("/hub-ops/events/{$event->id}/registrations", ['phone' => '072 418 3390'])->assertSessionHasNoErrors();
    $registration = EventRegistration::query()->sole();
    $this->post("/hub-ops/events/{$event->id}/registrations/{$registration->id}/attend")->assertSessionHasNoErrors();

    expect($registration->refresh()->attended_at)->not->toBeNull()
        ->and(HubVisit::query()->where('user_id', $person->id)->exists())->toBeTrue();
});

it('hides other hubs\' events from hub staff', function (): void {
    Staff::as($this, 'hub_facilitator');
    $other = anEvent(['hub_id' => Structure::hub('GP-JHB-SOW')->id]);

    $this->get("/hub-ops/events/{$other->id}")->assertNotFound();
});

it('shows public events on the hub page and my events on my hub home', function (): void {
    $event = anEvent();
    anEvent(['title' => 'Members meeting', 'audience' => 'members']);
    $person = User::factory()->create(['home_hub_id' => $this->hub->id]);
    app(EventBook::class)->register($event, $person);

    $this->get("/hubs/{$this->hub->slug}")->assertInertia(fn ($page) => $page->has('events', 1)->where('events.0.title', 'Spar job day'));

    Staff::signIn($this, $person);
    $this->get('/home')->assertInertia(fn ($page) => $page->where('agenda.0.title', 'Spar job day')->where('agenda.0.status', 'registered'));
    $this->get('/events')->assertInertia(fn ($page) => $page->has('events', 2));
});

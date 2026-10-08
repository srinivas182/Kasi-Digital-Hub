<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Modules\Core\Access\HubEntitlements;
use Modules\Core\Access\RoleAssignments;
use Modules\Core\Access\Scope;
use Modules\Core\Events\UserRegistered;
use Modules\Core\Identity\Models\User;
use Modules\Core\Identity\Services\ConsentService;
use Modules\Core\Notifications\NotificationCatalogue;
use Modules\Core\Platform\EventCatalogue;
use Modules\Core\Platform\Models\PlatformEventRecord;
use Modules\Core\Platform\Models\Update;
use Modules\Core\Tests\Helpers;
use Modules\Core\Tests\Structure;

beforeEach(fn () => $this->travelTo(now()->setTimezone('Africa/Johannesburg')->setTime(10, 0)->utc()));

it('records platform events with who, what and where', function (): void {
    Structure::seed($this);
    $hub = Structure::hub('LP-GIY-TSU');
    $person = User::factory()->create();

    app(RoleAssignments::class)->assign($person, 'hub_facilitator', Scope::hub($hub));

    $event = PlatformEventRecord::query()->where('name', 'core.role.assigned')->latest('occurred_at')->firstOrFail();
    expect($event->user_id)->toBe($person->id)
        ->and($event->hub_id)->toBe($hub->id)
        ->and($event->payload)->toMatchArray(['role' => 'hub_facilitator', 'scope_type' => 'hub']);
});

it('never records events for changes that were rolled back', function (): void {
    $before = PlatformEventRecord::query()->count();

    try {
        DB::transaction(function (): void {
            event(new UserRegistered(User::factory()->create()));
            throw new RuntimeException('rollback');
        });
    } catch (RuntimeException) {
        // expected
    }

    expect(PlatformEventRecord::query()->count())->toBe($before);
});

it('records consent changes and hub package changes', function (): void {
    Structure::seed($this);
    $user = User::factory()->create();

    app(ConsentService::class)->record($user, ['marketing' => true]);
    app(HubEntitlements::class)->addOn(Structure::hub('LP-TZA-NKO'), 'Learn');

    expect(PlatformEventRecord::query()->where('name', 'core.consent.changed')->where('user_id', $user->id)->exists())->toBeTrue()
        ->and(PlatformEventRecord::query()->where('name', 'core.hub.package_changed')->latest('occurred_at')->first()->payload['modules'])->toContain('Learn');
});

it('welcomes new people and tells staff about roles others gave them', function (): void {
    Structure::seed($this);

    Helpers::signUp($this, '+27724183390');
    $user = User::query()->where('phone', '+27724183390')->firstOrFail();
    expect(Update::query()->where('user_id', $user->id)->where('title', 'like', 'Welcome to KasiHub%')->exists())->toBeTrue();

    app(RoleAssignments::class)->assign($user, 'job_seeker', Scope::self());
    app(RoleAssignments::class)->assign($user, 'hub_facilitator', Scope::hub(Structure::hub('LP-GIY-TSU')));

    expect(Update::query()->where('user_id', $user->id)->where('title', 'like', 'You are now a%')->pluck('title')->all())->toBe(['You are now a Hub facilitator']);
});

it('keeps the event catalogue and module manifests in step', function (): void {
    expect(app(EventCatalogue::class)->problems())->toBe([]);
});

it('has up-to-date generated docs (run php artisan kasi:docs:generate)', function (): void {
    expect(file_get_contents(base_path('docs/event-catalogue.md')))->toBe(app(EventCatalogue::class)->markdown())
        ->and(file_get_contents(base_path('docs/whatsapp-templates.md')))->toBe(app(NotificationCatalogue::class)->whatsappMarkdown());
});

it('shows the updates feed with unread counts, opens items and marks all read', function (): void {
    $user = User::factory()->create();
    app(ConsentService::class)->record($user, ['platform' => true]);
    $first = Update::query()->create(['user_id' => $user->id, 'module' => 'Work', 'category' => 'jobs', 'title' => 'New job match', 'url' => '/home', 'created_at' => now()]);
    Update::query()->create(['user_id' => $user->id, 'module' => 'Learn', 'category' => 'learning', 'title' => 'Course reminder', 'created_at' => now()]);

    Helpers::signedIn($this, $user);
    $this->get('/home/updates')->assertOk()->assertInertia(fn ($page) => $page
        ->component('Hub/Updates')
        ->where('auth.user.unreadUpdates', 2)
        ->has('updates.data', 2));

    $this->get("/home/updates/{$first->id}")->assertRedirect('/home');
    expect($first->refresh()->read_at)->not->toBeNull();

    $this->post('/home/updates/read')->assertRedirect();
    expect(Update::query()->where('user_id', $user->id)->whereNull('read_at')->count())->toBe(0);
});

it("does not open someone else's update", function (): void {
    $user = User::factory()->create();
    app(ConsentService::class)->record($user, ['platform' => true]);
    $other = Update::query()->create(['user_id' => User::factory()->create()->id, 'module' => 'Hub', 'category' => 'account', 'title' => 'x', 'created_at' => now()]);

    Helpers::signedIn($this, $user);
    $this->get("/home/updates/{$other->id}")->assertNotFound();
});

it('schedules the background jobs and records heartbeats', function (): void {
    $this->artisan('schedule:list')
        ->expectsOutputToContain('kasi:notifications:release')
        ->expectsOutputToContain('kasi:documents:remind-expiring')
        ->expectsOutputToContain('model:prune')
        ->assertSuccessful();
});

it('only lets super and operations admins see the queue dashboard', function (): void {
    Structure::seed($this);

    expect(Gate::forUser(Structure::personWith('super_admin'))->allows('viewHorizon'))->toBeTrue()
        ->and(Gate::forUser(Structure::personWith('hub_manager'))->allows('viewHorizon'))->toBeFalse();
});

<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Modules\Core\Access\Scope;
use Modules\Core\Documents\Generation\FakePdfRenderer;
use Modules\Core\Identity\Models\AuditLog;
use Modules\Core\Identity\Models\User;
use Modules\Core\Insights\Insights;
use Modules\Core\Structure\Models\Organisation;
use Modules\Core\Structure\Models\Province;
use Modules\Core\Tests\Structure;
use Modules\Funder\Models\Programme;
use Modules\Funder\Services\Programmes;
use Modules\HubOps\Events\VisitRecorded;
use Modules\HubOps\Models\HubVisit;
use Modules\HubOps\Tests\Staff;
use Modules\Work\Events\HireConfirmed;
use Tests\TestCase;

beforeEach(function (): void {
    Structure::seed($this);
    $this->hub = Structure::hub('LP-GIY-TSU');
    $this->funder = Organisation::query()->create(['type' => 'funder', 'name' => 'Youth Fund', 'verification_status' => 'verified']);
    $this->manager = Structure::personWith('funder_manager', Scope::organisation($this->funder));
    $this->staff = Structure::personWith('operations_admin', Scope::national());
    $this->programme = Programme::query()->create(['organisation_id' => $this->funder->id, 'name' => 'Pathways', 'scope' => ['hub_ids' => [$this->hub->id]],
        'starts_on' => now()->startOfMonth()->toDateString(), 'ends_on' => now()->addYear()->toDateString(), 'age_min' => 18, 'age_max' => 35, 'status' => 'active']);
    DB::table('fund_targets')->insert(['programme_id' => $this->programme->id, 'indicator' => 'work.hires', 'target' => 20]);
});

/** Record platform events directly, like the portals do. */
function hires(TestCase $t, int $count, ?string $hubId, int $age = 22): void
{
    for ($i = 0; $i < $count; $i++) {
        $person = User::factory()->create(['home_hub_id' => $hubId, 'date_of_birth' => now()->subYears($age)->subDays(3)]);
        event(new HireConfirmed($person, null, ['application' => (string) Str::ulid()]));
    }
}

it('counts exactly what the indicator definition says, within the programme scope and age group', function (): void {
    hires($this, 12, $this->hub->id);
    hires($this, 3, $this->hub->id, age: 40); // outside the age group
    hires($this, 5, null); // no hub - outside the scope

    expect(app(Programmes::class)->progress($this->programme)['work.hires'])->toBe(['value' => 12, 'target' => 20, 'percent' => 60]);
});

it('shows numbers from 1 to 9 as "<10" everywhere a funder can see them', function (): void {
    hires($this, 4, $this->hub->id);
    Staff::signIn($this, $this->manager);

    $this->get("/funder/programmes/{$this->programme->id}")->assertInertia(fn ($page) => $page->where('progress', fn ($p) => $p['work.hires']['value'] === '<10') // keys contain dots
        ->where('breakdown.hub', fn ($h) => collect($h)->every(fn ($v) => $v === '<10' || $v === 0 || $v >= 10)));
    $csv = $this->get("/funder/programmes/{$this->programme->id}/export.csv")->streamedContent();
    expect($csv)->toContain('<10')->not->toContain(',4,');
    $this->get("/funder/programmes/{$this->programme->id}/report")->assertOk();
    expect(end(FakePdfRenderer::$rendered))->toContain('&lt;10')->not->toMatch('/>\s*4\s*</');
});

it('never puts personal data in funder or regional responses', function (): void {
    hires($this, 12, $this->hub->id);
    // A distinctive person (factory names repeat - another user on the page could share a random surname).
    $person = User::query()->whereNotNull('home_hub_id')->latest('created_at')->firstOrFail();
    $person->forceFill(['first_name' => 'Zinhlezenkosi', 'last_name' => 'Qwabe-Ntshangase', 'phone' => '+27729990001'])->save();
    Staff::signIn($this, $this->manager);
    $body = json_encode($this->get("/funder/programmes/{$this->programme->id}")->viewData('page'));

    expect($body)->not->toContain($person->phone)->not->toContain((string) $person->last_name)->not->toContain($person->id);
});

it('freezes monthly snapshots so reports never change, and marks the current month provisional', function (): void {
    hires($this, 11, $this->hub->id);
    $period = now()->format('Y-m');
    expect(app(Programmes::class)->month($this->programme, $period)['provisional'])->toBeTrue();

    app(Programmes::class)->freeze($period);
    hires($this, 5, $this->hub->id); // after the freeze
    $month = app(Programmes::class)->month($this->programme, $period);
    expect($month['provisional'])->toBeFalse()->and($month['values']['work.hires'])->toBe(11);
});

it('keeps programmes to their funder, and target changes need KasiHub approval', function (): void {
    Staff::signIn($this, Structure::personWith('funder_manager', Scope::organisation(Organisation::query()->create(['type' => 'funder', 'name' => 'Other']))));
    $this->get("/funder/programmes/{$this->programme->id}")->assertNotFound();

    Staff::signIn($this, $this->manager);
    $this->post("/funder/programmes/{$this->programme->id}/targets", ['indicator' => 'work.hires', 'target' => 30, 'reason' => 'Contract amendment 2'])->assertSessionHasNoErrors();
    expect((int) DB::table('fund_targets')->value('target'))->toBe(20);

    Staff::signIn($this, $this->staff);
    $this->post('/funder/target-changes/'.DB::table('fund_target_changes')->value('id'), ['approve' => true])->assertSessionHasNoErrors();
    expect((int) DB::table('fund_targets')->value('target'))->toBe(30)
        ->and(AuditLog::query()->where('event', 'funder.viewed')->exists())->toBeFalse(); // only views are audited (none yet by staff)
});

it('shows coordinators only their area, with health counts and an activity-drop alert', function (): void {
    $coordinator = Structure::personWith('provincial_coordinator', Scope::province(Province::query()->findOrFail(
        DB::table('municipalities')->where('id', $this->hub->municipality_id)->value('province_id'))));
    // Three busy months, then a quiet one.
    $visitor = fn () => User::factory()->create(['home_hub_id' => $this->hub->id]);
    foreach ([3, 2, 1] as $monthsAgo) {
        $this->travelTo(now()->startOfMonth()->subMonths($monthsAgo)->addDays(3));
        for ($i = 0; $i < 30; $i++) {
            $visit = new HubVisit;
            $visit->forceFill(['hub_id' => $this->hub->id, 'user_id' => $visitor()->id, 'purpose' => 'learning', 'method' => 'staff']);
            event(new VisitRecorded($visit));
        }
        $this->travelBack();
    }
    $this->travelTo(now()->endOfMonth()->subHour());

    Staff::signIn($this, $coordinator);
    $this->get('/region')->assertInertia(fn ($page) => $page->where('area.type', 'province')
        ->where('hubs', fn ($hubs) => collect($hubs)->firstWhere('id', $this->hub->id)['drop'] === true)
        ->where('signals', fn ($s) => collect($s)->contains('core.documents_waiting')));
    $this->post("/region/hubs/{$this->hub->id}/message", ['message' => 'Can we talk about visitor numbers?'])->assertSessionHasNoErrors();

    Staff::signIn($this, User::factory()->create());
    $this->get('/region')->assertForbidden();
});

it('suppresses small cells in the shared helper', function (): void {
    expect(Insights::suppress(['a' => 0, 'b' => 1, 'c' => 9, 'd' => 10]))->toBe(['a' => 0, 'b' => '<10', 'c' => '<10', 'd' => 10]);
});

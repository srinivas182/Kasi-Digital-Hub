<?php

declare(strict_types=1);

use Modules\Core\Access\Scope;
use Modules\Core\Identity\Models\User;
use Modules\Core\Identity\Services\OtpService;
use Modules\Core\Structure\Models\RoleAssignment;
use Modules\Core\Tests\Structure;
use Modules\HubOps\Models\HubVisit;
use Modules\HubOps\Tests\Staff;

beforeEach(function (): void {
    Structure::seed($this);
    $this->travelTo(now()->setTimezone('Africa/Johannesburg')->setTime(10, 0)->utc());
    $this->hub = Structure::hub('LP-GIY-TSU');
});

function recordVisit(string $hubId, ?string $userId, int $daysAgo, string $purpose = 'jobs'): void
{
    HubVisit::query()->create(['hub_id' => $hubId, 'user_id' => $userId, 'visit_date' => now('Africa/Johannesburg')->subDays($daysAgo)->toDateString(), 'purpose' => $purpose, 'method' => 'qr']);
}

it('shows the hub numbers for the chosen period', function (): void {
    Staff::as($this, 'hub_manager');
    [$a, $b] = User::factory()->count(2)->create(['home_hub_id' => $this->hub->id])->all();
    recordVisit($this->hub->id, $a->id, 1);
    recordVisit($this->hub->id, $a->id, 2, 'learning');
    recordVisit($this->hub->id, $b->id, 3);
    recordVisit($this->hub->id, null, 3, 'computer');
    recordVisit($this->hub->id, $b->id, 20);

    $this->get('/hub-ops?period=7')->assertInertia(fn ($page) => $page
        ->where('metrics.totals.visits', 4)
        ->where('metrics.totals.people', 2)
        ->where('metrics.totals.walkIns', 1)
        ->where('metrics.totals.newMembers', 2)
        ->where('metrics.purposes.jobs', 2)
        ->has('metrics.byDay', 7));

    $this->get('/hub-ops?period=30')->assertInertia(fn ($page) => $page->where('metrics.totals.visits', 5));
});

it('compares the hubs a coordinator looks after', function (): void {
    Staff::as($this, 'provincial_coordinator', Scope::province(Structure::province('LP')));
    recordVisit($this->hub->id, null, 1);

    $this->get('/hub-ops/compare')->assertInertia(fn ($page) => $page
        ->where('rows', fn ($rows) => collect($rows)->firstWhere('name', 'Tsutsumani Digital Hub')['visits'] === 1
            && collect($rows)->pluck('name')->doesntContain('Soweto Hub')));
});

it('exports the hub numbers as CSV', function (): void {
    Staff::as($this, 'hub_manager');
    recordVisit($this->hub->id, null, 1);

    expect($this->get('/hub-ops/export?period=7')->assertOk()->streamedContent())->toStartWith('date,visits')->toContain('visits,1');
});

it('saves the hub internet connection and gives it the higher sign-in limit', function (): void {
    Staff::as($this, 'hub_manager');

    $this->put('/hub-ops/settings', ['trusted_ips' => ['10.0.0.0/8']])->assertSessionHasErrors('trusted_ips.0');
    $this->put('/hub-ops/settings', ['trusted_ips' => ['not-an-ip']])->assertSessionHasErrors('trusted_ips.0');
    $this->put('/hub-ops/settings', ['trusted_ips' => ['196.25.10.0/24'], 'phone' => '015 812 0000'])->assertSessionHasNoErrors();

    expect($this->hub->refresh()->trusted_ips)->toBe(['196.25.10.0/24'])->and($this->hub->phone)->toBe('+27158120000');

    config(['kasi.identity.otp.per_ip_hour' => 1]);
    $otp = app(OtpService::class);
    expect($otp->request('+27724183390', 'login', '196.25.10.7', 'a')['sent'])->toBeTrue()
        ->and($otp->request('+27731234567', 'login', '196.25.10.7', 'b')['sent'])->toBeTrue();
});

it('shows a new door-screen link once', function (): void {
    Staff::as($this, 'hub_manager');

    $this->post('/hub-ops/settings/kiosk')->assertSessionHas('kiosk_url');
    expect($this->hub->refresh()->kiosk_token_hash)->not->toBeNull();
});

it('lets a hub manager appoint and remove facilitators at their own hub', function (): void {
    Staff::as($this, 'hub_manager');
    $person = User::factory()->create(['phone' => '+27724183390']);

    $this->post('/hub-ops/settings/facilitators', ['phone' => '0724183390'])->assertSessionHasErrors('reason');
    $this->post('/hub-ops/settings/facilitators', ['phone' => '0724183390', 'reason' => 'Started as a volunteer'])->assertSessionHasNoErrors();

    $assignment = RoleAssignment::query()->where('user_id', $person->id)->sole();
    expect($assignment->scope_id)->toBe($this->hub->id)->and($assignment->role)->toBe('hub_facilitator');

    $this->delete("/hub-ops/settings/facilitators/{$assignment->id}", ['reason' => 'Contract ended'])->assertSessionHasNoErrors();
    expect(RoleAssignment::query()->where('user_id', $person->id)->exists())->toBeFalse();
});

it('does not let a manager remove facilitators from another hub', function (): void {
    Staff::as($this, 'hub_manager');
    $other = Structure::personWith('hub_facilitator', Scope::hub(Structure::hub('GP-JHB-SOW')));
    $assignment = RoleAssignment::query()->where('user_id', $other->id)->sole();

    $this->delete("/hub-ops/settings/facilitators/{$assignment->id}", ['reason' => 'Not my hub'])->assertNotFound();
});

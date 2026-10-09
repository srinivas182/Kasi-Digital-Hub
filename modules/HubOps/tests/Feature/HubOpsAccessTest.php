<?php

declare(strict_types=1);

use Modules\Core\Access\Scope;
use Modules\Core\Tests\Structure;
use Modules\HubOps\Tests\Staff;

beforeEach(fn () => Structure::seed($this));

it('gives each role the right KasiHub Ops screens', function (string $role, array $allowed): void {
    Staff::as($this, $role);
    $screens = [
        'dashboard' => '/hub-ops', 'compare' => '/hub-ops/compare', 'checkin' => '/hub-ops/check-in', 'members' => '/hub-ops/members',
        'events' => '/hub-ops/events', 'register' => '/hub-ops/register', 'settings' => '/hub-ops/settings',
    ];

    foreach ($screens as $name => $path) {
        expect($this->get($path)->getStatusCode())->toBe(in_array($name, $allowed, true) ? 200 : 403, "{$role} -> {$name}");
    }
})->with([
    ['hub_owner', ['dashboard', 'compare', 'checkin', 'members', 'events', 'register', 'settings']],
    ['hub_manager', ['dashboard', 'compare', 'checkin', 'members', 'events', 'register', 'settings']],
    ['hub_facilitator', ['dashboard', 'checkin', 'members', 'events', 'register']],
    ['city_coordinator', ['dashboard', 'compare']],
    ['provincial_coordinator', ['dashboard', 'compare']],
    ['super_admin', ['dashboard', 'compare', 'checkin', 'members', 'events', 'register', 'settings']],
]);

it('keeps citizens out of the staff screens', function (): void {
    Staff::as($this, 'job_seeker');

    $this->get('/hub-ops')->assertForbidden();
    $this->get('/hub-ops/check-in')->assertForbidden();
});

it('keeps hub staff on their own hub', function (): void {
    Staff::as($this, 'hub_facilitator');
    $other = Structure::hub('GP-JHB-SOW');

    $this->get("/hub-ops?hub={$other->id}")->assertInertia(fn ($page) => $page->where('hubs.current.name', 'Tsutsumani Digital Hub')->has('hubs.options', 1));
});

it('lets a city coordinator choose between the hubs in their city only', function (): void {
    Staff::as($this, 'city_coordinator', Scope::municipality(Structure::city('LIM331')));

    $this->get('/hub-ops')->assertInertia(fn ($page) => $page->where('hubs.options', fn ($options) => collect($options)->pluck('name')->every(fn ($n) => in_array($n, ['Tsutsumani Digital Hub', 'Giyani Central Hub'], true))));
});

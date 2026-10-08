<?php

declare(strict_types=1);

use Modules\Admin\Tests\Console;
use Modules\Core\Tests\Structure;

beforeEach(fn () => Structure::seed($this));

/*
| Who may open what (spec table): super admin, operations admin, support agent, content reviewer.
*/
it('lets each admin role open exactly the screens it should', function (string $role, array $allowed): void {
    Console::as($this, $role);
    $hub = Structure::hub('LP-GIY-TSU');
    $screens = [
        'dashboard' => '/admin', 'people' => '/admin/people', 'hubs' => '/admin/hubs', 'hub' => "/admin/hubs/{$hub->id}",
        'hub_create' => '/admin/hubs/create', 'organisations' => '/admin/organisations', 'org_create' => '/admin/organisations/create',
        'verification' => '/admin/verification', 'enquiries' => '/admin/enquiries', 'audit' => '/admin/audit', 'export' => '/admin/audit/export',
    ];

    foreach ($screens as $name => $path) {
        $status = $this->get($path)->getStatusCode();
        expect($status)->toBe(in_array($name, $allowed, true) ? 200 : 403, "{$role} -> {$name}");
    }
})->with([
    ['super_admin', ['dashboard', 'people', 'hubs', 'hub', 'hub_create', 'organisations', 'org_create', 'verification', 'enquiries', 'audit', 'export']],
    ['operations_admin', ['dashboard', 'people', 'hubs', 'hub', 'hub_create', 'organisations', 'org_create', 'verification', 'enquiries', 'audit']],
    ['support_agent', ['dashboard', 'people', 'hubs', 'hub', 'organisations', 'verification', 'enquiries']],
    ['content_reviewer', ['dashboard', 'people', 'hubs', 'hub', 'organisations']],
]);

it('keeps everyone else out of the console', function (string $role): void {
    Console::as($this, $role);

    $this->get('/admin')->assertForbidden();
    $this->get('/admin/people')->assertForbidden();
})->with(['job_seeker', 'hub_manager', 'provincial_coordinator', 'finance_admin']);

it('only shows console menu items the person may open', function (): void {
    Console::as($this, 'support_agent');

    $this->get('/admin')->assertInertia(fn ($page) => $page->where('navigation.portals', fn ($portals) => collect($portals)->firstWhere('module', 'Admin')['items'] !== null
        && collect(collect($portals)->firstWhere('module', 'Admin')['items'])->pluck('href')->all() === ['/admin', '/admin/people', '/admin/hubs', '/admin/organisations', '/admin/verification', '/admin/enquiries']));
});

it('shows the overview numbers', function (): void {
    Console::as($this, 'super_admin');

    $this->get('/admin')->assertInertia(fn ($page) => $page
        ->component('Admin/Dashboard')
        ->where('kpis.hubsLive', 11)
        ->has('registrations', 12)
        ->where('hubsByStatus.planned', 1));
});

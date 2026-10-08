<?php

declare(strict_types=1);

use Modules\Core\Access\AccessResolver;
use Modules\Core\Access\RoleRegistry;
use Modules\Core\Tests\Structure;

/*
| The access matrix agreed in "Kasi Digital Hub - Portals & Roles v1.0" (Release 1 portals).
| If a manifest drifts from the agreed matrix, this test fails.
| Hub roles are checked at Tsutsumani, which has the Full package.
*/
const AGREED_MATRIX = [
    'job_seeker' => ['Work' => 'use', 'Learn' => 'use'],
    'learner' => ['Learn' => 'use'],
    'entrepreneur' => ['Learn' => 'use', 'Start' => 'use', 'Connect' => 'use'],
    'mentor' => ['Connect' => 'use'],
    'employer_admin' => ['Work' => 'manage', 'Learn' => 'use', 'Connect' => 'use'],
    'recruiter' => ['Work' => 'use'],
    'provider_admin' => ['Learn' => 'manage'],
    'course_author' => ['Learn' => 'use'],
    'assessor_moderator' => ['Learn' => 'use'],
    'partner_admin' => ['Start' => 'view', 'Connect' => 'view', 'Partner' => 'manage'],
    'partner_agent' => ['Partner' => 'use'],
    'funder_manager' => ['Learn' => 'view', 'Funder' => 'manage'],
    'funder_viewer' => ['Funder' => 'view'],
    'hub_owner' => ['Work' => 'view', 'Learn' => 'view', 'Start' => 'view', 'Connect' => 'view', 'HubOps' => 'manage', 'Funder' => 'view'],
    'hub_manager' => ['Work' => 'assist', 'Learn' => 'assist', 'Start' => 'assist', 'Connect' => 'assist', 'HubOps' => 'manage', 'Funder' => 'view'],
    'hub_facilitator' => ['Work' => 'assist', 'Learn' => 'assist', 'Start' => 'assist', 'Connect' => 'assist', 'HubOps' => 'use'],
    'city_coordinator' => ['Work' => 'view', 'Learn' => 'view', 'Start' => 'view', 'Connect' => 'view', 'HubOps' => 'view', 'Region' => 'manage', 'Funder' => 'view'],
    'provincial_coordinator' => ['Work' => 'view', 'Learn' => 'view', 'Start' => 'view', 'Connect' => 'view', 'HubOps' => 'view', 'Region' => 'manage', 'Funder' => 'view'],
    'super_admin' => ['Work' => 'manage', 'Learn' => 'manage', 'Start' => 'manage', 'Connect' => 'manage', 'HubOps' => 'manage', 'Region' => 'manage', 'Funder' => 'manage', 'Partner' => 'manage', 'Admin' => 'manage', 'Commercial' => 'manage'],
    'operations_admin' => ['Work' => 'manage', 'Learn' => 'manage', 'Start' => 'manage', 'Connect' => 'manage', 'HubOps' => 'manage', 'Region' => 'view', 'Funder' => 'view', 'Partner' => 'manage', 'Admin' => 'manage'],
    'support_agent' => ['Work' => 'assist', 'Learn' => 'assist', 'Start' => 'assist', 'Connect' => 'assist', 'HubOps' => 'view', 'Admin' => 'view'],
    'content_reviewer' => ['Work' => 'view', 'Learn' => 'view', 'Connect' => 'view', 'Admin' => 'view'],
    'commercial_admin' => ['Work' => 'view', 'Learn' => 'view', 'Start' => 'view', 'Connect' => 'view', 'HubOps' => 'view', 'Region' => 'view', 'Funder' => 'view', 'Partner' => 'view', 'Admin' => 'view', 'Commercial' => 'manage'],
    'finance_admin' => ['Work' => 'view', 'Learn' => 'view', 'Start' => 'view', 'Connect' => 'view', 'HubOps' => 'view', 'Region' => 'view', 'Funder' => 'view', 'Partner' => 'view', 'Admin' => 'view', 'Commercial' => 'manage'],
];

beforeEach(fn () => Structure::seed($this));

it('defines exactly the agreed Release 1 roles', function (): void {
    expect(array_keys(app(RoleRegistry::class)->all()))->toEqualCanonicalizing(array_keys(AGREED_MATRIX));
});

it('grants each role exactly the agreed access', function (string $role, array $expected): void {
    $person = Structure::personWith($role);

    expect(app(AccessResolver::class)->levels($person))->toEqual($expected);
})->with(fn () => array_map(null, array_keys(AGREED_MATRIX), array_values(AGREED_MATRIX)));

it('requires the authenticator step for staff, organisation and national roles only', function (): void {
    $individual = ['job_seeker', 'learner', 'entrepreneur', 'mentor'];

    foreach (app(RoleRegistry::class)->all() as $key => $role) {
        expect($role->staff)->toBe(! in_array($key, $individual, true), "Role {$key}");
    }
});

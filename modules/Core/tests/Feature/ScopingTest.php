<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Core\Access\AccessLevel;
use Modules\Core\Access\AccessResolver;
use Modules\Core\Access\HubEntitlements;
use Modules\Core\Access\RoleAssignments;
use Modules\Core\Access\Scope;
use Modules\Core\Identity\Models\AuditLog;
use Modules\Core\Identity\Models\User;
use Modules\Core\Structure\Models\Hub;
use Modules\Core\Tests\Structure;

beforeEach(function (): void {
    Structure::seed($this);

    // One citizen per hub, so we can see who is visible to whom.
    foreach (Hub::all() as $hub) {
        User::factory()->create(['home_hub_id' => $hub->id, 'first_name' => 'Citizen', 'last_name' => $hub->code]);
    }
});

function visibleHubCodes(User $viewer, string $module = 'Work'): array
{
    return User::query()->where('first_name', 'Citizen')->visibleTo($viewer, $module)->pluck('last_name')->sort()->values()->all();
}

it('shows a facilitator only their own hub', function (): void {
    expect(visibleHubCodes(Structure::personWith('hub_facilitator')))->toBe(['LP-GIY-TSU']);
});

it('shows a city coordinator every hub in their city', function (): void {
    expect(visibleHubCodes(Structure::personWith('city_coordinator')))->toBe(['LP-GIY-CEN', 'LP-GIY-TSU']);
});

it('shows a provincial coordinator every hub in their province', function (): void {
    expect(visibleHubCodes(Structure::personWith('provincial_coordinator')))->toBe(['LP-COL-MAL', 'LP-GIY-CEN', 'LP-GIY-TSU', 'LP-TZA-NKO']);
});

it('shows national roles every hub', function (): void {
    expect(visibleHubCodes(Structure::personWith('super_admin')))->toHaveCount(12);
});

it('shows citizens and organisation roles no hub data', function (string $role): void {
    expect(visibleHubCodes(Structure::personWith($role)))->toBe([]);
})->with(['job_seeker', 'employer_admin', 'funder_manager']);

it('only gives hub staff the portals their hub package includes', function (): void {
    $baseHub = Structure::hub('LP-TZA-NKO'); // Base package: HubOps + KasiWork
    $facilitator = Structure::personWith('hub_facilitator', Scope::hub($baseHub));
    $access = app(AccessResolver::class);

    expect($access->levels($facilitator))->toBe(['Work' => 'assist', 'HubOps' => 'use']);

    app(HubEntitlements::class)->addOn($baseHub, 'Learn');
    expect($access->can($facilitator, 'Learn', AccessLevel::Assist))->toBeTrue();

    app(HubEntitlements::class)->disable($baseHub, 'Work');
    expect($access->can($facilitator, 'Work'))->toBeFalse();
});

it('keeps national services open to citizens whatever their hub package', function (): void {
    $seeker = Structure::personWith('job_seeker', attributes: ['home_hub_id' => Structure::hub('LP-TZA-NKO')->id]);

    expect(app(AccessResolver::class)->can($seeker, 'Learn', AccessLevel::Use))->toBeTrue();
});

it('switches a hub to a new package, keeping add-ons', function (): void {
    $hub = Structure::hub('LP-TZA-NKO');
    $entitlements = app(HubEntitlements::class);
    $entitlements->addOn($hub, 'Connect');
    $entitlements->applyPackage($hub, 'growth');

    expect($entitlements->modulesFor($hub))->toEqualCanonicalizing(['HubOps', 'Work', 'Learn', 'Connect'])
        ->and($hub->refresh()->package)->toBe('growth')
        ->and(AuditLog::query()->where('event', 'hub.package_applied')->exists())->toBeTrue();
});

it('guards routes by portal access level', function (): void {
    Route::middleware(['web', 'auth', 'portal:HubOps,manage'])->get('/_test/hub-settings', fn () => 'ok');

    foreach (['hub_facilitator' => 403, 'hub_manager' => 200, 'job_seeker' => 403] as $role => $status) {
        $person = Structure::personWith($role);
        $device = $person->devices()->create(['name' => 'Test']);
        $this->actingAs($person)->withSession(['device_id' => $device->id, 'last_activity_at' => now()->getTimestamp()])
            ->get('/_test/hub-settings')->assertStatus($status);
    }
});

it('switches the authenticator requirement on and off with staff roles, and audits changes', function (): void {
    $person = User::factory()->create();
    $roles = app(RoleAssignments::class);

    $roles->assign($person, 'job_seeker', Scope::self());
    expect($person->refresh()->two_factor_required)->toBeFalse();

    $roles->assign($person, 'hub_facilitator', Scope::hub(Structure::hub('LP-GIY-TSU')));
    expect($person->refresh()->two_factor_required)->toBeTrue();

    $roles->revoke($person, 'hub_facilitator', Scope::hub(Structure::hub('LP-GIY-TSU')));
    expect($person->refresh()->two_factor_required)->toBeFalse()
        ->and(AuditLog::query()->whereIn('event', ['role.assigned', 'role.revoked'])->count())->toBe(3);
});

it('refreshes cached access immediately when roles change', function (): void {
    $person = User::factory()->create();
    $access = app(AccessResolver::class);
    expect($access->levels($person))->toBe([]);

    app(RoleAssignments::class)->assign($person, 'job_seeker', Scope::self());
    expect($access->levels($person->refresh()))->toHaveKey('Work');
});

it('rejects a role given at the wrong level', function (): void {
    app(RoleAssignments::class)->assign(User::factory()->create(), 'hub_facilitator', Scope::national());
})->throws(InvalidArgumentException::class, 'must be given at hub level');

it('keeps minors to learning roles', function (): void {
    $minor = User::factory()->minor()->create();
    $roles = app(RoleAssignments::class);

    $roles->assign($minor, 'learner', Scope::self());
    expect(fn () => $roles->assign($minor, 'job_seeker', Scope::self()))->toThrow(InvalidArgumentException::class);
});

it('ignores expired roles', function (): void {
    $person = User::factory()->create();
    app(RoleAssignments::class)->assign($person, 'job_seeker', Scope::self(), expiresAt: now()->subDay());

    expect(app(AccessResolver::class)->levels($person->refresh()))->toBe([]);
});

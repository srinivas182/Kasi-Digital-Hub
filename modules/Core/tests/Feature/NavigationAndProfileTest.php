<?php

declare(strict_types=1);

use App\Support\Navigation\NavigationBuilder;
use Modules\Core\Identity\Models\User;
use Modules\Core\Identity\Services\ConsentService;
use Modules\Core\Tests\Helpers;
use Modules\Core\Tests\Structure;

beforeEach(fn () => Structure::seed($this));

function portalsFor(?User $user): array
{
    return array_column(app(NavigationBuilder::class)->portals($user), 'module');
}

it('shows guests no portal menus', function (): void {
    expect(portalsFor(null))->toBe([]);
});

it('shows each person only the portals they can open', function (string $role, array $portals): void {
    expect(portalsFor(Structure::personWith($role)))->toEqualCanonicalizing($portals);
})->with([
    ['job_seeker', ['Hub', 'Work', 'Learn']],
    ['hub_facilitator', ['Hub', 'Work', 'Learn', 'Start', 'Connect', 'HubOps']],
    ['funder_manager', ['Hub', 'Learn', 'Funder']],
    ['super_admin', ['Hub', 'Work', 'Learn', 'Start', 'Connect', 'HubOps', 'Region', 'Funder', 'Partner', 'Admin', 'Commercial']],
]);

it('lets people choose their nearest hub and location', function (): void {
    $user = User::factory()->create();
    app(ConsentService::class)->record($user, ['platform' => true]);
    Helpers::signedIn($this, $user);
    $hub = Structure::hub('LP-GIY-TSU');
    $province = Structure::province('LP');

    $this->put('/account/profile', [
        'first_name' => 'Thandi', 'last_name' => 'Mabasa', 'preferred_locale' => 'en',
        'home_hub_id' => $hub->id, 'province_id' => $province->id,
        'municipality_id' => Structure::city('JHB')->id, // not in Limpopo
    ])->assertSessionHasErrors('municipality_id');

    $this->put('/account/profile', [
        'first_name' => 'Thandi', 'last_name' => 'Mabasa', 'preferred_locale' => 'en',
        'home_hub_id' => $hub->id, 'province_id' => $province->id,
        'municipality_id' => Structure::city('LIM331')->id, 'place_name' => 'Tsutsumani',
    ])->assertSessionHasNoErrors();

    expect($user->refresh()->home_hub_id)->toBe($hub->id)->and($user->place_name)->toBe('Tsutsumani');

    $this->get('/account')->assertInertia(fn ($page) => $page->has('hubOptions', 11)->where('profile.homeHubId', $hub->id));
    $this->get('/home')->assertInertia(fn ($page) => $page->where('auth.user.homeHub', 'Tsutsumani Digital Hub'));
});

it('only offers live hubs', function (): void {
    $user = User::factory()->create();
    app(ConsentService::class)->record($user, ['platform' => true]);
    Helpers::signedIn($this, $user);

    $this->put('/account/profile', [
        'first_name' => 'A', 'last_name' => 'B', 'preferred_locale' => 'en',
        'home_hub_id' => Structure::hub('KZN-ETH-INA')->id, // planned, not live
    ])->assertSessionHasErrors('home_hub_id');
});

it('sets the home hub and location at sign-up when chosen', function (): void {
    $hub = Structure::hub('GP-JHB-SOW');

    Helpers::signUp($this, '+27724183390', ['home_hub_id' => $hub->id])->assertRedirect('/home');

    $user = User::query()->where('phone', '+27724183390')->firstOrFail();
    expect($user->home_hub_id)->toBe($hub->id)
        ->and($user->municipality_id)->toBe($hub->municipality_id)
        ->and($user->province_id)->toBe(Structure::province('GP')->id);
});

it('lists the roles a person holds on their account page', function (): void {
    $user = Structure::personWith('job_seeker');
    app(ConsentService::class)->record($user, ['platform' => true]);
    Helpers::signedIn($this, $user);

    $this->get('/account')->assertInertia(fn ($page) => $page->where('roles.0', ['role' => 'Job seeker', 'where' => 'Own account']));
});

<?php

declare(strict_types=1);

use Modules\Admin\Tests\Console;
use Modules\Core\Access\AccessResolver;
use Modules\Core\Access\HubEntitlements;
use Modules\Core\Access\Scope;
use Modules\Core\Identity\Models\User;
use Modules\Core\Platform\Models\PlatformEventRecord;
use Modules\Core\Platform\Models\Update;
use Modules\Core\Structure\Models\Hub;
use Modules\Core\Structure\Models\Organisation;
use Modules\Core\Tests\Structure;

beforeEach(function (): void {
    Structure::seed($this);
    $this->travelTo(now()->setTimezone('Africa/Johannesburg')->setTime(10, 0)->utc());
});

function hubPayload(array $overrides = []): array
{
    return [
        'code' => 'LP-POL-SES', 'name' => 'Seshego Hub', 'slug' => 'seshego', 'municipality_id' => Structure::city('LIM354')->id,
        'place_name' => 'Seshego', 'address' => '12 Main Road, Seshego', 'latitude' => -23.85, 'longitude' => 29.38,
        'phone' => '015 223 4455', 'email' => 'seshego@kasidigitalhub.co.za', 'status' => 'planned', 'package' => 'growth',
        'opening_hours' => ['mon-fri' => '08:00-17:00'], ...$overrides,
    ];
}

it('creates a hub with its package and a new place', function (): void {
    Console::as($this, 'operations_admin');

    $this->post('/admin/hubs', hubPayload())->assertSessionHasNoErrors();

    $hub = Hub::query()->where('code', 'LP-POL-SES')->firstOrFail();
    expect($hub->place->name)->toBe('Seshego')
        ->and($hub->phone)->toBe('+27152234455')
        ->and(app(HubEntitlements::class)->modulesFor($hub))->toEqualCanonicalizing(['HubOps', 'Work', 'Learn']);
});

it('validates hub details', function (): void {
    Console::as($this, 'operations_admin');

    $this->post('/admin/hubs', hubPayload(['code' => 'lower case', 'slug' => 'tsutsumani', 'latitude' => 10, 'municipality_id' => Structure::city('DC33')->id]))
        ->assertSessionHasErrors(['code', 'slug', 'latitude', 'municipality_id']);
});

it('changes a package and add-ons, and access follows immediately', function (): void {
    Console::as($this, 'operations_admin');
    $hub = Structure::hub('LP-TZA-NKO');
    $facilitator = Structure::personWith('hub_facilitator', Scope::hub($hub));
    expect(app(AccessResolver::class)->can($facilitator, 'Learn'))->toBeFalse();

    $this->post("/admin/hubs/{$hub->id}/modules", ['module' => 'Learn', 'enabled' => true])->assertSessionHasNoErrors();
    expect(app(AccessResolver::class)->can($facilitator, 'Learn'))->toBeTrue();

    $this->put("/admin/hubs/{$hub->id}", hubPayload(['code' => $hub->code, 'slug' => $hub->slug, 'name' => $hub->name, 'municipality_id' => $hub->municipality_id, 'status' => 'live', 'package' => 'full']))
        ->assertSessionHasNoErrors();
    expect($hub->refresh()->package)->toBe('full')
        ->and(app(AccessResolver::class)->can($facilitator, 'Connect'))->toBeTrue();
});

it('verifies an organisation and tells its members', function (): void {
    Console::as($this, 'operations_admin');
    $member = User::factory()->create();
    $org = Organisation::query()->create(['type' => 'employer', 'name' => 'Test Traders', 'verification_status' => 'pending']);
    $org->members()->attach($member->id);

    $this->post("/admin/organisations/{$org->id}/verify")->assertSessionHasErrors('checklist'); // checklist first
    foreach (['cipc_found', 'cipc_active', 'person_linked', 'phone_answered'] as $item) {
        $this->post("/admin/organisations/{$org->id}/checklist", ['item' => $item, 'checked' => true])->assertSessionHasNoErrors();
    }
    $this->post("/admin/organisations/{$org->id}/verify")->assertSessionHasNoErrors();

    expect($org->refresh()->verification_status)->toBe('verified')
        ->and(Update::query()->where('user_id', $member->id)->where('title', 'Test Traders is verified')->exists())->toBeTrue()
        ->and(PlatformEventRecord::query()->where('name', 'admin.organisation.verified')->exists())->toBeTrue();
});

it('rejects an organisation only with a reason', function (): void {
    Console::as($this, 'operations_admin');
    $org = Organisation::query()->create(['type' => 'partner', 'name' => 'Dodgy Loans', 'verification_status' => 'pending']);

    $this->post("/admin/organisations/{$org->id}/reject", [])->assertSessionHasErrors('reason');
    $this->post("/admin/organisations/{$org->id}/reject", ['reason' => 'Not registered with the NCR'])->assertSessionHasNoErrors();

    expect($org->refresh()->verification_status)->toBe('rejected');
});

it('adds members by phone number and lists them with masked numbers', function (): void {
    Console::as($this, 'operations_admin');
    $org = Structure::org('Baloyi Wholesalers');
    $person = User::factory()->create(['phone' => '+27724183390']);

    $this->post("/admin/organisations/{$org->id}/members", ['phone' => '0731111111'])->assertSessionHasErrors('phone');
    $this->post("/admin/organisations/{$org->id}/members", ['phone' => '072 418 3390', 'title' => 'HR manager'])->assertSessionHasNoErrors();

    $this->get("/admin/organisations/{$org->id}")->assertInertia(fn ($page) => $page->where('members.0.phone', '072 *** 3390')->where('members.0.title', 'HR manager'));
    expect($org->members()->whereKey($person->id)->exists())->toBeTrue();
});

it('creates organisations as pending', function (): void {
    Console::as($this, 'operations_admin');

    $this->post('/admin/organisations', ['name' => 'Giyani Bakers', 'type' => 'employer', 'contact_phone' => '0724183390'])->assertSessionHasNoErrors();

    expect(Organisation::query()->where('name', 'Giyani Bakers')->value('verification_status'))->toBe('pending');
});

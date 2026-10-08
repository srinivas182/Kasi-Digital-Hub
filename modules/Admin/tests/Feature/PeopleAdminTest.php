<?php

declare(strict_types=1);

use Modules\Admin\Tests\Console;
use Modules\Core\Identity\Drivers\LogSmsSender;
use Modules\Core\Identity\Models\AuditLog;
use Modules\Core\Identity\Models\User;
use Modules\Core\Platform\Models\PlatformEventRecord;
use Modules\Core\Structure\Models\RoleAssignment;
use Modules\Core\Tests\Helpers;
use Modules\Core\Tests\Structure;

beforeEach(function (): void {
    Structure::seed($this);
    $this->travelTo(now()->setTimezone('Africa/Johannesburg')->setTime(10, 0)->utc());
});

it('finds people by name or the last digits of their phone, with phones masked in the list', function (): void {
    Console::as($this, 'support_agent');
    User::factory()->create(['first_name' => 'Thandeka', 'last_name' => 'Ngubane', 'phone' => '+27724183390']);
    User::factory()->create(['first_name' => 'Sipho', 'last_name' => 'Dube']);

    $this->get('/admin/people?q=Thand')->assertInertia(fn ($page) => $page->has('people.data', 1)->where('people.data.0.phone', '072 *** 3390'));
    $this->get('/admin/people?q=3390')->assertInertia(fn ($page) => $page->has('people.data', 1)->where('people.data.0.name', 'Thandeka Ngubane'));
});

it('filters people by hub, role and status', function (): void {
    Console::as($this, 'super_admin');
    $hub = Structure::hub('GP-JHB-SOW');
    User::factory()->count(2)->create(['home_hub_id' => $hub->id]);
    User::factory()->create(['status' => User::STATUS_SUSPENDED]);

    $this->get("/admin/people?hub={$hub->id}")->assertInertia(fn ($page) => $page->has('people.data', 2));
    $this->get('/admin/people?status=suspended')->assertInertia(fn ($page) => $page->has('people.data', 1));
    $this->get('/admin/people?role=super_admin')->assertInertia(fn ($page) => $page->has('people.data', 1));
});

it('records every person page view in the audit log', function (): void {
    $admin = Console::as($this, 'support_agent');
    $person = User::factory()->create();

    $this->get("/admin/people/{$person->id}")->assertOk();

    expect(AuditLog::query()->where('event', 'admin.person_viewed')->where('user_id', $person->id)->value('actor_id'))->toBe($admin->id);
});

it('gives and removes roles with a reason, recorded in the audit log', function (): void {
    $admin = Console::as($this, 'operations_admin');
    $person = User::factory()->create();
    $hub = Structure::hub('LP-GIY-TSU');

    $this->post("/admin/people/{$person->id}/roles", ['role' => 'hub_facilitator', 'scope_id' => $hub->id])->assertSessionHasErrors('reason');
    $this->post("/admin/people/{$person->id}/roles", ['role' => 'hub_facilitator', 'scope_id' => $hub->id, 'reason' => 'New facilitator from October'])->assertSessionHasNoErrors();

    $assignment = RoleAssignment::query()->where('user_id', $person->id)->firstOrFail();
    expect($assignment->scope_id)->toBe($hub->id)
        ->and(AuditLog::query()->where('event', 'role.assigned')->where('user_id', $person->id)->first()->meta['reason'])->toBe('New facilitator from October')
        ->and(AuditLog::query()->where('event', 'role.assigned')->where('user_id', $person->id)->value('actor_id'))->toBe($admin->id);

    $this->delete("/admin/people/{$person->id}/roles/{$assignment->id}", ['reason' => 'Left the hub'])->assertSessionHasNoErrors();
    expect(RoleAssignment::query()->where('user_id', $person->id)->count())->toBe(0);
});

it('rejects a role without its scope', function (): void {
    Console::as($this, 'operations_admin');
    $person = User::factory()->create();

    $this->post("/admin/people/{$person->id}/roles", ['role' => 'hub_facilitator', 'reason' => 'Missing the hub'])->assertSessionHasErrors('role');
});

it('lets only super admins give national roles, and nobody change their own', function (): void {
    Console::as($this, 'operations_admin');
    $person = User::factory()->create();
    $this->post("/admin/people/{$person->id}/roles", ['role' => 'super_admin', 'reason' => 'Trying to escalate'])->assertForbidden();

    $super = Console::as($this, 'super_admin');
    $this->post("/admin/people/{$person->id}/roles", ['role' => 'support_agent', 'reason' => 'Joining support team'])->assertSessionHasNoErrors();
    $own = RoleAssignment::query()->where('user_id', $super->id)->firstOrFail();
    $this->delete("/admin/people/{$super->id}/roles/{$own->id}", ['reason' => 'Removing myself'])->assertForbidden();
});

it('suspends an account: signs them out everywhere, blocks sign-in and tells them by SMS', function (): void {
    Console::as($this, 'operations_admin');
    $person = User::factory()->create();
    $device = $person->devices()->create(['name' => 'Phone', 'session_id' => 'their-session']);

    $this->post("/admin/people/{$person->id}/suspend", ['reason' => 'x'])->assertSessionHasErrors('reason');
    $this->post("/admin/people/{$person->id}/suspend", ['reason' => 'Reported fake job adverts'])->assertSessionHasNoErrors();

    expect($person->refresh()->status)->toBe(User::STATUS_SUSPENDED)
        ->and($device->refresh()->revoked_at)->not->toBeNull()
        ->and(LogSmsSender::lastMessage($person->phone))->toContain('suspended')
        ->and(PlatformEventRecord::query()->where('name', 'admin.account.suspended')->where('user_id', $person->id)->exists())->toBeTrue();

    $this->post('/logout');
    Helpers::verifyPhone($this, $person->phone);
    $this->post('/login/pin', ['pin' => '24680'])->assertSessionHasErrors('pin');

    Console::as($this, 'operations_admin');
    $this->post("/admin/people/{$person->id}/reactivate", ['reason' => 'Investigation closed'])->assertSessionHasNoErrors();
    expect($person->refresh()->status)->toBe(User::STATUS_ACTIVE);
});

it('does not let support agents suspend accounts or give roles', function (): void {
    Console::as($this, 'support_agent');
    $person = User::factory()->create();

    $this->post("/admin/people/{$person->id}/suspend", ['reason' => 'Not allowed to do this'])->assertForbidden();
    $this->post("/admin/people/{$person->id}/roles", ['role' => 'job_seeker', 'reason' => 'Not allowed to do this'])->assertForbidden();
});

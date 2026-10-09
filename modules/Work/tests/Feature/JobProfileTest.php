<?php

declare(strict_types=1);

use Modules\Core\Documents\Models\Document;
use Modules\Core\Identity\Models\AuditLog;
use Modules\Core\Identity\Models\User;
use Modules\Core\Platform\Models\PlatformEventRecord;
use Modules\Core\Structure\Models\RoleAssignment;
use Modules\Core\Tests\Structure;
use Modules\HubOps\Tests\Staff;
use Modules\Work\Models\WorkExperience;
use Modules\Work\Models\WorkProfile;

beforeEach(fn () => Structure::seed($this));

function about(array $overrides = []): array
{
    return ['headline' => 'Friendly cashier', 'summary' => 'I am reliable and good with people.', 'drivers_licence' => 'none', 'own_transport' => false, ...$overrides];
}

it('creates the profile on first save and makes the person a job seeker', function (): void {
    $person = Staff::signIn($this, User::factory()->create());

    $this->get('/work/profile')->assertOk()->assertInertia(fn ($page) => $page->component('Work/Profile')->where('step', 'about'));
    $this->put('/work/profile/about', about())->assertRedirect('/work/profile?step=experience');

    expect(WorkProfile::query()->find($person->id)->headline)->toBe('Friendly cashier')
        ->and(RoleAssignment::query()->where('user_id', $person->id)->where('role', 'job_seeker')->exists())->toBeTrue();
});

it('keeps KasiWork for adults', function (): void {
    Staff::signIn($this, User::factory()->create(['age_band' => 'minor', 'date_of_birth' => now()->subYears(17)]));

    $this->get('/work/profile')->assertForbidden();
});

it('counts informal work as experience and tracks completeness', function (): void {
    $person = Staff::signIn($this, User::factory()->create());
    $this->put('/work/profile/about', about());

    $this->post('/work/experience', ['kind' => 'family_business', 'title' => 'Shop assistant', 'duration' => 'about 2 years', 'description' => 'I serve customers at my aunt\'s tuck shop'])->assertSessionHasNoErrors();
    $this->post('/work/experience', ['kind' => 'piece_work', 'title' => 'Gardener'])->assertSessionHasErrors('duration'); // dates or a duration
    $this->post('/work/experience', ['kind' => 'job', 'title' => 'Packer', 'started' => '2024-02', 'ended' => '2023-01'])->assertSessionHasErrors('ended');

    expect(WorkExperience::query()->where('user_id', $person->id)->count())->toBe(1)
        ->and(WorkProfile::query()->find($person->id)->completed_at)->toBeNull();

    $this->put('/work/profile/skills', ['skills' => ['Cash handling', 'Customer service', 'cash handling', 'Stock counting'], 'languages' => [['language' => 'Xitsonga', 'level' => 'fluent']]])->assertSessionHasNoErrors();

    $profile = WorkProfile::query()->find($person->id);
    expect($profile->completed_at)->not->toBeNull()->and($profile->completeness)->toBeGreaterThan(50)
        ->and(DB::table('work_skills')->where('user_id', $person->id)->count())->toBe(3) // duplicates ignored
        ->and(PlatformEventRecord::query()->where('name', 'work.profile.completed')->where('user_id', $person->id)->count())->toBe(1);
});

it('only links documents the person owns to their education', function (): void {
    $person = Staff::signIn($this, User::factory()->create());
    $someoneElse = Document::query()->create([
        'user_id' => User::factory()->create()->id, 'type' => 'matric_certificate', 'disk' => 'documents', 'path' => 'x.pdf', 'original_name' => 'x.pdf',
        'mime_type' => 'application/pdf', 'size_bytes' => 10, 'sha256' => str_repeat('a', 64), 'status' => 'verified',
    ]);

    $this->post('/work/education', ['kind' => 'matric', 'name' => 'Matric', 'year' => 2024, 'document_id' => $someoneElse->id])->assertSessionHasErrors('document_id');
    $this->post('/work/education', ['kind' => 'matric', 'name' => 'Matric', 'year' => 2024])->assertSessionHasNoErrors();
});

it('does not let people edit each other\'s experience', function (): void {
    $other = WorkExperience::query()->create(['user_id' => User::factory()->create()->id, 'kind' => 'job', 'title' => 'Driver', 'duration' => '1 year']);
    Staff::signIn($this, User::factory()->create());

    $this->put("/work/experience/{$other->id}", ['kind' => 'job', 'title' => 'Hacked', 'duration' => '1 year'])->assertNotFound();
    $this->delete("/work/experience/{$other->id}")->assertNotFound();
});

it('shows profile and CV steps on the hub home', function (): void {
    Staff::signIn($this, User::factory()->create());

    $this->get('/home')->assertInertia(fn ($page) => $page->where('steps', fn ($steps) => collect($steps)->pluck('key')->contains('work_profile') && collect($steps)->pluck('key')->contains('work_cv')));
});

it('lets a facilitator build the profile for the person they are helping, recorded as assisted', function (): void {
    $facilitator = Staff::as($this, 'hub_facilitator');
    $person = User::factory()->create();
    $this->post('/hub-ops/check-in', ['person_id' => $person->id, 'purpose' => 'jobs']);
    $this->post("/hub-ops/assist/{$person->id}");

    $this->get('/work/profile')->assertInertia(fn ($page) => $page->where('person.assisted', true)->where('person.name', $person->fullName()));
    $this->put('/work/profile/about', about())->assertSessionHasNoErrors();

    expect(WorkProfile::query()->find($person->id))->not->toBeNull()
        ->and(WorkProfile::query()->find($facilitator->id))->toBeNull()
        ->and(AuditLog::query()->where('event', 'work.profile_saved')->where('user_id', $person->id)->value('actor_id'))->toBe($facilitator->id);
});

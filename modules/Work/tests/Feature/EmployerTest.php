<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Identity\Models\User;
use Modules\Core\Structure\Models\Organisation;
use Modules\Core\Structure\Models\RoleAssignment;
use Modules\Core\Tests\Structure;
use Modules\HubOps\Tests\Staff;

beforeEach(function (): void {
    Structure::seed($this);
    Storage::fake('documents');
});

function business(array $overrides = []): array
{
    return [
        'name' => 'Giyani Bakery (Pty) Ltd', 'registration_number' => '2021/123456/07', 'sector' => 'retail', 'size_band' => '2-10',
        'municipality_id' => Structure::city('LIM331')->id, 'confirm' => true,
        'certificate' => UploadedFile::fake()->createWithContent('cipc.pdf', '%PDF-1.4 cipc'), ...$overrides,
    ];
}

it('registers a business with its CIPC certificate, waiting for verification', function (): void {
    $owner = Staff::signIn($this, User::factory()->create());

    $this->get('/work/employer')->assertRedirect('/work/employer/register');
    $this->post('/work/employer/register', business())->assertRedirect('/work/employer')->assertSessionHasNoErrors();

    $org = Organisation::query()->where('name', 'Giyani Bakery (Pty) Ltd')->sole();
    expect($org->type)->toBe('employer')->and($org->verification_status)->toBe('pending')->and($org->community)->toBeFalse()
        ->and($org->registration_document_id)->not->toBeNull()
        ->and(RoleAssignment::query()->where('user_id', $owner->id)->where('role', 'employer_admin')->value('scope_id'))->toBe($org->id)
        ->and($org->checklistItems())->toBe(['cipc_found', 'cipc_active', 'person_linked', 'phone_answered']);
});

it('checks the CIPC number and certificate', function (): void {
    Staff::signIn($this, User::factory()->create());

    $this->post('/work/employer/register', business(['registration_number' => '12345', 'certificate' => null]))->assertSessionHasErrors(['registration_number', 'certificate']);
});

it('lets community businesses register without CIPC, with their own checklist', function (): void {
    Staff::signIn($this, User::factory()->create());

    $this->post('/work/employer/register', business(['community' => true, 'name' => 'Mama Rose Kitchen', 'registration_number' => null, 'certificate' => null]))->assertSessionHasNoErrors();

    $org = Organisation::query()->where('name', 'Mama Rose Kitchen')->sole();
    expect($org->community)->toBeTrue()->and($org->checklistItems())->toContain('hub_visit');
});

it('keeps employer screens for adults and members of the business', function (): void {
    Staff::signIn($this, User::factory()->create(['age_band' => 'minor', 'date_of_birth' => now()->subYears(17)]));
    $this->get('/work/employer/register')->assertForbidden();

    Staff::signIn($this, User::factory()->create());
    $this->get('/work/employer/listings/create')->assertForbidden(); // no business yet
});

it('lets the admin add and remove recruiters by phone number', function (): void {
    $owner = Staff::signIn($this, User::factory()->create());
    $this->post('/work/employer/register', business());
    $org = Organisation::query()->where('name', 'Giyani Bakery (Pty) Ltd')->sole();
    $recruiter = User::factory()->create(['phone' => '+27724183390']);

    $this->post('/work/employer/team', ['phone' => '0731111111'])->assertSessionHasErrors('phone');
    $this->post('/work/employer/team', ['phone' => '072 418 3390'])->assertSessionHasNoErrors();
    expect(RoleAssignment::query()->where('user_id', $recruiter->id)->where('role', 'recruiter')->value('scope_id'))->toBe($org->id);

    $this->delete("/work/employer/team/{$recruiter->id}")->assertSessionHasNoErrors();
    expect(RoleAssignment::query()->where('user_id', $recruiter->id)->exists())->toBeFalse();
    $this->delete("/work/employer/team/{$owner->id}")->assertForbidden();
});

<?php

declare(strict_types=1);

use Modules\Core\Identity\Drivers\LogSmsSender;
use Modules\Core\Identity\Models\AuditLog;
use Modules\Core\Identity\Models\Consent;
use Modules\Core\Identity\Models\User;
use Modules\Core\Platform\Models\PlatformEventRecord;
use Modules\Core\Tests\Helpers;
use Modules\Core\Tests\Structure;
use Modules\HubOps\Models\HubVisit;
use Modules\HubOps\Tests\Staff;

beforeEach(function (): void {
    Structure::seed($this);
    $this->travelTo(now()->setTimezone('Africa/Johannesburg')->setTime(10, 0)->utc());
    $this->facilitator = Staff::as($this, 'hub_facilitator');
});

function assistedDetails(array $overrides = []): array
{
    return [
        'first_name' => 'Hlulani', 'last_name' => 'Maluleke', 'date_of_birth' => '2001-05-14',
        'pin' => '47291', 'pin_confirmation' => '47291', 'consents' => ['job_matching' => true],
        'visit_purpose' => 'jobs', 'accept_terms' => true, 'present' => true, ...$overrides,
    ];
}

it('registers a person at the desk: their phone, their PIN, consent recorded as assisted', function (): void {
    $this->post('/hub-ops/register/code', ['phone' => '072 418 3390'])->assertRedirect('/hub-ops/register/details');
    $code = Helpers::lastCode('+27724183390');

    $this->post('/hub-ops/register', assistedDetails(['code' => $code]))->assertRedirect('/hub-ops/check-in')->assertSessionHasNoErrors();

    $person = User::query()->where('phone', '+27724183390')->sole();
    expect($person->home_hub_id)->toBe(Structure::hub('LP-GIY-TSU')->id)
        ->and(Hash::check('47291', $person->pin))->toBeTrue()
        ->and(Consent::query()->where('user_id', $person->id)->where('purpose', 'platform')->value('channel'))->toBe('assisted')
        ->and(Consent::query()->where('user_id', $person->id)->where('purpose', 'job_matching')->value('assisted_by'))->toBe($this->facilitator->id)
        ->and(HubVisit::query()->where('user_id', $person->id)->value('method'))->toBe('assisted')
        ->and(AuditLog::query()->where('event', 'signup.assisted')->value('actor_id'))->toBe($this->facilitator->id)
        ->and(PlatformEventRecord::query()->where('name', 'hubops.assisted.registration')->exists())->toBeTrue()
        ->and(PlatformEventRecord::query()->where('name', 'core.user.registered')->where('user_id', $person->id)->exists())->toBeTrue()
        ->and(LogSmsSender::lastMessage('+27724183390'))->toContain($this->facilitator->fullName());

    // The PIN is never written to the audit log.
    expect(AuditLog::query()->get()->contains(fn (AuditLog $log) => str_contains((string) json_encode($log->meta), '47291')))->toBeFalse();
});

it('needs the code from the person\'s phone', function (): void {
    $this->post('/hub-ops/register/code', ['phone' => '0724183390']);

    $this->post('/hub-ops/register', assistedDetails(['code' => '000000']))->assertSessionHasErrors('code');
    expect(User::query()->where('phone', '+27724183390')->exists())->toBeFalse();
});

it('sends people who already have an account to check-in instead', function (): void {
    User::factory()->create(['phone' => '+27724183390']);

    $this->post('/hub-ops/register/code', ['phone' => '0724183390'])->assertSessionHasErrors('phone');
});

it('asks under-18s to register with a guardian on their own phone', function (): void {
    $this->post('/hub-ops/register/code', ['phone' => '0724183390']);

    $this->post('/hub-ops/register', assistedDetails(['code' => Helpers::lastCode('+27724183390'), 'date_of_birth' => now()->subYears(17)->toDateString()]))
        ->assertSessionHasErrors('date_of_birth');
});

it('requires the person to be present and to accept the terms', function (): void {
    $this->post('/hub-ops/register/code', ['phone' => '0724183390']);

    $this->post('/hub-ops/register', assistedDetails(['code' => Helpers::lastCode('+27724183390'), 'present' => false, 'accept_terms' => false]))
        ->assertSessionHasErrors(['present', 'accept_terms']);
});

it('expires the registration after 15 minutes', function (): void {
    $this->post('/hub-ops/register/code', ['phone' => '0724183390']);
    $this->travel(16)->minutes();
    Staff::signIn($this, $this->facilitator);

    $this->get('/hub-ops/register/details')->assertRedirect('/hub-ops/register');
});

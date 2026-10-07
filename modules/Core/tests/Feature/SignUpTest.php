<?php

declare(strict_types=1);

use Modules\Core\Database\Seeders\ConsentDocumentSeeder;
use Modules\Core\Identity\Models\AuditLog;
use Modules\Core\Identity\Models\Consent;
use Modules\Core\Identity\Models\User;
use Modules\Core\Identity\Models\UserDevice;
use Modules\Core\Identity\Services\DeviceManager;
use Modules\Core\Tests\Helpers;

beforeEach(fn () => $this->seed(ConsentDocumentSeeder::class));

it('signs up an adult with phone, code, PIN, age, name and consent', function (): void {
    $response = Helpers::signUp($this, '+27724183390');

    $response->assertRedirect('/home');
    $user = User::query()->where('phone', '+27724183390')->firstOrFail();

    $this->assertAuthenticatedAs($user);
    expect($user->age_band)->toBe('adult')
        ->and($user->status)->toBe(User::STATUS_ACTIVE)
        ->and($user->pin)->not->toBe('24680')
        ->and($user->phone_verified_at)->not->toBeNull();

    $purposes = Consent::query()->where('user_id', $user->id)->pluck('granted', 'purpose')->all();
    // Every choice shown is recorded - including an explicit "no" - as evidence of the decision.
    expect($purposes)->toMatchArray(['platform' => true, 'job_matching' => true, 'marketing' => false])
        ->and($purposes)->not->toHaveKey('partner_sharing'); // not submitted = not recorded

    $platform = Consent::query()->where('user_id', $user->id)->where('purpose', 'platform')->first();
    expect($platform->document_versions)->toBe(['terms' => 1, 'privacy' => 1]);

    expect(UserDevice::query()->where('user_id', $user->id)->whereNotNull('remembered_until')->exists())->toBeTrue();
    $response->assertCookie(DeviceManager::COOKIE);
    expect(AuditLog::query()->where('event', 'signup.completed')->exists())->toBeTrue();
});

it('requires acceptance of the terms', function (): void {
    Helpers::signUp($this, '+27724183390', ['accept_terms' => false])->assertSessionHasErrors('accept_terms');
    $this->assertGuest();
});

it('rejects PINs that are too simple', function (string $pin): void {
    Helpers::signUp($this, '+27724183390', ['pin' => $pin, 'pin_confirmation' => $pin])->assertSessionHasErrors('pin');
})->with(['12345', '54321', '11111', '1234', 'abcde']);

it('cannot reach sign-up without verifying the phone first', function (): void {
    $this->get('/signup')->assertRedirect('/login');
    $this->post('/signup', [])->assertRedirect('/login');
});

it('declines people under the minimum age', function (): void {
    Helpers::signUp($this, '+27724183390', ['date_of_birth' => now()->subYears(15)->toDateString()])
        ->assertRedirect('/signup/declined');

    expect(User::query()->count())->toBe(0);
    $this->assertGuest();
});

it('needs guardian consent for 16 and 17 year-olds', function (): void {
    Helpers::signUp($this, '+27724183390', ['date_of_birth' => now()->subYears(17)->toDateString()])
        ->assertRedirect('/signup/guardian');

    $user = User::query()->firstOrFail();
    expect($user->status)->toBe(User::STATUS_PENDING_GUARDIAN)->and($user->age_band)->toBe('minor');
    $this->assertGuest();

    $this->post('/signup/guardian', ['guardian_name' => 'Grace Mabunda', 'guardian_phone' => '0731234567', 'relationship' => 'parent'])
        ->assertSessionHasNoErrors();

    $this->post('/signup/guardian/code', ['code' => '000000'])->assertSessionHasErrors('code');
    $this->post('/signup/guardian/code', ['code' => Helpers::lastCode('+27731234567')])->assertRedirect('/home');

    $user->refresh();
    expect($user->status)->toBe(User::STATUS_ACTIVE)
        ->and($user->guardianConsents()->whereNotNull('verified_at')->exists())->toBeTrue();
    $this->assertAuthenticatedAs($user);
});

it("does not accept the minor's own number as the guardian's", function (): void {
    Helpers::signUp($this, '+27724183390', ['date_of_birth' => now()->subYears(16)->toDateString()]);

    $this->post('/signup/guardian', ['guardian_name' => 'Me', 'guardian_phone' => '0724183390', 'relationship' => 'parent'])
        ->assertSessionHasErrors('guardian_phone');
});

it('keeps minors out of adults-only portals', function (): void {
    Route::middleware(['web', 'auth', 'adult'])->get('/_test/adults-only', fn () => 'ok');
    $minor = User::factory()->minor()->create();

    $this->actingAs($minor)->withSession(['device_id' => UserDevice::query()->create(['user_id' => $minor->id, 'name' => 'Test'])->id])
        ->get('/_test/adults-only')->assertForbidden();
});

<?php

declare(strict_types=1);

use Modules\Core\Database\Seeders\ConsentDocumentSeeder;
use Modules\Core\Identity\Models\User;
use Modules\Core\Tests\Helpers;
use PragmaRX\Google2FA\Google2FA;

beforeEach(fn () => $this->seed(ConsentDocumentSeeder::class));

function staffToPinStep(object $test, User $user): void
{
    Helpers::verifyPhone($test, $user->phone);
}

it('makes staff set up an authenticator, shows backup codes once, then signs in', function (): void {
    $user = User::factory()->staff()->create();
    staffToPinStep($this, $user);

    $this->post('/login/pin', ['pin' => '24680'])->assertRedirect('/two-factor/setup');
    $this->assertGuest();

    $this->get('/two-factor/setup')->assertOk();
    $secret = $user->refresh()->twoFactor->secret;

    $this->post('/two-factor/setup', ['code' => '000000'])->assertSessionHasErrors('code');
    $this->post('/two-factor/setup', ['code' => app(Google2FA::class)->getCurrentOtp($secret)])->assertRedirect('/two-factor/recovery-codes');
    $this->get('/two-factor/recovery-codes')->assertOk()->assertInertia(fn ($page) => $page->has('codes', 8));

    $this->post('/two-factor/finish')->assertRedirect('/home');
    $this->assertAuthenticatedAs($user);
});

it('asks staff for the authenticator code on every sign-in, and backup codes work once', function (): void {
    $user = User::factory()->staff()->create();
    $google2fa = app(Google2FA::class);
    $secret = $google2fa->generateSecretKey(32);
    $user->twoFactor()->create(['secret' => $secret, 'recovery_codes' => [Hash::make('ABCDE-FGHIJ')], 'confirmed_at' => now()]);

    staffToPinStep($this, $user);
    $this->post('/login/pin', ['pin' => '24680'])->assertRedirect('/two-factor/challenge');
    $this->post('/two-factor/challenge', ['code' => '000000'])->assertSessionHasErrors('code');
    $this->post('/two-factor/challenge', ['code' => 'abcde-fghij'])->assertRedirect('/home');
    $this->assertAuthenticatedAs($user);

    $this->post('/logout');
    staffToPinStep($this, $user);
    $this->post('/login/pin', ['pin' => '24680']);
    $this->post('/two-factor/challenge', ['code' => 'ABCDE-FGHIJ'])->assertSessionHasErrors('code');
    $this->post('/two-factor/challenge', ['code' => $google2fa->getCurrentOtp($secret)])->assertRedirect('/home');
});

it('does not let anyone reach the second step without a correct PIN', function (): void {
    $this->get('/two-factor/challenge')->assertRedirect('/login');
    $this->get('/two-factor/setup')->assertRedirect('/login');
});

it('uses the shorter idle timeout for staff', function (): void {
    $user = User::factory()->staff()->create();
    $secret = app(Google2FA::class)->generateSecretKey(32);
    $user->twoFactor()->create(['secret' => $secret, 'recovery_codes' => [], 'confirmed_at' => now()]);
    staffToPinStep($this, $user);
    $this->post('/login/pin', ['pin' => '24680']);
    $this->post('/two-factor/challenge', ['code' => app(Google2FA::class)->getCurrentOtp($secret)]);

    $this->travel(31)->minutes();
    $this->get('/home')->assertRedirect('/login?expired=1');
    $this->assertGuest();
});

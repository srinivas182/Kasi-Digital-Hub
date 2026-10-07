<?php

declare(strict_types=1);

use Modules\Core\Database\Seeders\ConsentDocumentSeeder;
use Modules\Core\Identity\Drivers\LogSmsSender;
use Modules\Core\Identity\Models\AuditLog;
use Modules\Core\Identity\Models\User;
use Modules\Core\Identity\Services\DeviceManager;
use Modules\Core\Tests\Helpers;

beforeEach(fn () => $this->seed(ConsentDocumentSeeder::class));

function rememberedDeviceCookie(object $test, User $user): string
{
    $test->post('/login', ['phone' => $user->phone]);
    $test->post('/login/code', ['code' => Helpers::lastCode($user->phone)]);
    $response = $test->post('/login/pin', ['pin' => '24680', 'remember' => true]);
    $test->post('/logout');

    return (string) $response->getCookie(DeviceManager::COOKIE)?->getValue();
}

it('does not reveal whether a number is registered', function (): void {
    $user = User::factory()->create();

    $this->post('/login', ['phone' => $user->phone])->assertRedirect('/login/code');
    $this->post('/login', ['phone' => '0731112222'])->assertRedirect('/login/code');
});

it('signs in on a new device with code then PIN', function (): void {
    $user = User::factory()->create();

    Helpers::verifyPhone($this, $user->phone)->assertRedirect('/login/pin');
    $this->post('/login/pin', ['pin' => '24680'])->assertRedirect('/home');

    $this->assertAuthenticatedAs($user);
});

it('signs in with phone and PIN only on a remembered device - no SMS', function (): void {
    $user = User::factory()->create();
    $token = rememberedDeviceCookie($this, $user);
    cache()->flush();

    $this->withCookie(DeviceManager::COOKIE, $token)->post('/login', ['phone' => $user->phone])->assertRedirect('/login/pin');
    expect(LogSmsSender::lastMessage($user->phone))->toBeNull();

    $this->withCookie(DeviceManager::COOKIE, $token)->post('/login/pin', ['pin' => '24680'])->assertRedirect('/home');
    $this->assertAuthenticatedAs($user);
});

it('locks the PIN after 5 wrong tries', function (): void {
    $user = User::factory()->create();
    Helpers::verifyPhone($this, $user->phone);

    foreach (range(1, 4) as $try) {
        $this->post('/login/pin', ['pin' => '13579'])->assertSessionHasErrors('pin');
    }
    $this->post('/login/pin', ['pin' => '13579'])->assertSessionHasErrors('pin');

    expect($user->refresh()->isPinLocked())->toBeTrue();
    $this->post('/login/pin', ['pin' => '24680'])->assertSessionHasErrors('pin');
    $this->assertGuest();
    expect(AuditLog::query()->where('event', 'auth.pin_locked')->exists())->toBeTrue();
});

it('resets a forgotten PIN with an SMS code', function (): void {
    $user = User::factory()->create();
    Helpers::verifyPhone($this, $user->phone);

    $this->post('/login/forgot')->assertRedirect('/login/code');
    $this->post('/login/code', ['code' => Helpers::lastCode($user->phone)])->assertRedirect('/login/new-pin');
    $this->post('/login/new-pin', ['pin' => '86420', 'pin_confirmation' => '86420'])->assertRedirect('/home');

    $this->assertAuthenticatedAs($user);
    $this->post('/logout');
    Helpers::verifyPhone($this, $user->phone);
    $this->post('/login/pin', ['pin' => '86420'])->assertRedirect('/home');
});

it('blocks suspended accounts', function (): void {
    $user = User::factory()->create(['status' => User::STATUS_SUSPENDED]);
    Helpers::verifyPhone($this, $user->phone);

    $this->post('/login/pin', ['pin' => '24680'])->assertSessionHasErrors('pin');
    $this->assertGuest();
});

it('sends a security alert when signing in from a new device', function (): void {
    $user = User::factory()->create(['last_login_at' => now()->subDay()]);
    Helpers::verifyPhone($this, $user->phone);
    $this->post('/login/pin', ['pin' => '24680']);

    expect(LogSmsSender::lastMessage($user->phone))->toContain('New sign-in');
});

it('cannot skip steps', function (): void {
    $this->get('/login/pin')->assertRedirect('/login');
    $this->get('/login/code')->assertRedirect('/login');
    $this->post('/login/pin', ['pin' => '24680'])->assertRedirect('/login');
    $this->get('/login/new-pin')->assertRedirect('/login');
});

it('signs out', function (): void {
    Helpers::signedIn($this);

    $this->post('/logout')->assertRedirect('/login');
    $this->assertGuest();
});

it('never writes codes or PINs to the audit log', function (): void {
    $user = User::factory()->create();
    Helpers::verifyPhone($this, $user->phone);
    $this->post('/login/pin', ['pin' => '24680']);

    $json = AuditLog::query()->get()->toJson();
    expect($json)->not->toContain('24680');
});

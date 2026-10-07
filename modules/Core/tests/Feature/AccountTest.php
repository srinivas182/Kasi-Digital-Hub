<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Modules\Core\Database\Seeders\ConsentDocumentSeeder;
use Modules\Core\Identity\Drivers\LogSmsSender;
use Modules\Core\Identity\Models\Consent;
use Modules\Core\Identity\Models\ConsentDocument;
use Modules\Core\Identity\Models\User;
use Modules\Core\Identity\Models\UserDevice;
use Modules\Core\Identity\Services\ConsentService;
use Modules\Core\Tests\Helpers;

beforeEach(function (): void {
    $this->seed(ConsentDocumentSeeder::class);
    $this->user = User::factory()->create();
    app(ConsentService::class)->record($this->user, ['platform' => true]);
    Helpers::signedIn($this, $this->user);
});

it('shows the account page', function (): void {
    $this->get('/account')->assertOk()->assertInertia(fn ($page) => $page
        ->component('Core/Account/Index')
        ->where('profile.phone', $this->user->phone)
        ->has('devices', 1)
        ->where('devices.0.current', true)
        ->has('consents', 6));
});

it('updates the profile and sends an email confirmation link', function (): void {
    Mail::fake();

    $this->put('/account/profile', [
        'first_name' => 'Thandeka', 'last_name' => 'Mabasa', 'preferred_name' => 'Thandi',
        'preferred_locale' => 'en', 'email' => 'thandi@example.co.za',
    ])->assertSessionHasNoErrors();

    $user = $this->user->refresh();
    expect($user->first_name)->toBe('Thandeka')->and($user->email_verified_at)->toBeNull();

    $url = URL::temporarySignedRoute('account.email.verify', now()->addDay(), ['id' => $user->id, 'hash' => sha1('thandi@example.co.za')]);
    $this->get($url)->assertRedirect('/account');
    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

it('rejects email confirmation links that are tampered with', function (): void {
    $this->user->forceFill(['email' => 'a@example.co.za'])->save();
    $this->get("/account/email/verify/{$this->user->id}/".sha1('a@example.co.za'))->assertForbidden();
});

it('changes the PIN only with the current PIN, and alerts the owner', function (): void {
    $this->put('/account/pin', ['current_pin' => '13579', 'pin' => '86420', 'pin_confirmation' => '86420'])->assertSessionHasErrors('current_pin');
    $this->put('/account/pin', ['current_pin' => '24680', 'pin' => '86420', 'pin_confirmation' => '86420'])->assertSessionHasNoErrors();

    expect(Hash::check('86420', $this->user->refresh()->pin))->toBeTrue()
        ->and(LogSmsSender::lastMessage($this->user->phone))->toContain('PIN was changed');
});

it('changes the phone number with a code to the new number and the current PIN', function (): void {
    $old = $this->user->phone;
    $this->post('/account/phone', ['phone' => '0731234567'])->assertSessionHasNoErrors();
    $code = Helpers::lastCode('+27731234567');

    $this->post('/account/phone/verify', ['code' => $code, 'current_pin' => '13579'])->assertSessionHasErrors('current_pin');
    $this->post('/account/phone/verify', ['code' => $code, 'current_pin' => '24680'])->assertSessionHasNoErrors();

    expect($this->user->refresh()->phone)->toBe('+27731234567')
        ->and(LogSmsSender::lastMessage($old))->toContain('phone number was changed');
});

it('signs out another device remotely', function (): void {
    $other = UserDevice::query()->create(['user_id' => $this->user->id, 'name' => 'Chrome on Android', 'session_id' => 'other-session']);

    $this->delete("/account/devices/{$other->id}")->assertSessionHasNoErrors();
    expect($other->refresh()->revoked_at)->not->toBeNull();
});

it("can't sign out someone else's device", function (): void {
    $device = UserDevice::query()->create(['user_id' => User::factory()->create()->id, 'name' => 'X']);
    $this->delete("/account/devices/{$device->id}")->assertNotFound();
});

it('ends this session when its device is signed out elsewhere', function (): void {
    UserDevice::query()->where('user_id', $this->user->id)->update(['revoked_at' => now()]);

    $this->get('/home')->assertRedirect('/login');
    $this->assertGuest();
});

it('keeps a consent history when choices change', function (): void {
    $this->put('/account/consents', ['consents' => ['marketing' => true, 'platform' => false]])->assertSessionHasNoErrors();
    $this->put('/account/consents', ['consents' => ['marketing' => false]])->assertSessionHasNoErrors();

    $history = Consent::query()->where('user_id', $this->user->id)->where('purpose', 'marketing')->orderBy('created_at')->orderBy('id')->pluck('granted')->all();
    expect($history)->toBe([true, false])
        ->and(app(ConsentService::class)->state($this->user)['platform'])->toBeTrue(); // required purpose not changed here
});

it('asks everyone to accept new terms before continuing', function (): void {
    ConsentDocument::query()->create(['key' => 'terms', 'version' => 2, 'title' => 'Terms of use', 'summary' => 'Updated', 'body' => 'v2', 'published_at' => now()->subMinute()]);

    $this->get('/home')->assertRedirect('/consents/review');
    $this->post('/consents/review', ['accept_terms' => true])->assertRedirect('/home');
    $this->get('/home')->assertOk();
});

it('records an account deletion request', function (): void {
    $this->post('/account/deletion', ['confirm' => false])->assertSessionHasErrors('confirm');
    $this->post('/account/deletion', ['confirm' => true])->assertSessionHasNoErrors();

    expect($this->user->refresh()->status)->toBe(User::STATUS_DELETION_REQUESTED)
        ->and($this->user->deletion_requested_at)->not->toBeNull();
});

it('signs out citizens after 2 hours of inactivity', function (): void {
    $this->travel(121)->minutes();
    $this->get('/home')->assertRedirect('/login?expired=1');
});

it('shows the hub home to signed-in users and the public legal pages to everyone', function (): void {
    $this->get('/home')->assertOk()->assertInertia(fn ($page) => $page->component('Hub/Home')->where('auth.user.id', $this->user->id));
    $this->post('/logout');
    $this->get('/home')->assertRedirect('/login');
    $this->get('/legal/terms')->assertOk()->assertInertia(fn ($page) => $page->component('Core/Legal')->where('version', 1));
});

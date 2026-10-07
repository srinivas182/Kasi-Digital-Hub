<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Modules\Core\Identity\Models\OtpChallenge;
use Modules\Core\Identity\Services\OtpService;
use Modules\Core\Tests\Helpers;

it('only sends codes to South African mobile numbers', function (string $number): void {
    $this->post('/login', ['phone' => $number])->assertSessionHasErrors('phone');
    expect(OtpChallenge::query()->count())->toBe(0);
})->with([
    'landline' => '011 123 4567',
    'premium' => '0861 234 567',
    'toll free' => '0800 123 456',
    'too short' => '072 123',
    'foreign' => '+44 7911 123456',
]);

it('limits codes per number to 3 in 15 minutes', function (): void {
    foreach (range(1, 3) as $i) {
        $this->post('/login', ['phone' => '0724183390'])->assertRedirect('/login/code');
    }

    $this->post('/login', ['phone' => '0724183390'])->assertSessionHasErrors('phone');
    expect(OtpChallenge::query()->count())->toBe(3);
});

it('expires codes after 5 minutes', function (): void {
    $this->post('/login', ['phone' => '0724183390']);
    $code = Helpers::lastCode('+27724183390');

    $this->travel(6)->minutes();
    $this->post('/login/code', ['code' => $code])->assertSessionHasErrors('code');
});

it('allows only 5 attempts per code', function (): void {
    $this->post('/login', ['phone' => '0724183390']);
    $code = Helpers::lastCode('+27724183390');

    foreach (range(1, 5) as $i) {
        $this->post('/login/code', ['code' => '000000'])->assertSessionHasErrors('code');
    }

    $this->post('/login/code', ['code' => $code])->assertSessionHasErrors('code');
});

it('accepts each code only once and stores only a hash', function (): void {
    $service = app(OtpService::class);
    $service->request('+27724183390', 'login', '127.0.0.1', 'device');
    $code = Helpers::lastCode('+27724183390');

    expect(OtpChallenge::query()->first()->code_hash)->not->toContain($code)
        ->and($service->verify('+27724183390', 'login', $code))->toBeTrue()
        ->and($service->verify('+27724183390', 'login', $code))->toBeFalse();
});

it('stops sending when the daily SMS cap is reached', function (): void {
    config(['kasi.identity.otp.daily_cap' => 2]);
    $service = app(OtpService::class);

    expect($service->request('+27724183390', 'login', '1.1.1.1', 'a')['sent'])->toBeTrue()
        ->and($service->request('+27731234567', 'login', '1.1.1.2', 'b')['sent'])->toBeTrue()
        ->and($service->request('+27821234567', 'login', '1.1.1.3', 'c'))->toMatchArray(['sent' => false, 'reason' => 'unavailable']);
});

it('blocks a number range showing signs of SMS pumping', function (): void {
    config(['kasi.identity.otp.per_range_hour' => 3]);
    $service = app(OtpService::class);

    foreach (['+27724180001', '+27724180002', '+27724180003'] as $i => $phone) {
        expect($service->request($phone, 'login', "10.0.0.{$i}", "d{$i}")['sent'])->toBeTrue();
    }

    expect($service->request('+27724180004', 'login', '10.0.0.9', 'd9')['sent'])->toBeFalse();
});

it('limits code requests per IP address', function (): void {
    config(['kasi.identity.otp.per_ip_hour' => 2]);
    $service = app(OtpService::class);

    $service->request('+27724183390', 'login', '9.9.9.9', 'x1');
    $service->request('+27731234567', 'login', '9.9.9.9', 'x2');

    expect($service->request('+27821234567', 'login', '9.9.9.9', 'x3')['reason'])->toBe('rate_limited');
    Cache::flush();
});

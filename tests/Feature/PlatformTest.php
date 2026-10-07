<?php

declare(strict_types=1);

it('passes the health check', function (): void {
    $this->get('/up')->assertOk();
});

it('reports the running version and enabled modules', function (): void {
    $this->getJson('/version')
        ->assertOk()
        ->assertJsonPath('name', 'KasiHub')
        ->assertJsonPath('version', trim((string) file_get_contents(base_path('VERSION'))))
        ->assertJsonCount(13, 'modules');
});

it('sends baseline security headers', function (): void {
    $this->get('/')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Content-Security-Policy-Report-Only');
});

it('uses demo drivers for every external service in tests', function (): void {
    expect(config('kasi.drivers'))->toMatchArray([
        'ai' => 'fake',
        'sms' => 'log',
        'whatsapp' => 'log',
        'payments' => 'fake',
    ]);
});

it('refuses demo reset when demo mode is off', function (): void {
    config(['kasi.demo.enabled' => false]);

    $this->artisan('kasi:demo:reset', ['--force' => true])->assertFailed();
});

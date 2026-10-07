<?php

declare(strict_types=1);

use App\Support\Modules\ModuleManifest;
use App\Support\Navigation\NavigationBuilder;
use Inertia\Testing\AssertableInertia as Assert;

it('shares platform, language and navigation props with every page', function (): void {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('platform.brand', 'KasiHub')
            ->where('i18n.locale', 'en')
            ->has('i18n.strings')
            ->has('i18n.languages', 3)
            ->has('navigation.portals', 11)
        );
});

it('orders portals: front doors, services, operations, national', function (): void {
    $order = ['front' => 0, 'service' => 1, 'operations' => 2, 'national' => 3];
    $ranks = array_map(static fn (array $portal): int => $order[$portal['group']], app(NavigationBuilder::class)->portals());
    $sorted = $ranks;
    sort($sorted);

    expect($ranks)->toBe($sorted)->and($ranks[0])->toBe(0);
});

it('has an English string for every navigation label', function (): void {
    $english = json_decode((string) file_get_contents(lang_path('en.json')), true);

    foreach (app(NavigationBuilder::class)->portals() as $portal) {
        foreach ($portal['items'] as $item) {
            expect($english)->toHaveKey($item['label']);
        }
    }
});

it('only uses translation keys that exist in English', function (string $locale): void {
    $english = json_decode((string) file_get_contents(lang_path('en.json')), true);
    $other = json_decode((string) file_get_contents(lang_path("{$locale}.json")), true);

    $keys = array_filter(array_keys($other), static fn (string $key): bool => ! str_starts_with($key, '_'));

    expect(array_diff($keys, array_keys($english)))->toBe([])
        ->and($other)->toHaveKey('_note');
})->with(['zu', 'ts']);

it('rejects nav items without a label and href', function (): void {
    ModuleManifest::fromArray([
        'name' => 'Broken', 'slug' => 'broken', 'title' => 'Broken', 'group' => 'service',
        'release' => 'r1', 'version' => '0.0.1', 'nav' => ['items' => [['href' => '/x']]],
    ], '/tmp/Broken');
})->throws(InvalidArgumentException::class, 'nav item');

it('serves the UI kit and every layout preview outside production', function (string $layout): void {
    $this->get('/ui-kit')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Core/UiKit/Index')->has('layouts', 6));
    $this->get("/ui-kit/layouts/{$layout}")->assertOk()->assertInertia(fn (Assert $page) => $page->component('Core/UiKit/Layout')->where('layout', $layout));
})->with(['public', 'auth', 'app', 'portal', 'console', 'kiosk']);

it('returns 404 for an unknown layout preview', function (): void {
    $this->get('/ui-kit/layouts/nope')->assertNotFound();
});

it('serves the offline page, web app manifest and service worker', function (): void {
    $this->get('/offline')->assertOk()->assertSee("You're offline", false);

    $manifest = json_decode((string) file_get_contents(public_path('manifest.webmanifest')), true);
    expect($manifest['short_name'])->toBe('KasiHub')
        ->and($manifest['icons'])->toHaveCount(3)
        ->and(public_path('sw.js'))->toBeFile();

    foreach ($manifest['icons'] as $icon) {
        expect(public_path(ltrim($icon['src'], '/')))->toBeFile();
    }
});

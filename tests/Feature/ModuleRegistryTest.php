<?php

declare(strict_types=1);

use App\Support\Modules\ModuleManifest;
use App\Support\Modules\ModuleRegistry;

const RELEASE_ONE_MODULES = [
    'Core', 'Site', 'Hub', 'HubOps', 'Work', 'Learn', 'Start', 'Connect',
    'Region', 'Funder', 'Partner', 'Admin', 'Commercial',
];

it('discovers every release 1 module with a valid manifest', function (): void {
    $modules = app(ModuleRegistry::class)->all();

    expect(array_keys($modules))->toEqualCanonicalizing(RELEASE_ONE_MODULES);
});

it('orders modules so dependencies come first', function (): void {
    $names = array_keys(app(ModuleRegistry::class)->all());

    expect($names[0])->toBe('Core')
        ->and(array_search('HubOps', $names, true))->toBeLessThan(array_search('Region', $names, true));
});

it('gives every module an autoload mapping and the standard folders', function (string $name): void {
    $composer = json_decode((string) file_get_contents(base_path('composer.json')), true);

    expect($composer['autoload']['psr-4'])->toHaveKey("Modules\\{$name}\\")
        ->and($composer['autoload-dev']['psr-4'])->toHaveKey("Modules\\{$name}\\Tests\\")
        ->and(is_dir(base_path("modules/{$name}/src")))->toBeTrue()
        ->and(is_dir(base_path("modules/{$name}/database/migrations")))->toBeTrue()
        ->and(is_dir(base_path("modules/{$name}/resources/js/Pages")))->toBeTrue()
        ->and(is_dir(base_path("modules/{$name}/tests")))->toBeTrue();
})->with(RELEASE_ONE_MODULES);

it('rejects a manifest with an invalid group', function (): void {
    ModuleManifest::fromArray([
        'name' => 'Broken', 'slug' => 'broken', 'title' => 'Broken', 'group' => 'nope',
        'release' => 'r1', 'version' => '0.0.1',
    ], '/tmp/Broken');
})->throws(InvalidArgumentException::class, 'invalid group');

it('rejects a manifest whose folder does not match its name', function (): void {
    ModuleManifest::fromArray([
        'name' => 'Work', 'slug' => 'work', 'title' => 'KasiWork', 'group' => 'service',
        'release' => 'r1', 'version' => '0.0.1',
    ], '/tmp/NotWork');
})->throws(InvalidArgumentException::class, 'folder named');

it('detects circular dependencies', function (): void {
    $dir = sys_get_temp_dir().'/kasi-modules-'.uniqid();
    foreach (['A' => ['B'], 'B' => ['A']] as $name => $deps) {
        mkdir("{$dir}/{$name}", 0777, true);
        file_put_contents("{$dir}/{$name}/module.json", json_encode([
            'name' => $name, 'slug' => strtolower($name), 'title' => $name, 'group' => 'service',
            'release' => 'r1', 'version' => '0.0.1', 'depends_on' => $deps,
        ]));
    }

    (new ModuleRegistry($dir))->all();
})->throws(InvalidArgumentException::class, 'Circular module dependency');

it('can switch a module off via config', function (): void {
    $registry = new ModuleRegistry(base_path('modules'), ['Connect']);

    expect($registry->isEnabled('Connect'))->toBeFalse()
        ->and($registry->isEnabled('Work'))->toBeTrue()
        ->and($registry->all())->toHaveKey('Connect');
});

it('has a page file for every Inertia page a module renders', function (): void {
    // Module pages are resolved from modules/<Module>/resources/js/Pages (see resolvePage.ts).
    expect(base_path('modules/Site/resources/js/Pages/Home.tsx'))->toBeFile();
});

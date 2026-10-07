<?php

declare(strict_types=1);

/*
| Architecture rules (ADR-005). Modules may depend on the shared kernel
| (App\ and Modules\Core) but never on another portal's internals - portals talk
| through contracts and events only.
*/

arch('platform code uses strict types')
    ->expect(['App', 'Modules'])
    ->toUseStrictTypes();

arch('no debugging helpers are left in code')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'print_r', 'die'])
    ->not->toBeUsed();

arch('application support code is final')
    ->expect('App\Support')
    ->classes()
    ->toBeFinal();

$portals = ['Site', 'Hub', 'HubOps', 'Work', 'Learn', 'Start', 'Connect', 'Region', 'Funder', 'Partner', 'Admin', 'Commercial'];

foreach ($portals as $module) {
    $others = array_map(
        static fn (string $other): string => "Modules\\{$other}",
        array_values(array_filter($portals, static fn (string $other): bool => $other !== $module)),
    );

    arch("{$module} does not reach into other portals")
        ->expect("Modules\\{$module}")
        ->not->toUse($others);
}

arch('the shared kernel does not depend on portals')
    ->expect('Modules\Core')
    ->not->toUse(array_map(static fn (string $m): string => "Modules\\{$m}", $portals));

/*
| Services must not keep the HTTP request from their constructor: controllers are cached
| per route and Octane (S22) reuses services across requests, so a stored request goes stale.
| Resolve it per call with app('request') instead.
*/
test('services never store the request from their constructor', function (): void {
    $offenders = [];
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path('modules')));

    foreach ($files as $file) {
        if (! $file->isFile() || ! str_ends_with($file->getPathname(), '.php') || ! str_contains($file->getPathname(), '/src/')) {
            continue;
        }

        $code = (string) file_get_contents($file->getPathname());
        if (preg_match('/function __construct\([^)]*Request \$/s', $code) === 1) {
            $offenders[] = str_replace(base_path().'/', '', $file->getPathname());
        }
    }

    expect($offenders)->toBe([]);
});

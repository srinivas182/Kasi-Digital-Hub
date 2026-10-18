<?php

declare(strict_types=1);

/*
| S22 (Octane): the app stays in memory between requests, so a static property with state would carry one
| person's data into the next request. Only the fake drivers (reset after every request by
| App\Octane\ResetFakeDrivers) may keep static state.
*/
it('keeps no request state in static properties (Octane)', function (): void {
    $allowed = ['FakePdfRenderer.php', 'FakeSpeechToText.php', 'FakeAiDriver.php'];
    $found = [];
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path(), FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) {
        $path = (string) $file;
        if (! str_ends_with($path, '.php') || (! str_contains($path, '/app/') && ! preg_match('#/modules/[^/]+/src/#', $path)) || str_contains($path, '/vendor/')) {
            continue;
        }
        if (in_array(basename($path), $allowed, true)) {
            continue;
        }
        if (preg_match('/^\s*(public|protected|private)\s+static\s+(?!function)(?:\??[\\\w]+\s+)?\$\w+/m', (string) file_get_contents($path)) === 1) {
            $found[] = str_replace(base_path().'/', '', $path);
        }
    }

    expect($found)->toBe([]);
});

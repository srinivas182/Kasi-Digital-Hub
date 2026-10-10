<?php

declare(strict_types=1);

/*
| Every Inertia::render('Module/Page') in the modules must have a page file. PHP feature tests don't
| load the page component, so a wrong name only showed up in browser tests (S17).
*/
it('has a page component for every Inertia page rendered by the modules', function (): void {
    $missing = [];
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path('modules'), FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) {
        if (! str_ends_with((string) $file, '.php') || ! str_contains((string) $file, '/src/')) {
            continue;
        }
        preg_match_all("/Inertia::render\\('([A-Za-z]+)\\/([A-Za-z\\/]+)'/", (string) file_get_contents((string) $file), $matches, PREG_SET_ORDER);
        foreach ($matches as [, $module, $page]) {
            if (! is_file(base_path("modules/{$module}/resources/js/Pages/{$page}.tsx"))) {
                $missing[] = "{$module}/{$page} (".str_replace(base_path().'/', '', (string) $file).')';
            }
        }
    }

    expect($missing)->toBe([]);
});

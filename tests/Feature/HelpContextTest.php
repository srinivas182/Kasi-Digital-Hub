<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Site\Database\Seeders\HelpSeeder;

/* Every "Help with this page" link points from a real page to a real starter article (S21). */
it('maps real pages to real help articles', function (): void {
    $slugs = array_column(array_map(static fn (array $a): array => ['slug' => $a[1]], HelpSeeder::ARTICLES), 'slug');
    $problems = [];
    foreach ((array) config('kasi.help_context') as $route => $slug) {
        if (! Route::has($route)) {
            $problems[] = "no route {$route}";
        }
        if (! in_array($slug, $slugs, true)) {
            $problems[] = "no article {$slug}";
        }
    }

    expect($problems)->toBe([]);
});

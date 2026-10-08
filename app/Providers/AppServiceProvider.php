<?php

declare(strict_types=1);

namespace App\Providers;

use App\Support\Locale\FallbackJsonLoader;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Missing keys in a partly translated language fall back to English (not raw keys).
        // (extend, not bind: the deferred translation provider registers its own loader later.)
        $this->app->extend('translation.loader', fn ($loader, $app): FallbackJsonLoader => new FallbackJsonLoader(
            $app['files'],
            [base_path('vendor/laravel/framework/src/Illuminate/Translation/lang'), $app['path.lang']],
        ));
    }
}

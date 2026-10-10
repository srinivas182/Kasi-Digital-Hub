<?php

declare(strict_types=1);

namespace Modules\Start\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Core\Home\HomeRegistry;
use Modules\Start\Home\StartHomeContributor;

final class StartServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag([StartHomeContributor::class], HomeRegistry::TAG);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'start');
    }
}

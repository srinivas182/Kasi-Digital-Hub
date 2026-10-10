<?php

declare(strict_types=1);

namespace Modules\Start\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Core\Enterprise\BusinessFacts;
use Modules\Core\Home\HomeRegistry;
use Modules\Start\Home\StartHomeContributor;
use Modules\Start\Services\StartBusinessFacts;

final class StartServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag([StartHomeContributor::class], HomeRegistry::TAG);
        $this->app->bind(BusinessFacts::class, StartBusinessFacts::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'start');
    }
}

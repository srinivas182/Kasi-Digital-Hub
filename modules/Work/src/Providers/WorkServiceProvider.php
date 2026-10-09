<?php

declare(strict_types=1);

namespace Modules\Work\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Core\Home\HomeRegistry;
use Modules\Work\Home\WorkHomeContributor;

final class WorkServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag([WorkHomeContributor::class], HomeRegistry::TAG);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'work');
    }
}

<?php

declare(strict_types=1);

namespace Modules\Connect\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Connect\Console\ConnectDailyCommand;
use Modules\Connect\Home\ConnectHomeContributor;
use Modules\Core\Home\HomeRegistry;

final class ConnectServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag([ConnectHomeContributor::class], HomeRegistry::TAG);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'connect');
        if ($this->app->runningInConsole()) {
            $this->commands([ConnectDailyCommand::class]);
        }
    }
}

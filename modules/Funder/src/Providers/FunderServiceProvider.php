<?php

declare(strict_types=1);

namespace Modules\Funder\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Funder\Console\FreezeSnapshotsCommand;

final class FunderServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'funder');
        if ($this->app->runningInConsole()) {
            $this->commands([FreezeSnapshotsCommand::class]);
        }
    }
}

<?php

declare(strict_types=1);

namespace Modules\HubOps\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Core\Home\HubActivity;
use Modules\HubOps\Console\AnonymiseVisitsCommand;
use Modules\HubOps\Console\RemindEventsCommand;
use Modules\HubOps\Services\EventActivity;

final class HubOpsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag([EventActivity::class], HubActivity::TAG);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([RemindEventsCommand::class, AnonymiseVisitsCommand::class]);
        }
    }
}

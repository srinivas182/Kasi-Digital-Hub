<?php

declare(strict_types=1);

namespace Modules\Admin\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Admin\Console\AdminHourlyCommand;
use Modules\Admin\Support\HeldContent;
use Modules\Core\Insights\ReviewSource;

/**
 * National admin console. Routes are loaded from modules/Admin/routes by the module system.
 */
final class AdminServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag([HeldContent::class], ReviewSource::TAG);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([AdminHourlyCommand::class]);
        }
    }
}

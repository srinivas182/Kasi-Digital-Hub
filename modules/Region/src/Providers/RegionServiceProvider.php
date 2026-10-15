<?php

declare(strict_types=1);

namespace Modules\Region\Providers;

use Illuminate\Support\ServiceProvider;

final class RegionServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'region');

    }
}

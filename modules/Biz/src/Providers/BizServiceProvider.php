<?php

declare(strict_types=1);

namespace Modules\Biz\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Biz\Services\BizSignals;
use Modules\Core\Enterprise\BusinessSignals;
use Modules\Core\Insights\EventIndicator;
use Modules\Core\Insights\RegistersIndicators;

final class BizServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        RegistersIndicators::add($this->app, [
            new EventIndicator('biz.entries', ['biz.entry.recorded']),
            new EventIndicator('biz.businesses_recording', ['biz.entry.recorded'], 'people'),
        ]);
        $this->app->tag([BizSignals::class], BusinessSignals::TAG);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'biz');
    }
}

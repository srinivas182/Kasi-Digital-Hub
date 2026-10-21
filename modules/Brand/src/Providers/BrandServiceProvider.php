<?php

declare(strict_types=1);

namespace Modules\Brand\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Brand\Services\BrandSignals;
use Modules\Core\Enterprise\BusinessSignals;
use Modules\Core\Insights\EventIndicator;
use Modules\Core\Insights\RegistersIndicators;

final class BrandServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        RegistersIndicators::add($this->app, [
            new EventIndicator('brand.kits', ['brand.kit.saved'], 'people'),
            new EventIndicator('brand.exports', ['brand.design.exported']),
        ]);
        $this->app->tag([BrandSignals::class], BusinessSignals::TAG);
    }
}

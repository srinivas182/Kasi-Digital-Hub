<?php

declare(strict_types=1);

namespace Modules\Start\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Core\Enterprise\BusinessFacts;
use Modules\Core\Home\HomeRegistry;
use Modules\Core\Insights\EventIndicator;
use Modules\Core\Insights\RegistersIndicators;
use Modules\Start\Home\StartHomeContributor;
use Modules\Start\Services\StartBusinessFacts;

final class StartServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Outcome indicators for funders and coordinators (S20, ADR-028).
        RegistersIndicators::add($this->app, [
            new EventIndicator('start.businesses', ['start.business.created']),
            new EventIndicator('start.formalised', ['start.business.formalised']),
            new EventIndicator('start.plans', ['start.plan.completed']),
        ]);
        $this->app->tag([StartHomeContributor::class], HomeRegistry::TAG);
        $this->app->bind(BusinessFacts::class, StartBusinessFacts::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'start');
    }
}

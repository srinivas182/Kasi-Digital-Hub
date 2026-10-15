<?php

declare(strict_types=1);

namespace Modules\Connect\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Connect\Console\ConnectDailyCommand;
use Modules\Connect\Home\ConnectHomeContributor;
use Modules\Connect\Services\ConcernsOpen;
use Modules\Core\Home\HomeRegistry;
use Modules\Core\Insights\EventIndicator;
use Modules\Core\Insights\HealthSignal;
use Modules\Core\Insights\RegistersIndicators;

final class ConnectServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag([ConcernsOpen::class], HealthSignal::TAG);
        // Outcome indicators for funders and coordinators (S20, ADR-028).
        RegistersIndicators::add($this->app, [
            new EventIndicator('connect.mentorships', ['connect.mentorship.started']),
            new EventIndicator('connect.completed', ['connect.mentorship.ended']),
            new EventIndicator('connect.goals_met', ['connect.mentorship.ended'], 'sum', null, 'met'),
            new EventIndicator('connect.sessions', ['connect.session.held']),
        ]);
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

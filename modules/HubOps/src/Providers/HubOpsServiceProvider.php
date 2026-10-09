<?php

declare(strict_types=1);

namespace Modules\HubOps\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Core\Home\HubActivity;
use Modules\Core\Search\RefreshSearchDocument;
use Modules\Core\Search\SearchRegistry;
use Modules\HubOps\Console\AnonymiseVisitsCommand;
use Modules\HubOps\Console\RemindEventsCommand;
use Modules\HubOps\Models\HubEvent;
use Modules\HubOps\Search\EventSearchSource;
use Modules\HubOps\Services\EventActivity;

final class HubOpsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag([EventActivity::class], HubActivity::TAG);
        $this->app->tag([EventSearchSource::class], SearchRegistry::TAG);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'hubops');

        // Keep events findable as they are scheduled, changed or cancelled.
        HubEvent::saved(static fn (HubEvent $e) => RefreshSearchDocument::dispatch('event', $e->id));
        HubEvent::deleted(static fn (HubEvent $e) => RefreshSearchDocument::dispatch('event', $e->id));

        if ($this->app->runningInConsole()) {
            $this->commands([RemindEventsCommand::class, AnonymiseVisitsCommand::class]);
        }
    }
}

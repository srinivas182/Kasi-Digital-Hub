<?php

declare(strict_types=1);

namespace Modules\Work\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Modules\Core\Events\ModerationDecided;
use Modules\Core\Home\HomeRegistry;
use Modules\Core\Search\RefreshSearchDocument;
use Modules\Core\Search\SearchRegistry;
use Modules\Work\Console\ImportOfoCommand;
use Modules\Work\Console\ListingHousekeepingCommand;
use Modules\Work\Home\WorkHomeContributor;
use Modules\Work\Models\JobListing;
use Modules\Work\Search\JobSearchSource;
use Modules\Work\Services\Listings;

final class WorkServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag([WorkHomeContributor::class], HomeRegistry::TAG);
        $this->app->tag([JobSearchSource::class], SearchRegistry::TAG);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'work');

        if ($this->app->runningInConsole()) {
            $this->commands([ImportOfoCommand::class, ListingHousekeepingCommand::class]);
        }

        // Reviewers' decisions on flagged listings (content review queue).
        Event::listen(ModerationDecided::class, static function (ModerationDecided $e): void {
            if ($e->subjectType === 'job_listing' && ($listing = JobListing::query()->find($e->subjectId)) !== null) {
                app(Listings::class)->reviewed($listing, $e->decision, $e->reason);
            }
        });

        // Keep the job search index current.
        JobListing::saved(static fn (JobListing $l) => RefreshSearchDocument::dispatch('job', $l->id));
        JobListing::deleted(static fn (JobListing $l) => RefreshSearchDocument::dispatch('job', $l->id));
    }
}

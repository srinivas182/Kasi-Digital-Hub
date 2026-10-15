<?php

declare(strict_types=1);

namespace Modules\Work\Providers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Modules\Core\Commerce\Fulfiller;
use Modules\Core\Events\ModerationDecided;
use Modules\Core\Home\HomeRegistry;
use Modules\Core\Home\SkillGapSource;
use Modules\Core\Insights\EventIndicator;
use Modules\Core\Insights\HealthSignal;
use Modules\Core\Insights\RegistersIndicators;
use Modules\Core\Search\RefreshSearchDocument;
use Modules\Core\Search\SearchRegistry;
use Modules\Work\Console\HiringHousekeepingCommand;
use Modules\Work\Console\ImportOfoCommand;
use Modules\Work\Console\JobAlertsCommand;
use Modules\Work\Console\ListingHousekeepingCommand;
use Modules\Work\Home\WorkHomeContributor;
use Modules\Work\Matching\RefreshMatches;
use Modules\Work\Matching\WorkSkillGaps;
use Modules\Work\Models\JobListing;
use Modules\Work\Search\JobSearchSource;
use Modules\Work\Services\AdvertSlots;
use Modules\Work\Services\ApplicationsUnanswered;
use Modules\Work\Services\Listings;

final class WorkServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag([ApplicationsUnanswered::class], HealthSignal::TAG);
        // Outcome indicators for funders and coordinators (S20, ADR-028).
        RegistersIndicators::add($this->app, [
            new EventIndicator('work.profiles', ['work.profile.completed'], 'people'),
            new EventIndicator('work.applications', ['work.application.submitted']),
            new EventIndicator('work.hires', ['work.hire.confirmed']),
            new EventIndicator('work.retained_90', ['work.retention.checked'], 'events', static fn (array $p): bool => (int) ($p['days'] ?? 0) === 90 && (bool) ($p['still_working'] ?? false)),
        ]);
        $this->app->tag([AdvertSlots::class], Fulfiller::TAG);
        $this->app->tag([WorkHomeContributor::class], HomeRegistry::TAG);
        $this->app->tag([WorkSkillGaps::class], SkillGapSource::TAG);
        $this->app->tag([JobSearchSource::class], SearchRegistry::TAG);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'work');

        if ($this->app->runningInConsole()) {
            $this->commands([ImportOfoCommand::class, ListingHousekeepingCommand::class, JobAlertsCommand::class, HiringHousekeepingCommand::class]);
        }

        // Reviewers' decisions on flagged listings (content review queue).
        Event::listen(ModerationDecided::class, static function (ModerationDecided $e): void {
            if ($e->subjectType === 'work_message') {
                // Approved messages are delivered; rejected ones stay held.
                if ($e->decision === 'approved') {
                    DB::table('work_messages')->where('id', $e->subjectId)->update(['held' => false]);
                }

                return;
            }
            if ($e->subjectType === 'job_listing' && ($listing = JobListing::query()->find($e->subjectId)) !== null) {
                app(Listings::class)->reviewed($listing, $e->decision, $e->reason);
            }
        });

        // Keep the job search index current.
        JobListing::saved(static function (JobListing $l): void {
            RefreshSearchDocument::dispatch('job', $l->id);
            // Matches follow the advert: recalculated when it goes live, changes or closes.
            RefreshMatches::dispatch('listing', $l->id);
        });
        JobListing::deleted(static fn (JobListing $l) => RefreshSearchDocument::dispatch('job', $l->id));
    }
}

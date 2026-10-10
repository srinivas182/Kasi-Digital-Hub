<?php

declare(strict_types=1);

namespace Modules\Learn\Providers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use Modules\Core\Achievements\AchievementSource;
use Modules\Core\Home\HomeRegistry;
use Modules\Core\Hubs\HubSessionAttended;
use Modules\Core\Search\SearchRegistry;
use Modules\Learn\Console\LearnDailyCommand;
use Modules\Learn\Home\LearnHomeContributor;
use Modules\Learn\Media\FakeMediaConverter;
use Modules\Learn\Media\FfmpegConverter;
use Modules\Learn\Media\MediaConverter;
use Modules\Learn\Models\Enrolment;
use Modules\Learn\Search\CourseSearchSource;
use Modules\Learn\Services\Certificates;
use Modules\Learn\Services\Learning;

final class LearnServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MediaConverter::class, fn (): MediaConverter => match (config('kasi.learn.media_driver')) {
            'fake' => new FakeMediaConverter,
            'ffmpeg' => new FfmpegConverter,
            default => throw new InvalidArgumentException('Unknown media driver ['.config('kasi.learn.media_driver').'].'),
        });
        $this->app->tag([LearnHomeContributor::class], HomeRegistry::TAG);
        $this->app->tag([CourseSearchSource::class], SearchRegistry::TAG);
        $this->app->tag([Certificates::class], AchievementSource::TAG);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'learn');

        // Attendance at a cohort session can complete a blended course.
        Event::listen(HubSessionAttended::class, static function (HubSessionAttended $e): void {
            $courseIds = DB::table('learn_cohort_sessions')->join('learn_cohorts', 'learn_cohorts.id', '=', 'learn_cohort_sessions.cohort_id')
                ->where('learn_cohort_sessions.event_id', $e->sessionId)->pluck('learn_cohorts.course_id');
            Enrolment::query()->with(['course', 'version'])->where('user_id', $e->userId)->whereIn('course_id', $courseIds)->get()
                ->each(static fn (Enrolment $en) => app(Learning::class)->recalculate($en));
        });

        if ($this->app->runningInConsole()) {
            $this->commands([LearnDailyCommand::class]);
        }
    }
}

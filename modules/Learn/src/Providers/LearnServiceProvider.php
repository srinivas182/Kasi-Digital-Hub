<?php

declare(strict_types=1);

namespace Modules\Learn\Providers;

use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use Modules\Core\Home\HomeRegistry;
use Modules\Core\Search\SearchRegistry;
use Modules\Learn\Console\LearnDailyCommand;
use Modules\Learn\Home\LearnHomeContributor;
use Modules\Learn\Media\FakeMediaConverter;
use Modules\Learn\Media\FfmpegConverter;
use Modules\Learn\Media\MediaConverter;
use Modules\Learn\Search\CourseSearchSource;

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
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([LearnDailyCommand::class]);
        }
    }
}

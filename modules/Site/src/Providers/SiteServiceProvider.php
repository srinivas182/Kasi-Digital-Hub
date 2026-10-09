<?php

declare(strict_types=1);

namespace Modules\Site\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Modules\Core\Search\RefreshSearchDocument;
use Modules\Core\Search\SearchRegistry;
use Modules\Core\Structure\Models\Hub;
use Modules\Site\Search\HelpSearchSource;
use Modules\Site\Search\HubSearchSource;

final class SiteServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag([HubSearchSource::class, HelpSearchSource::class], SearchRegistry::TAG);
    }

    public function boot(): void
    {
        // Website forms: 5 messages per 10 minutes per connection (raised only for browser tests).
        RateLimiter::for('site-enquiries', static fn (Request $request): Limit => Limit::perMinutes(10, 5 * max(1, (int) config('kasi.identity.throttle_multiplier', 1)))->by((string) $request->ip()));

        // Keep hubs findable as they open, change or close.
        Hub::saved(static fn (Hub $hub) => RefreshSearchDocument::dispatch('hub', $hub->id));
        Hub::deleted(static fn (Hub $hub) => RefreshSearchDocument::dispatch('hub', $hub->id));
    }
}

<?php

declare(strict_types=1);

namespace Modules\Brand\Providers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Brand\Console\BrandRemindersCommand;
use Modules\Brand\Services\BrandSignals;
use Modules\Brand\Services\PublicSite;
use Modules\Core\Enterprise\BusinessSignals;
use Modules\Core\Enterprise\PublicSites;
use Modules\Core\Insights\EventIndicator;
use Modules\Core\Insights\RegistersIndicators;

final class BrandServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        RegistersIndicators::add($this->app, [
            new EventIndicator('brand.kits', ['brand.kit.saved'], 'people'),
            new EventIndicator('brand.exports', ['brand.design.exported']),
            new EventIndicator('brand.websites', ['brand.site.published'], 'people'),
            new EventIndicator('brand.posts_posted', ['brand.post.posted']),
        ]);
        $this->app->tag([BrandSignals::class], BusinessSignals::TAG);
        $this->app->bind(PublicSites::class, PublicSite::class); // S31: business websites
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'brand');
        // S31: a business website by path. Registered outside the web stack: no session, no cookies, no CSRF token -
        // nothing about visitors is stored. (The subdomain name.<domain> is served by the portal host middleware.)
        Route::get('/b/{name}', static function (Request $request, string $name) {
            return app(PublicSites::class)->respond($name, $request) ?? abort(404);
        })->where('name', '[a-z0-9][a-z0-9-]{1,28}[a-z0-9]')->middleware('throttle:120,1')->name('brand.site.public');
        if ($this->app->runningInConsole()) {
            $this->commands([BrandRemindersCommand::class]);
        }
    }
}

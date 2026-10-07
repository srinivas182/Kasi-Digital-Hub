<?php

declare(strict_types=1);

namespace App\Providers;

use App\Support\Modules\ModuleManifest;
use App\Support\Modules\ModuleRegistry;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Boots every enabled module: its service providers, routes and migrations.
 *
 * Web routes use the `web` middleware group. API routes are versioned under
 * `/api/v1` with the `api` middleware group, ready for mobile apps and partners.
 */
final class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ModuleRegistry::class, function (): ModuleRegistry {
            /** @var list<string> $disabled */
            $disabled = config('kasi.modules.disabled', []);

            return new ModuleRegistry(base_path('modules'), $disabled);
        });

        foreach ($this->registry()->enabled() as $module) {
            foreach ($module->providers as $provider) {
                $this->app->register($provider);
            }
        }
    }

    public function boot(): void
    {
        foreach ($this->registry()->enabled() as $module) {
            $this->bootModule($module);
        }
    }

    private function bootModule(ModuleManifest $module): void
    {
        $migrations = $module->path('database/migrations');

        if (is_dir($migrations)) {
            $this->loadMigrationsFrom($migrations);
        }

        if ($this->app->routesAreCached()) {
            return;
        }

        if ($module->webRoutes && is_file($web = $module->path('routes/web.php'))) {
            Route::middleware('web')->group($web);
        }

        if ($module->apiRoutes && is_file($api = $module->path('routes/api.php'))) {
            Route::middleware('api')
                ->prefix('api/v1')
                ->name('api.v1.')
                ->group($api);
        }
    }

    private function registry(): ModuleRegistry
    {
        return $this->app->make(ModuleRegistry::class);
    }
}

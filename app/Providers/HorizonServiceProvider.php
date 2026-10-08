<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;
use Modules\Core\Access\AccessResolver;
use Modules\Core\Identity\Models\User;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        // Horizon::routeSmsNotificationsTo('15556667777');
        // Horizon::routeMailNotificationsTo('example@example.com');
        // Horizon::routeSlackNotificationsTo('slack-webhook-url', '#channel');
    }

    /**
     * Register the Horizon gate.
     *
     * This gate determines who can access Horizon in non-local environments.
     */
    protected function gate(): void
    {
        // Queue dashboard: national super admins and operations admins only.
        Gate::define('viewHorizon', static fn ($user = null): bool => $user instanceof User
            && array_intersect(app(AccessResolver::class)->roleKeys($user), ['super_admin', 'operations_admin']) !== []);
    }
}

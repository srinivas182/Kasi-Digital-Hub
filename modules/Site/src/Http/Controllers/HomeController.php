<?php

declare(strict_types=1);

namespace Modules\Site\Http\Controllers;

use App\Support\Seo\Seo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Site\Support\ImpactStats;
use Modules\Site\Support\PublicHubs;

/**
 * Public home page. Signed-in people go straight to their hub home.
 */
final class HomeController
{
    public function __invoke(Request $request): Response|RedirectResponse
    {
        if ($request->user() !== null) {
            return to_route('hub.home');
        }

        return Inertia::render('Site/Home', [
            'impact' => ImpactStats::headline(),
            'hubs' => array_slice(array_values(array_filter(PublicHubs::all(), static fn (array $hub): bool => $hub['status'] === 'live')), 0, 6),
            'hubCount' => count(PublicHubs::all()),
            'seo' => Seo::page(
                (string) config('kasi.brand.full_name'),
                __('site.home.seo_description'),
                '/',
                [Seo::organisation()],
            ),
        ]);
    }
}

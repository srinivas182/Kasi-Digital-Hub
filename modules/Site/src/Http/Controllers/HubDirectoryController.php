<?php

declare(strict_types=1);

namespace Modules\Site\Http\Controllers;

use App\Support\Seo\Seo;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Site\Support\PublicHubs;

/**
 * "Find a hub" and the public page of each hub.
 */
final class HubDirectoryController
{
    public function index(): Response
    {
        return Inertia::render('Site/Hubs/Index', [
            'hubs' => PublicHubs::all(),
            'seo' => Seo::page(__('site.hubs.title'), __('site.hubs.seo_description'), '/hubs'),
        ]);
    }

    public function show(string $slug): Response
    {
        $hub = PublicHubs::find($slug);
        abort_if($hub === null, 404);

        return Inertia::render('Site/Hubs/Show', [
            'hub' => $hub,
            'seo' => Seo::page(
                (string) $hub['name'],
                (string) ($hub['description'] ?? __('site.hubs.seo_description')),
                '/hubs/'.$hub['slug'],
                [PublicHubs::jsonLd($hub)],
            ),
        ]);
    }
}

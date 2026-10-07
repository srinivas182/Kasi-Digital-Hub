<?php

declare(strict_types=1);

namespace Modules\Site\Http\Controllers;

use App\Support\Modules\ModuleManifest;
use App\Support\Modules\ModuleRegistry;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public home page. In S0 this is the platform shell; S5 replaces it with the
 * full public website.
 */
final class HomeController
{
    public function __invoke(ModuleRegistry $registry): Response
    {
        $portals = array_values(array_map(
            static fn (ModuleManifest $m): array => [
                'name' => $m->name,
                'title' => $m->title,
                'description' => $m->description,
                'group' => $m->group,
            ],
            array_filter($registry->enabled(), static fn (ModuleManifest $m): bool => $m->group !== 'core'),
        ));

        return Inertia::render('Site/Home', ['portals' => $portals]);
    }
}

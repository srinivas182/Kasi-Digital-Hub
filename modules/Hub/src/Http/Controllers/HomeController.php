<?php

declare(strict_types=1);

namespace Modules\Hub\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Personal hub home. Sprint 2 shows a welcome; Sprint 5 adds next steps, updates and portal tiles.
 */
final class HomeController
{
    public function __invoke(Request $request): Response
    {
        return Inertia::render('Hub/Home', [
            'welcome' => (bool) $request->session()->get('welcome', false),
        ]);
    }
}

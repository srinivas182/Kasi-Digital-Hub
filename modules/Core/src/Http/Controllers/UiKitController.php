<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

/**
 * Internal component catalogue - the visual reference for every sprint.
 * Only routed outside production (see modules/Core/routes/web.php).
 */
final class UiKitController
{
    public const LAYOUTS = ['public', 'auth', 'app', 'portal', 'console', 'kiosk'];

    public function index(): Response
    {
        return Inertia::render('Core/UiKit/Index', ['layouts' => self::LAYOUTS]);
    }

    public function layout(string $layout): Response
    {
        abort_unless(in_array($layout, self::LAYOUTS, true), 404);

        return Inertia::render('Core/UiKit/Layout', ['layout' => $layout]);
    }
}

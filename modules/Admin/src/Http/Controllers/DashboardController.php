<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use Modules\Admin\Services\DashboardMetrics;

final class DashboardController
{
    public function __invoke(DashboardMetrics $metrics): Response
    {
        return Inertia::render('Admin/Dashboard', $metrics->all());
    }
}

<?php

declare(strict_types=1);

namespace Modules\Admin\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * National admin console. Routes are loaded from modules/Admin/routes by the module system.
 */
final class AdminServiceProvider extends ServiceProvider
{
    public function register(): void {}
}

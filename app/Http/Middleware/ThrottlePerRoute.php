<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Route;

/**
 * Rate limits per person AND per route. Laravel's default `throttle:5,10` keys the counter on the
 * signed-in user only, so every limited route shares one counter: saving jobs could use up the
 * allowance for registering a business. Found by the S16 browser run. Named limiters are unaffected.
 */
final class ThrottlePerRoute extends ThrottleRequests
{
    /** @param Request $request */
    protected function resolveRequestSignature($request): string
    {
        $route = $request->route();
        $name = $route instanceof Route ? ($route->getName() ?? $route->uri()) : $request->path();

        return sha1(parent::resolveRequestSignature($request).'|'.$name);
    }
}

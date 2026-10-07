<?php

declare(strict_types=1);

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Core\Identity\Models\User;
use Symfony\Component\HttpFoundation\Response;

/**
 * Age policy guard for portals that are adults-only (KasiWork, KasiStart, KasiConnect).
 * Usage: ->middleware('adult'). Minors may only use the modules in kasi.age.minor_modules.
 */
final class EnsureAdult
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_if($user instanceof User && $user->isMinor(), 403);

        return $next($request);
    }
}

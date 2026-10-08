<?php

declare(strict_types=1);

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Core\Access\AccessLevel;
use Modules\Core\Access\AccessResolver;
use Modules\Core\Identity\Models\User;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route guard: ->middleware('portal:Work') or ->middleware('portal:HubOps,manage').
 * Requires the given access level (default: view) in the portal.
 */
final class EnsurePortalAccess
{
    public function __construct(private readonly AccessResolver $access) {}

    public function handle(Request $request, Closure $next, string $module, string $level = 'view'): Response
    {
        $user = $request->user();

        abort_unless($user instanceof User && $this->access->can($user, $module, AccessLevel::fromName($level)), 403);

        return $next($request);
    }
}

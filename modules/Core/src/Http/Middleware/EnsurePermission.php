<?php

declare(strict_types=1);

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Core\Access\AccessResolver;
use Modules\Core\Identity\Models\User;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route guard for fine-grained permissions: ->middleware('permission:admin.documents.verify').
 */
final class EnsurePermission
{
    public function __construct(private readonly AccessResolver $access) {}

    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        abort_unless($user instanceof User && $this->access->hasPermission($user, $permission), 403);

        return $next($request);
    }
}

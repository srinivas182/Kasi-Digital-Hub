<?php

declare(strict_types=1);

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Core\Identity\Models\User;
use Modules\Core\Identity\Services\Authenticator;
use Modules\Core\Identity\Services\ConsentService;
use Symfony\Component\HttpFoundation\Response;

/**
 * Before any signed-in page: the account must be usable and the latest terms accepted.
 */
final class EnsureAccountReady
{
    public function __construct(private readonly ConsentService $consents) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return $next($request);
        }

        if (in_array($user->status, [User::STATUS_SUSPENDED, User::STATUS_PENDING_GUARDIAN], true)) {
            app(Authenticator::class)->logout('account_not_active');

            return redirect()->route('login')->with('signed_out_reason', 'account_not_active');
        }

        if (! $request->routeIs('consents.review*') && $this->consents->needsReacceptance($user)) {
            return redirect()->guest(route('consents.review'));
        }

        return $next($request);
    }
}

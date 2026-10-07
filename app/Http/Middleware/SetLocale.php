<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Locale\Languages;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the visitor's chosen interface language (cookie), falling back to English.
 * A signed-in user's profile language takes priority.
 */
final class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        // Signed-in users: their profile language. Visitors: the language cookie.
        $user = $request->hasSession() ? $request->user() : null;
        $locale = $user !== null && is_string($user->getAttribute('preferred_locale'))
            ? $user->getAttribute('preferred_locale')
            : $request->cookie(Languages::COOKIE);

        app()->setLocale(is_string($locale) && Languages::isAvailable($locale) ? $locale : 'en');

        return $next($request);
    }
}

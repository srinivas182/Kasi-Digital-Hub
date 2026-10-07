<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Locale\Languages;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the visitor's chosen interface language (cookie), falling back to English.
 * From Sprint 2 a signed-in user's profile language takes priority.
 */
final class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->cookie(Languages::COOKIE);

        app()->setLocale(is_string($locale) && Languages::isAvailable($locale) ? $locale : 'en');

        return $next($request);
    }
}

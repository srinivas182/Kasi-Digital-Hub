<?php

declare(strict_types=1);

namespace App\Support\Errors;

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetLocale;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Renders the branded Core/Error page.
 *
 * Errors such as "page not found" happen before the web middleware runs, so the
 * language and shared props (brand, strings, navigation) are applied here too.
 */
final class ErrorPage
{
    public const BRANDED = [403, 404, 419, 429, 500, 503];

    public static function render(Request $request, int $status): Response
    {
        (new SetLocale)->handle($request, static fn (): Response => new Response);

        Inertia::share(app(HandleInertiaRequests::class)->share($request));

        return Inertia::render('Core/Error', ['status' => $status])
            ->toResponse($request)
            ->setStatusCode($status);
    }
}

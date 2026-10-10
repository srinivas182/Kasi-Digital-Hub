<?php

declare(strict_types=1);

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\ThrottlePerRoute;
use App\Support\Errors\ErrorPage;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Modules\Core\Http\Middleware\TrackDevice;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['kasi_locale']);
        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo('/home');
        // Rate limits per person and per route (see ThrottlePerRoute).
        $middleware->alias(['throttle' => ThrottlePerRoute::class]);
        $middleware->web(append: [
            TrackDevice::class,
            SetLocale::class,
            HandleInertiaRequests::class,
            SecurityHeaders::class,
        ]);
        $middleware->api(append: [
            SecurityHeaders::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Branded, plain-language error pages (Core/Error). Local development keeps
        // Laravel's detailed error screen for 500s so bugs are easy to diagnose.
        $exceptions->respond(function (Response $response, Throwable $e, Request $request): Response {
            $status = $response->getStatusCode();

            if (! in_array($status, ErrorPage::BRANDED, true) || $request->expectsJson()) {
                return $response;
            }

            if ($status === 500 && app()->hasDebugModeEnabled()) {
                return $response;
            }

            return ErrorPage::render($request, $status);
        });
    })->create();

<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Portals\Portals;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline security headers for every web response.
 *
 * The Content Security Policy starts in report-only mode so it can be tuned
 * against real pages (fonts, media, analytics) before it is enforced in S23 (security sprint).
 */
final class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $headers = $response->headers;
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'SAMEORIGIN');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(self), microphone=(self), geolocation=(self), payment=()');
        // S23: with portal subdomains, our own subdomains may also serve assets and receive form posts and redirects.
        $ours = Portals::enabled() ? ' https://*.'.Portals::domain().' https://'.Portals::domain() : '';
        $headers->set('Content-Security-Policy-Report-Only', "default-src 'self'{$ours}; img-src 'self'{$ours} data: blob:; style-src 'self'{$ours} 'unsafe-inline'; script-src 'self'{$ours}; connect-src 'self'{$ours}; form-action 'self'{$ours}; frame-ancestors 'self'");

        if ($request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}

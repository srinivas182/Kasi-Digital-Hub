<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Portals\Portals;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Modules\Core\Enterprise\PublicSites;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends each page to its portal's subdomain (S23). Only page loads (GET/HEAD) move; form posts and webhooks
 * are never redirected. In-app (Inertia) navigation gets a full-page switch to the other host; ordinary
 * requests get a permanent redirect, so old links and QR codes keep working.
 */
final class PortalHost
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Portals::enabled()) {
            return $next($request);
        }
        if (! in_array($request->method(), ['GET', 'HEAD'], true)) {
            return $this->crossHost($request, $next($request));
        }

        $current = Portals::current($request);
        $path = '/'.ltrim($request->path(), '/');
        $query = $request->getQueryString();
        $pathAndQuery = $path.($query !== null ? '?'.$query : '');

        if ($current === null) {
            // An unknown subdomain of our domain goes to the main site. Any other host (the server's IP, a load
            // balancer's health check) is served as it is - never redirected.
            // S31: a business's published website (name.<domain>), when KasiBrand is installed.
            if (Portals::isOurs($request) && app()->bound(PublicSites::class)) {
                $name = strtolower(substr($request->getHost(), 0, -strlen('.'.(string) config('kasi.subdomains.domain'))));
                if (preg_match('/^[a-z0-9][a-z0-9-]{1,28}[a-z0-9]$/', $name) === 1) {
                    $site = app(PublicSites::class)->respond($name, $request);
                    if ($site !== null) {
                        return $site;
                    }
                }
            }

            return Portals::isOurs($request) ? redirect()->away(Portals::url('', '/'), 302) : $next($request);
        }

        // A portal's own address opens that portal.
        if ($path === '/' && $current !== '' && ($home = Portals::home($current)) !== null) {
            return redirect()->away(Portals::url($current, $home), 302);
        }

        $owner = Portals::owner($path);
        if ($owner === null || $owner === $current) {
            return $next($request);
        }

        $target = Portals::url($owner, $pathAndQuery);

        return $request->header('X-Inertia') !== null ? Inertia::location($target) : redirect()->away($target, 301);
    }

    /**
     * An in-app (Inertia) request answered with a redirect to another host can't follow it in the background
     * (different origin), so it becomes a full-page switch to that address - e.g. signing in on the main domain
     * and landing on my., or finishing an action that belongs to another portal. One hop, no extra redirect.
     */
    private function crossHost(Request $request, Response $response): Response
    {
        if ($request->header('X-Inertia') === null || ! $response->isRedirection()) {
            return $response;
        }
        $target = (string) $response->headers->get('Location');
        $host = strtolower((string) parse_url($target, PHP_URL_HOST));
        if ($host === '' || $host === strtolower($request->getHost())) {
            // Same host: let the redirect through, unless its path belongs to another portal.
            $path = (string) parse_url($target, PHP_URL_PATH);
            $owner = Portals::owner($path === '' ? '/' : $path);
            $current = Portals::current($request);
            if ($owner === null || $current === null || $owner === $current) {
                return $response;
            }
            $query = parse_url($target, PHP_URL_QUERY);
            $target = Portals::url($owner, $path.(is_string($query) ? '?'.$query : ''));
        }

        // Keep the original response's cookies (e.g. the new session cookie set at sign-in) - a fresh response
        // would drop them and the person would arrive signed out.
        $switch = Inertia::location($target);
        foreach ($response->headers->getCookies() as $cookie) {
            $switch->headers->setCookie($cookie);
        }

        return $switch;
    }
}

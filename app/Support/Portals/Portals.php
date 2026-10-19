<?php

declare(strict_types=1);

namespace App\Support\Portals;

use Illuminate\Http\Request;

/**
 * Portal subdomains (S23, ADR-031). Each portal owns path prefixes and has a subdomain of the base domain
 * (KASI_DOMAIN). Pages opened on the wrong host are sent to the owner's host; paths owned by no portal
 * (sign-in steps, checkout, downloads, health check) are served on any host. Off by default: then every
 * method returns today's single-host behaviour.
 */
final class Portals
{
    public static function enabled(): bool
    {
        return (bool) config('kasi.subdomains.enabled');
    }

    /** The base domain without scheme or port, e.g. "kasidigitalhub.co.za". */
    public static function domain(): string
    {
        return strtolower((string) config('kasi.subdomains.domain'));
    }

    /**
     * Subdomain => owned path prefixes ("" = the main domain). Longest prefix wins.
     *
     * @return array<string, list<string>>
     */
    public static function map(): array
    {
        /** @var array<string, list<string>> */
        return (array) config('kasi.subdomains.portals');
    }

    /** Which subdomain owns this path, "" for the main domain, or null when any host may serve it. */
    public static function owner(string $path): ?string
    {
        $path = '/'.ltrim($path, '/');
        foreach ((array) config('kasi.subdomains.shared') as $shared) {
            if ($path === $shared || str_starts_with($path, rtrim((string) $shared, '/').'/')) {
                return null;
            }
        }
        $best = null;
        $length = -1;
        foreach (self::map() as $sub => $prefixes) {
            foreach ($prefixes as $prefix) {
                $matches = $prefix === '/' ? $path === '/' : ($path === $prefix || str_starts_with($path, rtrim($prefix, '/').'/'));
                if ($matches && strlen($prefix) > $length) {
                    [$best, $length] = [$sub, strlen($prefix)];
                }
            }
        }

        return $best;
    }

    /** The subdomain of this request: "" for the main domain, null for a host that isn't ours or is unknown. */
    public static function current(Request $request): ?string
    {
        $host = strtolower($request->getHost());
        if ($host === self::domain()) {
            return '';
        }
        $suffix = '.'.self::domain();
        if (! str_ends_with($host, $suffix)) {
            return null;
        }
        $sub = substr($host, 0, -strlen($suffix));

        return array_key_exists($sub, self::map()) ? $sub : null;
    }

    /** Is this host the base domain or one of its subdomains? */
    public static function isOurs(Request $request): bool
    {
        $host = strtolower($request->getHost());

        return $host === self::domain() || str_ends_with($host, '.'.self::domain());
    }

    /** Absolute URL of a path on a portal's host (keeps the scheme and port of the app URL). */
    public static function url(string $sub, string $pathAndQuery): string
    {
        $base = parse_url((string) config('app.url'));
        $scheme = $base['scheme'] ?? 'https';
        $port = isset($base['port']) ? ':'.$base['port'] : '';
        $host = ($sub === '' ? '' : $sub.'.').self::domain();

        return $scheme.'://'.$host.$port.'/'.ltrim($pathAndQuery, '/');
    }

    /** Where a portal's own address (its subdomain on its own) takes people. */
    public static function home(string $sub): ?string
    {
        $homes = (array) config('kasi.subdomains.homes');

        return isset($homes[$sub]) ? (string) $homes[$sub] : null;
    }
}

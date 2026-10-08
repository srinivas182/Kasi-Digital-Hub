<?php

declare(strict_types=1);

namespace Modules\Site\Http\Controllers;

use Illuminate\Http\Response;
use Modules\Site\Support\PublicHubs;

/**
 * sitemap.xml (public pages and every hub page) and robots.txt (private areas blocked).
 */
final class SeoController
{
    public const PUBLIC_PATHS = ['/', '/hubs', '/employers', '/funders', '/about', '/help', '/contact', '/legal/terms', '/legal/privacy'];

    public const PRIVATE_PATHS = ['/home', '/account', '/login', '/signup', '/two-factor', '/documents', '/consents', '/ui-kit', '/horizon'];

    public function sitemap(): Response
    {
        $base = rtrim((string) config('kasi.brand.url'), '/');
        $urls = [...self::PUBLIC_PATHS, ...array_map(static fn (array $hub): string => '/hubs/'.$hub['slug'], PublicHubs::all())];

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        foreach ($urls as $path) {
            $xml .= '  <url><loc>'.htmlspecialchars($base.($path === '/' ? '/' : $path), ENT_XML1).'</loc></url>'."\n";
        }

        return response($xml.'</urlset>'."\n", 200, ['Content-Type' => 'application/xml; charset=UTF-8', 'Cache-Control' => 'public, max-age=3600']);
    }

    public function robots(): Response
    {
        $lines = ['User-agent: *'];

        if (! app()->isProduction()) {
            $lines[] = 'Disallow: /'; // keep demo and staging environments out of search engines
        } else {
            foreach (self::PRIVATE_PATHS as $path) {
                $lines[] = "Disallow: {$path}";
            }
        }

        $lines[] = 'Sitemap: '.rtrim((string) config('kasi.brand.url'), '/').'/sitemap.xml';

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}

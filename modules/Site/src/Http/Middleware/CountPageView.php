<?php

declare(strict_types=1);

namespace Modules\Site\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cookie-free page counting for public pages: one counter per page (route name) per day.
 * Stores no IP address, cookie, user or device - nothing personal (POPIA).
 */
final class CountPageView
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->isMethod('GET') && $response->isSuccessful() && ! $this->isBot((string) $request->userAgent())) {
            $page = (string) ($request->route()?->getName() ?? 'unknown');
            $today = now('Africa/Johannesburg')->toDateString();

            DB::table('page_views')->upsert(
                [['day' => $today, 'page' => $page, 'views' => 1]],
                ['day', 'page'],
                ['views' => DB::raw('page_views.views + 1')],
            );
        }

        return $response;
    }

    private function isBot(string $userAgent): bool
    {
        return $userAgent === '' || preg_match('/bot|crawl|spider|slurp|facebookexternalhit|whatsapp|preview/i', $userAgent) === 1;
    }
}

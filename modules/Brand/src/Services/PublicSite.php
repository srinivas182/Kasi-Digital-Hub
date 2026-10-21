<?php

declare(strict_types=1);

namespace Modules\Brand\Services;

use Illuminate\Http\Request;
use Modules\Core\Enterprise\PublicSites;
use Symfony\Component\HttpFoundation\Response;

/** Serves published business websites at name.<domain> and /b/name (S31): server-built, tiny, cached. */
final readonly class PublicSite implements PublicSites
{
    public function __construct(private Sites $sites) {}

    public function respond(string $name, Request $request): ?Response
    {
        $data = $this->sites->publicData($name);
        if ($data === null) {
            return null;
        }
        // Taps are counted via ?tap=whatsapp|order on the site's own links, then sent on (no cookies, no personal data).
        $tap = (string) $request->query('tap', '');
        if ($tap === 'whatsapp' && ($data['shop']['whatsapp'] ?? null) !== null) {
            Sites::count($data['business_id'], 'whatsapp');

            return redirect()->away('https://wa.me/'.preg_replace('/\D/', '', (string) $data['shop']['whatsapp']));
        }
        if ($tap === 'order' && ($data['shop']['url'] ?? null) !== null) {
            Sites::count($data['business_id'], 'order');

            return redirect()->away((string) $data['shop']['url']);
        }
        if ($request->path() !== '/' && ! str_starts_with($request->path(), 'b/')) {
            return redirect()->away(Sites::url($name)); // a one-page site: everything else goes to its front page
        }
        Sites::count($data['business_id'], 'view');

        return response()->view('brand::site', ['s' => $data, 'url' => Sites::url($name)])
            ->header('Cache-Control', 'public, max-age=300')->header('X-Robots-Tag', 'index, follow');
    }
}

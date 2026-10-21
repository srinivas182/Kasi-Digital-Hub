<?php

declare(strict_types=1);

namespace Modules\Core\Enterprise;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Extension point (S31, implemented by KasiBrand): businesses' public one-page websites at name.<domain>. The portal
 * host middleware asks this for unknown subdomains, so Core never depends on KasiBrand. Bound only when KasiBrand is
 * installed; otherwise unknown subdomains go to the main site as before.
 */
interface PublicSites
{
    /** The published site's response for this address, or null if there is none. */
    public function respond(string $name, Request $request): ?Response;
}

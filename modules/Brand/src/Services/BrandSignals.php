<?php

declare(strict_types=1);

namespace Modules\Brand\Services;

use Illuminate\Support\Facades\DB;
use Modules\Core\Enterprise\BusinessSignals;

/** What KasiBrand knows about a business (S30): whether it has a brand kit. */
final class BrandSignals implements BusinessSignals
{
    public function signals(string $businessId): array
    {
        // One query for both signals (query budget on the support-offers page).
        $row = DB::selectOne('select exists(select 1 from brand_kits where business_id = ?) as kit, exists(select 1 from brand_sites where business_id = ? and status = ?) as site',
            [$businessId, $businessId, 'published']);

        return ['brand.has_kit' => (bool) ($row->kit ?? false), 'brand.has_website' => (bool) ($row->site ?? false)]; // S30, S31
    }
}

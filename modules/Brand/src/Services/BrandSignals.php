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
        return ['brand.has_kit' => DB::table('brand_kits')->where('business_id', $businessId)->exists()];
    }
}

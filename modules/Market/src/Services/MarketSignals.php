<?php

declare(strict_types=1);

namespace Modules\Market\Services;

use Illuminate\Support\Facades\DB;
use Modules\Core\Enterprise\BusinessSignals;
use Modules\Market\Models\Shop;

/** What KasiMarket knows about a business (S27): an open shop, orders completed in 6 months, the shop rating. */
final class MarketSignals implements BusinessSignals
{
    public function signals(string $businessId): array
    {
        $shop = Shop::query()->where('business_id', $businessId)->first();
        if (! $shop instanceof Shop) {
            return ['market.has_shop' => false, 'market.orders_6m' => 0, 'market.rating' => null];
        }

        return [
            'market.has_shop' => $shop->status === 'open',
            'market.orders_6m' => DB::table('market_orders')->where('shop_id', $shop->id)->where('status', 'completed')->where('completed_at', '>=', now()->subMonths(6))->count(),
            'market.rating' => Reviews::rating($shop->id),
        ];
    }
}

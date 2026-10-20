<?php

declare(strict_types=1);

namespace Modules\Market\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Core\Enterprise\BusinessTransactions;
use Modules\Market\Models\Order;
use Modules\Market\Models\Shop;

/** KasiMarket's money for a business's books (S28): completed orders as sales, refunds as money out. */
final class MarketTransactions implements BusinessTransactions
{
    public function since(string $businessId, CarbonImmutable $from): array
    {
        $shop = Shop::query()->where('business_id', $businessId)->first();
        if (! $shop instanceof Shop) {
            return [];
        }
        $out = [];
        foreach (Order::query()->where('shop_id', $shop->id)->where('status', 'completed')->where('completed_at', '>=', $from)->orderBy('completed_at')->get() as $o) {
            $out[] = ['ref' => 'market:sale:'.$o->number, 'direction' => 'in', 'amount_cents' => $o->total_cents, 'category' => 'sales',
                'method' => $o->getAttribute('payment') === 'online' ? 'card' : 'cash', 'date' => $o->completed_at ?? CarbonImmutable::now(),
                'note' => __('market.books.sale', ['number' => $o->number])];
        }
        // Refunds (online orders only - cash never passed through KasiHub): one record per order holding the total
        // refunded so far; KasiBiz updates its amount if more is refunded later (never a second record).
        $refunds = DB::table('market_orders')->where('shop_id', $shop->id)->where('refunded_cents', '>', 0)->where('updated_at', '>=', $from)
            ->get(['number', 'refunded_cents', 'updated_at']);
        foreach ($refunds as $r) {
            $out[] = ['ref' => 'market:refund:'.$r->number, 'direction' => 'out', 'amount_cents' => (int) $r->refunded_cents, 'category' => 'refunds',
                'method' => 'card', 'date' => CarbonImmutable::parse((string) $r->updated_at), 'note' => __('market.books.refund', ['number' => $r->number])];
        }

        return $out;
    }
}

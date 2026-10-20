<?php

declare(strict_types=1);

namespace Modules\Market\Services;

use Illuminate\Support\Facades\DB;
use Modules\Market\Models\Order;
use Modules\Market\Models\Shop;

/** Trader insights (S27): sales by week, top products, views to orders, repeat buyers, ratings, money received. */
final class Insights
{
    /**
     * @return array{weeks: list<array{week: string, orders: int, cents: int}>, top: list<array{title: string, quantity: int, cents: int}>,
     *     views: int, orders: int, conversion: float|null, repeat_buyers: int, rating: float|null, reviews: int, online_cents: int, cash_cents: int}
     */
    public static function for(Shop $shop, int $days): array
    {
        $from = now()->subDays($days);
        $done = Order::query()->where('shop_id', $shop->id)->where('status', 'completed')->where('completed_at', '>=', $from)->get();
        $weeks = [];
        foreach ($done as $o) {
            $w = $o->completed_at?->startOfWeek()->toDateString() ?? '';
            $weeks[$w] ??= ['week' => $w, 'orders' => 0, 'cents' => 0];
            $weeks[$w]['orders']++;
            $weeks[$w]['cents'] += $o->total_cents - (int) $o->getAttribute('refunded_cents');
        }
        ksort($weeks);
        $top = DB::table('market_order_items')->join('market_orders', 'market_orders.id', '=', 'market_order_items.order_id')
            ->where('market_orders.shop_id', $shop->id)->where('market_orders.status', 'completed')->where('market_orders.completed_at', '>=', $from)
            ->groupBy('market_order_items.title')->orderByDesc(DB::raw('sum(market_order_items.quantity)'))->limit(5)
            ->get(['market_order_items.title', DB::raw('sum(market_order_items.quantity) as q'), DB::raw('sum(market_order_items.quantity * market_order_items.unit_price_cents) as c')]);
        $views = (int) DB::table('market_products')->where('shop_id', $shop->id)->sum('views');
        $orders = Order::query()->where('shop_id', $shop->id)->where('created_at', '>=', $from)->count();
        $buyers = $done->groupBy('buyer_id');

        return [
            'weeks' => array_values($weeks),
            'top' => array_values($top->map(static fn (object $r): array => ['title' => (string) $r->title, 'quantity' => (int) $r->q, 'cents' => (int) $r->c])->all()),
            'views' => $views, 'orders' => $orders, 'conversion' => $views > 0 ? round(100 * $orders / $views, 1) : null,
            'repeat_buyers' => $buyers->filter(static fn ($g) => $g->count() > 1)->count(),
            'rating' => Reviews::rating($shop->id), 'reviews' => DB::table('market_reviews')->where('shop_id', $shop->id)->where('status', 'sent')->count(),
            'online_cents' => (int) $done->where('payment', 'online')->sum(static fn (Order $o): int => $o->total_cents - (int) $o->getAttribute('refunded_cents')),
            'cash_cents' => (int) $done->where('payment', 'cash')->sum('total_cents'),
        ];
    }

    /**
     * CSV of the shop's orders (for bookkeeping in KasiBiz).
     *
     * @return list<list<string|int>>
     */
    public static function csvRows(Shop $shop, int $days): array
    {
        $rows = [['number', 'placed', 'status', 'payment', 'fulfilment', 'items_cents', 'delivery_cents', 'total_cents', 'refunded_cents', 'completed']];
        foreach (Order::query()->where('shop_id', $shop->id)->where('created_at', '>=', now()->subDays($days))->orderBy('created_at')->get() as $o) {
            $rows[] = [$o->number, $o->created_at->toDateTimeString(), $o->status, (string) $o->getAttribute('payment'), $o->fulfilment, $o->items_cents,
                $o->delivery_fee_cents, $o->total_cents, (int) $o->getAttribute('refunded_cents'), (string) ($o->completed_at?->toDateTimeString() ?? '')];
        }

        return $rows;
    }
}

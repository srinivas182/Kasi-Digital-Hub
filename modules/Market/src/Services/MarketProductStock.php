<?php

declare(strict_types=1);

namespace Modules\Market\Services;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Core\Enterprise\ProductStock;
use Modules\Market\Models\Product;
use Modules\Market\Models\Shop;

/** KasiMarket's side of shared stock (S29): products with a quantity, read and adjusted for KasiBiz. */
final class MarketProductStock implements ProductStock
{
    public function products(string $businessId): array
    {
        $shop = Shop::query()->where('business_id', $businessId)->first();
        if (! $shop instanceof Shop) {
            return [];
        }

        return array_values(Product::query()->where('shop_id', $shop->id)->where('stock_type', 'quantity')->whereNotIn('status', ['refused'])->orderBy('title')->get()
            ->map(static fn (Product $p): array => ['id' => $p->id, 'title' => $p->title, 'stock' => (int) $p->stock])->all());
    }

    public function quantity(string $businessId, string $productId): ?int
    {
        $product = $this->product($businessId, $productId);

        return $product?->stock !== null ? (int) $product->stock : null;
    }

    public function adjust(string $businessId, string $productId, int $change): int
    {
        $product = $this->product($businessId, $productId);
        if (! $product instanceof Product) {
            throw new DomainException(__('biz.stock.not_linked'));
        }

        return DB::transaction(function () use ($product, $change): int {
            $current = (int) DB::table('market_products')->where('id', $product->id)->lockForUpdate()->value('stock');
            $new = max(0, $current + $change);
            DB::table('market_products')->where('id', $product->id)->update(['stock' => $new, 'updated_at' => now()]);

            return $new;
        });
    }

    private function product(string $businessId, string $productId): ?Product
    {
        $shop = Shop::query()->where('business_id', $businessId)->first();

        return $shop instanceof Shop ? Product::query()->where('shop_id', $shop->id)->where('stock_type', 'quantity')->find($productId) : null;
    }
}

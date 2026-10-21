<?php

declare(strict_types=1);

namespace Modules\Market\Services;

use Modules\Core\Enterprise\BusinessProducts;
use Modules\Market\Models\Product;
use Modules\Market\Models\Shop;

/** KasiMarket's public shop and listed products for other portals (S30). Nothing that isn't already public. */
final class MarketBusinessProducts implements BusinessProducts
{
    public function shop(string $businessId): ?array
    {
        $shop = Shop::query()->where('business_id', $businessId)->where('status', 'open')->first();

        return $shop instanceof Shop ? ['name' => $shop->name, 'url' => url('/market/shops/'.$shop->slug),
            'whatsapp' => $shop->whatsapp_public ? $shop->whatsapp : null, 'area' => $shop->area, 'hours' => $shop->hours ?? []] : null;
    }

    public function products(string $businessId, int $limit = 60): array
    {
        $shop = Shop::query()->where('business_id', $businessId)->where('status', 'open')->first();
        if (! $shop instanceof Shop) {
            return [];
        }

        return array_values(Product::query()->listed()->where('shop_id', $shop->id)->with('photos')->orderBy('title')->limit($limit)->get()
            ->map(static fn (Product $p): array => ['id' => $p->id, 'title' => $p->title, 'price_cents' => $p->price_cents, 'unit' => $p->unit,
                'photo' => $p->photos->first() !== null ? asset('storage/'.$p->photos->first()->medium_path) : null, 'url' => url('/market/products/'.$p->id)])->all());
    }
}

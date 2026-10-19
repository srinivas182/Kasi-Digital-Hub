<?php

declare(strict_types=1);

namespace Modules\Market\Services;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Core\Identity\Models\User;
use Modules\Market\Models\Product;
use Modules\Market\Models\ProductOption;
use Modules\Market\Models\Shop;

/** The buyer's cart (S25): saved to the account, grouped by shop; prices and stock rechecked whenever it is read. */
final class Cart
{
    public const MAX_QUANTITY = 50;

    public function add(User $user, Product $product, ?int $optionId, int $quantity): void
    {
        if (! Product::query()->listed()->whereKey($product->id)->exists()) {
            throw new DomainException(__('market.cart.unavailable'));
        }
        if ($optionId !== null && ! ProductOption::query()->whereKey($optionId)->where('product_id', $product->id)->exists()) {
            throw new DomainException(__('market.cart.unavailable'));
        }
        $existing = DB::table('market_cart_items')->where(['user_id' => $user->id, 'product_id' => $product->id, 'option_id' => $optionId])->first();
        $quantity = max(1, min(self::MAX_QUANTITY, $quantity + (int) ($existing->quantity ?? 0)));
        DB::table('market_cart_items')->updateOrInsert(['user_id' => $user->id, 'product_id' => $product->id, 'option_id' => $optionId],
            ['quantity' => $quantity, 'updated_at' => now(), 'created_at' => $existing->created_at ?? now()]);
    }

    public function set(User $user, int $itemId, int $quantity): void
    {
        $query = DB::table('market_cart_items')->where('id', $itemId)->where('user_id', $user->id);
        $quantity <= 0 ? $query->delete() : $query->update(['quantity' => min(self::MAX_QUANTITY, $quantity), 'updated_at' => now()]);
    }

    /**
     * The cart grouped by shop, with current prices and problems (unavailable, sold out, not enough stock).
     *
     * @return list<array{shop: Shop, lines: list<array{id: int, product: Product, option: ProductOption|null, quantity: int, unit_price_cents: int, problem: string|null}>, items_cents: int}>
     */
    public function contents(User $user, ?string $shopId = null): array
    {
        $rows = DB::table('market_cart_items')->where('user_id', $user->id)->orderBy('id')->get();
        $products = Product::query()->with(['shop', 'options'])->whereIn('id', $rows->pluck('product_id'))->get()->keyBy('id');
        $groups = [];
        foreach ($rows as $row) {
            $product = $products->get($row->product_id);
            if (! $product instanceof Product || ($shopId !== null && $product->shop_id !== $shopId)) {
                continue;
            }
            $option = $row->option_id !== null ? $product->options->firstWhere('id', $row->option_id) : null;
            $price = (int) ($option->price_cents ?? $product->price_cents);
            $available = $product->status === 'published' && $product->shop->status === 'open';
            $stock = $product->stock_type === 'quantity' ? (int) ($option->stock ?? $product->stock ?? 0) : null;
            $problem = ! $available ? 'unavailable' : ($stock !== null && $stock <= 0 ? 'sold_out' : ($stock !== null && $stock < (int) $row->quantity ? 'not_enough' : null));
            $groups[$product->shop_id] ??= ['shop' => $product->shop, 'lines' => [], 'items_cents' => 0];
            $groups[$product->shop_id]['lines'][] = ['id' => (int) $row->id, 'product' => $product, 'option' => $option, 'quantity' => (int) $row->quantity, 'unit_price_cents' => $price, 'problem' => $problem];
            if ($problem === null) {
                $groups[$product->shop_id]['items_cents'] += $price * (int) $row->quantity;
            }
        }

        return array_values($groups);
    }

    public function clearShop(User $user, string $shopId): void
    {
        DB::table('market_cart_items')->where('user_id', $user->id)
            ->whereIn('product_id', DB::table('market_products')->where('shop_id', $shopId)->pluck('id'))->delete();
    }
}

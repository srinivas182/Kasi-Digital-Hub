<?php

declare(strict_types=1);

namespace Modules\Commercial\Services;

use Modules\Commercial\Models\Order;
use Modules\Commercial\Models\Product;
use Modules\Core\Commerce\Payments;
use Modules\Core\Commerce\Sale;
use Modules\Core\Identity\Models\User;
use Modules\Core\Structure\Models\Hub;

/** The Core Payments contract (S26): marketplace sales paid online, split at source, refundable. */
final readonly class SalePayments implements Payments
{
    public function __construct(private Orders $orders) {}

    public function startSale(Sale $sale): array
    {
        $product = Product::query()->where('key', $sale->productKey)->where('active', true)->firstOrFail();
        $hub = $sale->hubId !== null ? Hub::query()->find($sale->hubId) : null;
        $order = $this->orders->createSale($product, $sale->itemsCents, $sale->sellerExtraCents, $hub instanceof Hub ? $hub : null, $sale->payeeRef, $sale->buyer,
            [...$sale->meta, 'seller_subaccount' => $sale->sellerSubaccount]);
        $start = $this->orders->checkout($order->load('product'), (string) ($sale->buyer->email ?? 'payments@kasidigitalhub.co.za'), $sale->returnUrl);

        return ['url' => $start['url'], 'reference' => $order->id];
    }

    public function refundSale(string $reference, int $amountCents, User $by, string $reason): void
    {
        $order = Order::query()->with('product')->findOrFail($reference);
        $this->orders->refund($order, min($amountCents, $order->total_cents - $order->refunded_cents), $by, $reason);
    }
}

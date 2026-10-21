<?php

declare(strict_types=1);

namespace Modules\Core\Enterprise;

/**
 * Extension point (S29, implemented by KasiMarket): a business's products that keep a stock count, read and adjusted
 * by other portals (KasiBiz stock), so one quantity is shared and neither portal reads the other's tables.
 */
interface ProductStock
{
    /** @return list<array{id: string, title: string, stock: int}> products that count stock */
    public function products(string $businessId): array;

    /** The current quantity, or null if the product isn't this business's or doesn't count stock. */
    public function quantity(string $businessId, string $productId): ?int;

    /** Change the quantity by $change (never below zero); returns the new quantity. */
    public function adjust(string $businessId, string $productId, int $change): int;
}

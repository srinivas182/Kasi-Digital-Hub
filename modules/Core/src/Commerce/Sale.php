<?php

declare(strict_types=1);

namespace Modules\Core\Commerce;

use Modules\Core\Identity\Models\User;

/**
 * A marketplace sale paid online (S26): the seller (e.g. a KasiMarket trader) is the merchant; the hub and Kasi take
 * their shares at source. Amounts in cents. `sellerExtraCents` (e.g. a delivery fee) goes wholly to the seller.
 */
final readonly class Sale
{
    /** @param array<string, string|int|bool|null> $meta passed back to the portal's Fulfiller when paid or refunded */
    public function __construct(
        public string $productKey,
        public string $payeeRef,
        public ?string $sellerSubaccount,
        public ?string $hubId,
        public User $buyer,
        public int $itemsCents,
        public int $sellerExtraCents,
        public array $meta,
        public string $returnUrl,
    ) {}
}

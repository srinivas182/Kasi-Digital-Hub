<?php

declare(strict_types=1);

namespace Modules\Market\Services;

use Modules\Core\Commerce\Fulfiller;

/** Payment confirmation from the commercial portal (S26): marks the KasiMarket order paid. */
final readonly class OrderPayments implements Fulfiller
{
    public function __construct(private Ordering $ordering) {}

    public function products(): array
    {
        return ['market_order'];
    }

    public function fulfil(string $productKey, int $quantity, array $meta): void
    {
        $this->ordering->paid((string) ($meta['market_order'] ?? ''));
    }

    /** A full refund is already recorded on the KasiMarket order by the step that asked for it. */
    public function revoke(string $productKey, int $quantity, array $meta): void {}
}

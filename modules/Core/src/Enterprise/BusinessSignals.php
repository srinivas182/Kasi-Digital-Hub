<?php

declare(strict_types=1);

namespace Modules\Core\Enterprise;

/**
 * Extension point (S27): a portal contributes facts about a business from its own activity (e.g. KasiMarket: has an
 * online shop, orders completed, rating), so partner eligibility can use them without reading that portal's tables.
 * Keys are prefixed with the portal, e.g. "market.has_shop". Tag implementations with TAG.
 */
interface BusinessSignals
{
    public const TAG = 'kasi.business_signals';

    /** @return array<string, int|float|bool|null> */
    public function signals(string $businessId): array;
}

<?php

declare(strict_types=1);

namespace Modules\Core\Enterprise;

use Carbon\CarbonImmutable;

/**
 * Extension point (S28): a portal provides money a business received or paid through it (e.g. KasiMarket sales and
 * refunds), so KasiBiz can fill in the cash book without reading that portal's tables. Each record has a stable
 * `ref` so it is imported only once. Tag implementations with TAG.
 */
interface BusinessTransactions
{
    public const TAG = 'kasi.business_transactions';

    /**
     * @return list<array{ref: string, direction: 'in'|'out', amount_cents: int, category: string, method: string, date: CarbonImmutable, note: string}>
     */
    public function since(string $businessId, CarbonImmutable $from): array;
}

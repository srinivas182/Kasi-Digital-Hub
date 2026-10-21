<?php

declare(strict_types=1);

namespace Modules\Core\Enterprise;

/**
 * Extension point (S30, implemented by KasiMarket): a business's public shop and listed products, for other portals
 * (e.g. KasiBrand price lists and the WhatsApp catalogue). Only what is already public on KasiMarket.
 */
interface BusinessProducts
{
    /** @return array{name: string, url: string, whatsapp: string|null, area: string|null, hours: array<string, string>}|null */
    public function shop(string $businessId): ?array;

    /** @return list<array{id: string, title: string, price_cents: int, unit: string, photo: string|null, url: string}> listed products only */
    public function products(string $businessId, int $limit = 60): array;
}

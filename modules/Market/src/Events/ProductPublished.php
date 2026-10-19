<?php

declare(strict_types=1);

namespace Modules\Market\Events;

final class ProductPublished extends MarketEvent
{
    public const DESCRIPTION = 'A product or service went live in a KasiMarket shop.';

    public const NAME = 'market.product.published';
}

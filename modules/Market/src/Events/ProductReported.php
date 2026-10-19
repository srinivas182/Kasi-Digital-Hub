<?php

declare(strict_types=1);

namespace Modules\Market\Events;

final class ProductReported extends MarketEvent
{
    public const DESCRIPTION = 'Someone reported a KasiMarket product.';

    public const NAME = 'market.product.reported';
}

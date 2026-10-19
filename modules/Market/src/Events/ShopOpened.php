<?php

declare(strict_types=1);

namespace Modules\Market\Events;

final class ShopOpened extends MarketEvent
{
    public const DESCRIPTION = 'A trader opened a KasiMarket shop.';

    public const NAME = 'market.shop.opened';
}

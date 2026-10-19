<?php

declare(strict_types=1);

namespace Modules\Market\Events;

final class OrderLapsed extends MarketEvent
{
    public const DESCRIPTION = 'A KasiMarket order lapsed because the trader did not respond within 48 hours.';

    public const NAME = 'market.order.lapsed';
}

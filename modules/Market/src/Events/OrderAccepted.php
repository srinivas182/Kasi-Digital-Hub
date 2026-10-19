<?php

declare(strict_types=1);

namespace Modules\Market\Events;

final class OrderAccepted extends MarketEvent
{
    public const DESCRIPTION = 'A trader accepted a KasiMarket order.';

    public const NAME = 'market.order.accepted';
}
